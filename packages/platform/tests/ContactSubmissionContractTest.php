<?php

namespace Nreljed\Platform\Tests;

use Nreljed\Platform\Http\Requests\ContactRequest;
use Nreljed\Platform\Models\ContactSubmission;
use Nreljed\Platform\Services\NotificationTypeRegistry;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Framework-free contract tests for the public contact form feature.
 *
 * The platform package deliberately does not boot a Laravel application in its
 * own CI — HTTP handling, Eloquent persistence and notification dispatch are
 * integration-tested inside consuming apps. What is pinned here is the static
 * surface every consumer depends on: the submission status vocabulary, the
 * request contract, the admin notification wiring, and the email template.
 *
 * @coversNothing
 */
final class ContactSubmissionContractTest extends TestCase
{
    public function test_status_vocabulary_is_new_read_archived(): void
    {
        $this->assertSame('new', ContactSubmission::STATUS_NEW);
        $this->assertSame('read', ContactSubmission::STATUS_READ);
        $this->assertSame('archived', ContactSubmission::STATUS_ARCHIVED);
    }

    public function test_statuses_returns_the_canonical_order(): void
    {
        $this->assertSame(['new', 'read', 'archived'], ContactSubmission::statuses());
    }

    public function test_contact_request_requires_a_message_and_allows_a_blank_subject(): void
    {
        $rules = (new ContactRequest)->rules();

        $this->assertContains('required', $rules['name']);
        $this->assertContains('max:120', $rules['name']);
        $this->assertContains('required', $rules['email']);
        $this->assertContains('max:190', $rules['email']);
        $this->assertContains('nullable', $rules['subject']);
        $this->assertContains('max:190', $rules['subject']);
        $this->assertContains('required', $rules['message']);
        $this->assertContains('min:10', $rules['message']);
        $this->assertContains('max:5000', $rules['message']);
    }

    public function test_contact_request_keeps_the_honeypot_field_out_of_its_rules(): void
    {
        $rules = (new ContactRequest)->rules();

        $this->assertArrayNotHasKey(
            'website',
            $rules,
            'the honeypot must stay absent from validation rules so a bot cannot '
            .'learn which field trapped it',
        );
    }

    public function test_contact_received_is_a_registered_core_notification_type(): void
    {
        $type = $this->coreTypes()['contact.received'] ?? null;

        $this->assertIsArray($type, 'contact.received must be a core-owned notification type');
        $this->assertSame('contact-received', $type['email_template']);
        $this->assertSame('Contact', $type['group']);
        $this->assertTrue($type['required']);
        $this->assertContains('in_app', $type['default_channels']);
        $this->assertContains('email', $type['default_channels']);
    }

    public function test_contact_received_template_exists_and_exposes_the_expected_variables(): void
    {
        $path = dirname(__DIR__).'/resources/views/email/templates/contact-received.blade.php';

        $this->assertFileExists($path);

        $blade = (string) file_get_contents($path);

        foreach (['$senderName', '$senderEmail', '$submittedAt', '$contactUrl', '$siteName'] as $variable) {
            $this->assertStringContainsString(
                $variable,
                $blade,
                "[{$variable}] must be rendered by the contact-received template",
            );
        }

        $this->assertStringContainsString(
            'white-space: pre-wrap',
            $blade,
            'the message body must preserve the sender\'s line breaks',
        );
    }

    public function test_contact_received_template_is_optional_about_the_subject_line(): void
    {
        $blade = (string) file_get_contents(
            dirname(__DIR__).'/resources/views/email/templates/contact-received.blade.php',
        );

        $this->assertMatchesRegularExpression(
            '/\$subjectLine/',
            $blade,
            'the optional subject must be rendered when present',
        );
        $this->assertStringContainsString(
            'empty($subjectLine)',
            $blade,
            'the subject row must be skipped when the sender left it blank',
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function coreTypes(): array
    {
        $instance = (new ReflectionMethod(NotificationTypeRegistry::class, '__construct'))
            ->getDeclaringClass()
            ->newInstanceWithoutConstructor();

        $reflection = new ReflectionMethod(NotificationTypeRegistry::class, 'getCoreTypes');
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($instance, []);
    }
}
