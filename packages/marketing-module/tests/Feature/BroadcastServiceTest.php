<?php

namespace Nreljed\MarketingModule\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Nreljed\MarketingModule\Enums\BroadcastChannel;
use Nreljed\MarketingModule\Enums\BroadcastStatus;
use Nreljed\MarketingModule\Models\Contact;
use Nreljed\MarketingModule\Models\MarketingBroadcast;
use Nreljed\MarketingModule\Models\MarketingBroadcastRecipient;
use Nreljed\MarketingModule\Services\BroadcastService;
use Nreljed\MarketingModule\Services\LogEmailChannel;
use Nreljed\MarketingModule\Services\LogSmsChannel;
use Nreljed\MarketingModule\Services\LogWhatsAppChannel;
use Nreljed\MarketingModule\Tests\Concerns\BootsDatabase;
use PHPUnit\Framework\TestCase;

final class BroadcastServiceTest extends TestCase
{
    use BootsDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootDatabase();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-23 12:00:00'));
        DB::table('users')->insert(['id' => 1, 'name' => 'Author', 'email' => 'author@example.test']);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_it_sends_an_sms_broadcast_to_consenting_contacts(): void
    {
        $contact = Contact::create([
            'first_name' => 'Sam',
            'phone' => '+15551234567',
            'sms_consent_at' => now(),
        ]);

        $broadcast = MarketingBroadcast::create([
            'name' => 'SMS test',
            'channel' => BroadcastChannel::Sms->value,
            'body_text' => 'Hello from SMS',
            'status' => BroadcastStatus::Draft->value,
        ]);

        (new BroadcastService(new LogEmailChannel, new LogSmsChannel, new LogWhatsAppChannel))->send($broadcast);

        $broadcast->refresh();

        $this->assertSame('sent', $broadcast->status->value);
        $this->assertSame(1, $broadcast->recipient_count);
        $this->assertSame(1, $broadcast->fresh()->delivered_count);
        $this->assertSame('sent', MarketingBroadcastRecipient::query()->first()->status);
        $this->assertSame(1, Contact::query()->first()->last_contacted_at !== null ? 1 : 0);
    }
}
