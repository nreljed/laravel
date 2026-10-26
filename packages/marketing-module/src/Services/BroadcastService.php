<?php

namespace Nreljed\MarketingModule\Services;

use Illuminate\Support\Facades\URL;
use Nreljed\MarketingModule\Contracts\EmailChannel;
use Nreljed\MarketingModule\Contracts\SmsChannel;
use Nreljed\MarketingModule\Contracts\WhatsAppChannel;
use Nreljed\MarketingModule\Enums\BroadcastChannel;
use Nreljed\MarketingModule\Models\Contact;
use Nreljed\MarketingModule\Models\MarketingBroadcast;
use Nreljed\MarketingModule\Models\MarketingBroadcastRecipient;

class BroadcastService
{
    public function __construct(
        private readonly EmailChannel $email,
        private readonly SmsChannel $sms,
        private readonly WhatsAppChannel $whatsapp,
    ) {}

    public function send(MarketingBroadcast $broadcast): void
    {
        if ($broadcast->status?->value === 'sent') {
            return;
        }

        $broadcast->forceFill(['status' => 'sending'])->save();

        $contacts = Contact::query();

        if ($broadcast->segment_id !== null) {
            $contacts->whereHas('segments', static fn ($query) => $query->where('segments.id', $broadcast->segment_id));
        }

        if ($broadcast->tag_id !== null) {
            $contacts->whereHas('tags', static fn ($query) => $query->where('tags.id', $broadcast->tag_id));
        }

        $channel = $broadcast->channel ?? BroadcastChannel::Email;

        $recipients = $contacts->get()->filter(static function (Contact $contact) use ($channel): bool {
            return match ($channel) {
                BroadcastChannel::Sms => $contact->canReceiveSms(),
                BroadcastChannel::WhatsApp => $contact->canReceiveWhatsApp(),
                default => $contact->canReceiveEmail(),
            };
        });

        $broadcast->forceFill(['recipient_count' => $recipients->count()])->save();

        $delivered = 0;
        $failed = 0;
        $message = $this->messageFor($broadcast);

        foreach ($recipients as $contact) {
            $recipient = MarketingBroadcastRecipient::query()->firstOrCreate(
                [
                    'marketing_broadcast_id' => $broadcast->id,
                    'contact_id' => $contact->id,
                ],
                ['status' => 'pending'],
            );

            try {
                match ($channel) {
                    BroadcastChannel::Sms => $this->sms->send((string) $contact->phone, $message),
                    BroadcastChannel::WhatsApp => $this->whatsapp->send((string) $contact->phone, $message, [
                        'broadcast_id' => $broadcast->id,
                    ]),
                    default => $this->email->send(
                        (string) $contact->email,
                        (string) $broadcast->subject,
                        (string) $broadcast->body_html,
                        ['List-Unsubscribe' => route('marketing.unsubscribe', ['contact' => $contact->id])],
                    ),
                };

                $recipient->forceFill(['status' => 'sent', 'sent_at' => now()])->save();
                $contact->forceFill(['last_contacted_at' => now()])->save();
                $delivered++;
            } catch (\Throwable $e) {
                $recipient->forceFill(['status' => 'failed', 'error' => $e->getMessage()])->save();
                $failed++;
            }
        }

        $broadcast->forceFill([
            'status' => 'sent',
            'sent_at' => now(),
            'delivered_count' => $delivered,
            'failed_count' => $failed,
        ])->save();
    }

    public function unsubscribeUrl(Contact $contact): string
    {
        return URL::signedRoute('marketing.unsubscribe', ['contact' => $contact->id]);
    }

    private function messageFor(MarketingBroadcast $broadcast): string
    {
        $text = trim((string) ($broadcast->body_text ?? ''));

        if ($text !== '') {
            return $text;
        }

        return trim(strip_tags((string) $broadcast->body_html));
    }
}
