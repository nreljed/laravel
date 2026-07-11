<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AccountService
{
    public function __construct(
        protected MailService $mailService,
    ) {}

    public function updateEmail(User $user, string $newEmail): array
    {
        $oldEmail = $user->email;

        if ($newEmail === $oldEmail) {
            return ['success' => true, 'message' => 'Email address is already up to date.'];
        }

        $requireVerification = SettingsService::get('require_email_verification', '0') === '1';

        $user->email = $newEmail;

        if ($requireVerification) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($requireVerification) {
            $user->sendEmailVerificationNotification();
        }

        $this->sendEmailChangeNotification($oldEmail, $newEmail, $user->name ?? '');

        AuditService::log(AuditAction::EmailChange, $user->id, [
            'old_email' => $oldEmail,
            'new_email' => $newEmail,
        ]);

        $message = $requireVerification
            ? 'Email updated successfully. A verification link has been sent to your new email.'
            : 'Email updated successfully.';

        return ['success' => true, 'message' => $message];
    }

    public function updatePassword(User $user, string $currentPassword, string $newPassword): array
    {
        if (! Hash::check($currentPassword, $user->password)) {
            return ['success' => false, 'message' => 'The current password you entered is incorrect.'];
        }

        if (Hash::check($newPassword, $user->password)) {
            return ['success' => false, 'message' => 'Your new password must be different from your current password.'];
        }

        $user->update([
            'password' => Hash::make($newPassword),
        ]);

        AuditService::log(AuditAction::PasswordChange, $user->id);

        $this->sendPasswordChangeConfirmation($user);

        return ['success' => true, 'message' => 'Password updated successfully! A confirmation email has been sent.'];
    }

    public function verifyPassword(User $user, string $password): array
    {
        return [
            'success' => Hash::check($password, $user->password),
            'verified' => Hash::check($password, $user->password),
        ];
    }

    public function updatePersonalInfo(User $user, array $data): void
    {
        if (isset($data['description'])) {
            $data['bio'] = $data['description'];
            unset($data['description']);
        }

        $user->fill($data);
        $user->save();

        AuditService::log(AuditAction::ProfileUpdate, $user->id, [
            'changed_fields' => array_keys($user->getChanges()),
        ]);
    }

    public function updateContactInfo(User $user, array $phoneNumbers, ?array $socialMedia): void
    {
        if ($phoneNumbers !== null) {
            $filtered = array_filter($phoneNumbers, function ($item) {
                return ! empty($item['label']) || ! empty($item['phone']);
            });
            $processed = array_map(function ($item) {
                if (! empty($item['label']) && $item['label'] === 'Other' && ! empty($item['label_custom'])) {
                    $item['label'] = $item['label_custom'];
                    unset($item['label_custom']);
                }

                return $item;
            }, $filtered);
            $user->phone = json_encode(array_values($processed));
        }

        if ($socialMedia !== null) {
            $filtered = array_filter($socialMedia, function ($item) {
                return ! empty($item['social_title']) && ! empty($item['social_value']);
            });
            $processed = array_map(function ($item) {
                if (! empty($item['social_value'])) {
                    $url = trim($item['social_value']);
                    if (! preg_match('/^[a-zA-Z][a-zA-Z0-9+.-]*:\/\//', $url)) {
                        $url = 'https://'.$url;
                    }
                    $item['social_value'] = $url;
                }
                if (! empty($item['social_title']) && $item['social_title'] === 'Other' && ! empty($item['social_title_custom'])) {
                    $item['social_title'] = $item['social_title_custom'];
                    unset($item['social_title_custom']);
                }

                return $item;
            }, $filtered);
            $user->social_links = json_encode(array_values($processed));
        }

        $user->save();

        AuditService::log(AuditAction::ProfileUpdate, $user->id, [
            'changed_fields' => array_keys($user->getChanges()),
        ]);
    }

    public function deleteAccount(User $user): void
    {
        AuditService::log(AuditAction::AccountDelete, $user->id);

        $this->deleteAllUserData($user);

        $email = $user->email;
        $displayName = $user->display_name ?: $user->name;

        $user->delete();

        $this->sendDeletionConfirmationEmail($email, $displayName);
    }

    public function deleteAllUserData(User $user): void
    {
        try {
            app(FeatureRegistry::class)->cleanupUserData($user->id);
        } catch (\Throwable $e) {
            Log::warning('Module data cleanup failed for user '.$user->id.': '.$e->getMessage());
        }

        if (method_exists($user, 'tokens')) {
            try {
                $user->tokens()->delete();
            } catch (\Throwable $e) {
                Log::warning('Token cleanup failed for user '.$user->id.': '.$e->getMessage());
            }
        }

        $user->notifications()->delete();

        NotificationPreference::where('user_id', $user->id)->delete();
    }

    protected function sendPasswordChangeConfirmation(User $user): void
    {
        try {
            $this->mailService->sendNow(
                $user->email,
                '['.config('app.name').'] Your password has been changed',
                'password-changed',
                [
                    'userName' => $user->display_name ?: $user->name,
                    'resetUrl' => route('password.request'),
                    'siteName' => config('app.name'),
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Failed to send password change confirmation to: '.$user->email.' | Error: '.$e->getMessage());
        }
    }

    protected function sendEmailChangeNotification(string $oldEmail, string $newEmail, string $userName): void
    {
        try {
            $this->mailService->sendNow(
                $oldEmail,
                '['.config('app.name').'] Email address updated',
                'email-changed',
                [
                    'userName' => $userName,
                    'oldEmail' => $oldEmail,
                    'newEmail' => $newEmail,
                    'siteName' => config('app.name'),
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Failed to send email change notification to: '.$oldEmail.' | Error: '.$e->getMessage());
        }
    }

    protected function sendDeletionConfirmationEmail(string $email, string $displayName): void
    {
        try {
            $this->mailService->sendNow(
                $email,
                '['.config('app.name').'] Your account has been deleted',
                'account-deleted',
                [
                    'userName' => $displayName,
                    'siteName' => config('app.name'),
                    'siteUrl' => route('home'),
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Failed to send deletion confirmation email to: '.$email.' | Error: '.$e->getMessage());
        }
    }
}
