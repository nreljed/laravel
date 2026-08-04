<?php

namespace Nreljed\DashboardCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Nreljed\DashboardCore\Ai\Models\AiProvider;
use Nreljed\DashboardCore\Ai\Models\AiUsageLog;
use Nreljed\DashboardCore\Enums\AuditAction;
use Nreljed\DashboardCore\Models\NotificationPreference;
use Nreljed\DashboardCore\Models\User;

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
            'password' => $newPassword,
        ]);

        AuditService::log(AuditAction::PasswordChange, $user->id);

        $this->sendPasswordChangeConfirmation($user);

        return ['success' => true, 'message' => 'Password updated successfully! A confirmation email has been sent.'];
    }

    public function verifyPassword(User $user, string $password): array
    {
        $verified = Hash::check($password, $user->password);

        return [
            'success' => $verified,
            'verified' => $verified,
        ];
    }

    public function updatePersonalInfo(User $user, array $data): void
    {
        $user->fill($data);
        $user->save();

        AuditService::log(AuditAction::ProfileUpdate, $user->id, [
            'changed_fields' => array_keys($user->getChanges()),
        ]);
    }

    public function updateContactInfo(User $user, ?array $phoneNumbers, ?array $socialMedia): void
    {
        if ($phoneNumbers !== null) {
            $filtered = array_filter($phoneNumbers, fn ($item) => ! empty($item['label']) || ! empty($item['phone']));
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
            $filtered = array_filter($socialMedia, fn ($item) => ! empty($item['social_title']) && ! empty($item['social_value']));
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
            $user->social_links = array_values($processed);
        }

        $user->save();

        AuditService::log(AuditAction::ProfileUpdate, $user->id, [
            'changed_fields' => array_keys($user->getChanges()),
        ]);
    }

    public function deleteAccount(User $user): void
    {
        AuditService::log(AuditAction::AccountDelete, $user->id, [
            'target_user_id' => $user->id,
            'target_name' => $user->name,
            'target_email' => $user->email,
            'target_username' => $user->username,
            'target_display_name' => $user->display_name,
        ]);

        $email = $user->email;
        $displayName = $user->display_name ?: $user->name;

        $this->deleteAllUserData($user);

        $this->sendDeletionConfirmationEmail($email, $displayName);

        $user->delete();
    }

    public function deleteAllUserData(User $user): void
    {
        if ($user->avatar) {
            try {
                Storage::disk('avatars')->delete($user->avatar);
            } catch (\Throwable $e) {
                Log::warning('Avatar cleanup failed for user '.$user->id.': '.$e->getMessage());
            }
        }

        try {
            app(FeatureRegistry::class)->cleanupUserData($user->id);
        } catch (\Throwable $e) {
            Log::warning('Module data cleanup failed for user '.$user->id.': '.$e->getMessage());
        }

        try {
            $user->tokens()->delete();
        } catch (\Throwable $e) {
            Log::warning('Token cleanup failed for user '.$user->id.': '.$e->getMessage());
        }

        $user->notifications()->delete();

        NotificationPreference::where('user_id', $user->id)->delete();

        DB::table('sessions')->where('user_id', $user->id)->delete();

        AiProvider::where('user_id', $user->id)->delete();
        AiUsageLog::where('user_id', $user->id)->delete();

        try {
            $this->deleteUserConversations($user->id);
        } catch (\Throwable $e) {
            Log::warning('Conversation cleanup failed for user '.$user->id.': '.$e->getMessage());
        }
    }

    protected function deleteUserConversations(int $userId): void
    {
        $conversationsTable = config('ai.conversations.tables.conversations', 'agent_conversations');
        $messagesTable = config('ai.conversations.tables.messages', 'agent_conversation_messages');

        $conversationIds = DB::table($conversationsTable)
            ->where('user_id', $userId)
            ->pluck('id');

        if ($conversationIds->isNotEmpty()) {
            DB::table($messagesTable)->whereIn('conversation_id', $conversationIds)->delete();
        }

        DB::table($messagesTable)->where('user_id', $userId)->delete();
        DB::table($conversationsTable)->where('user_id', $userId)->delete();
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
