<?php

namespace Nreljed\Platform\Tests;

use Nreljed\Platform\Services\EmailSettingsService;
use Nreljed\Platform\Services\MailerConfigurator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Email;

/**
 * Unit tests for per-message envelope settings (force from / reply-to /
 * return-path) applied via MailerConfigurator::applyMessageSettings().
 * Mirrors the PHPMailer behaviour in the Project-WP reference implementation.
 */
final class MailEnvelopeSettingsTest extends TestCase
{
    private function configurator(array $options): MailerConfigurator
    {
        return new MailerConfigurator(new class($options) extends EmailSettingsService
        {
            public function __construct(private array $stored) {}

            public function getOptions(): array
            {
                return array_merge(parent::defaults(), $this->stored);
            }
        });
    }

    public function test_force_from_email_sets_sender_address_and_name(): void
    {
        $configurator = $this->configurator([
            'force_from_email' => true,
            'from_email' => 'noreply@example.com',
            'from_name' => 'My App',
        ]);
        $email = new Email;

        $configurator->applyMessageSettings($email);

        $from = current($email->getFrom());
        $this->assertSame('noreply@example.com', $from->getAddress());
        $this->assertSame('My App', $from->getName());
    }

    public function test_force_from_name_alone_also_sets_sender(): void
    {
        $configurator = $this->configurator([
            'force_from_email' => false,
            'force_from_name' => true,
            'from_email' => 'noreply@example.com',
            'from_name' => 'My App',
        ]);
        $email = new Email;

        $configurator->applyMessageSettings($email);

        $this->assertSame('noreply@example.com', current($email->getFrom())->getAddress());
    }

    public function test_force_from_does_not_apply_when_disabled(): void
    {
        $configurator = $this->configurator([
            'force_from_email' => false,
            'force_from_name' => false,
            'from_email' => 'noreply@example.com',
        ]);
        $email = new Email;

        $configurator->applyMessageSettings($email);

        $this->assertEmpty($email->getFrom());
    }

    public function test_return_path_prefers_use_from_as_return_path(): void
    {
        $configurator = $this->configurator([
            'from_email' => 'noreply@example.com',
            'return_path' => 'bounce@example.com',
            'use_from_as_return_path' => true,
        ]);
        $email = new Email;

        $configurator->applyMessageSettings($email);

        $this->assertSame(
            'noreply@example.com',
            $email->getReturnPath()->getAddress()
        );
    }

    public function test_explicit_return_path_used_when_auto_off(): void
    {
        $configurator = $this->configurator([
            'from_email' => 'noreply@example.com',
            'return_path' => 'bounce@example.com',
            'use_from_as_return_path' => false,
        ]);
        $email = new Email;

        $configurator->applyMessageSettings($email);

        $this->assertSame(
            'bounce@example.com',
            $email->getReturnPath()->getAddress()
        );
    }

    public function test_reply_to_added_when_message_has_none(): void
    {
        $configurator = $this->configurator([
            'reply_to_email' => 'support@example.com',
            'from_name' => 'My App',
        ]);
        $email = new Email;

        $configurator->applyMessageSettings($email);

        $this->assertSame('support@example.com', current($email->getReplyTo())->getAddress());
    }

    public function test_reply_to_not_duplicated_when_message_has_one(): void
    {
        $configurator = $this->configurator([
            'reply_to_email' => 'support@example.com',
        ]);
        $email = (new Email)->replyTo('custom@example.com');

        $configurator->applyMessageSettings($email);

        $this->assertSame(
            ['custom@example.com'],
            array_map(fn ($address) => $address->getAddress(), $email->getReplyTo())
        );
    }
}
