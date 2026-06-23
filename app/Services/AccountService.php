<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\User;
use App\Models\NotificationPreference;
use App\Modules\ModuleRegistry;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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
        if (!Hash::check($currentPassword, $user->password)) {
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
            ModuleRegistry::cleanupUserData($user->id);
        } catch (\Throwable $e) {
            Log::warning('Module data cleanup failed for user ' . $user->id . ': ' . $e->getMessage());
        }

        if (method_exists($user, 'tokens')) {
            try {
                $user->tokens()->delete();
            } catch (\Throwable $e) {
                Log::warning('Token cleanup failed for user ' . $user->id . ': ' . $e->getMessage());
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
                '[' . config('app.name') . '] Your password has been changed',
                'password-changed',
                [
                    'userName' => $user->display_name ?: $user->name,
                    'resetUrl' => url('/forgot-password'),
                    'siteName' => config('app.name'),
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Failed to send password change confirmation to: ' . $user->email . ' | Error: ' . $e->getMessage());
        }
    }

    protected function sendEmailChangeNotification(string $oldEmail, string $newEmail, string $userName): void
    {
        try {
            $this->mailService->sendNow(
                $oldEmail,
                '[' . config('app.name') . '] Email address updated',
                'email-changed',
                [
                    'userName' => $userName,
                    'oldEmail' => $oldEmail,
                    'newEmail' => $newEmail,
                    'siteName' => config('app.name'),
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Failed to send email change notification to: ' . $oldEmail . ' | Error: ' . $e->getMessage());
        }
    }

    protected function sendDeletionConfirmationEmail(string $email, string $displayName): void
    {
        try {
            $this->mailService->sendNow(
                $email,
                '[' . config('app.name') . '] Your account has been deleted',
                'account-deleted',
                [
                    'userName' => $displayName,
                    'siteName' => config('app.name'),
                    'siteUrl' => url('/'),
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Failed to send deletion confirmation email to: ' . $email . ' | Error: ' . $e->getMessage());
        }
    }
}
