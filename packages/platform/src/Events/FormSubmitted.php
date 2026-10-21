<?php

namespace Nreljed\Platform\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Nreljed\Platform\Models\FormSubmission;

/**
 * A public form submission was stored.
 *
 * The forms engine owns persistence, deduplication and the admin notification
 * fan-out; this event is the seam for everything the engine cannot know about.
 * A feature that wants a submission to become a contact, a lead or a segment
 * member listens for it instead of wrapping the controller:
 *
 *     Event::listen(FormSubmitted::class, function (FormSubmitted $event) {
 *         Contact::firstOrCreate(['email' => $event->payload['email']], ...);
 *     });
 *
 * Only stored submissions are announced. A honeypot hit or a deduplicated
 * replay never reaches a listener — a bot cannot be handed a signal that its
 * submission was rejected.
 */
class FormSubmitted
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload  The validated, trimmed field payload.
     */
    public function __construct(
        public readonly string $formKey,
        public readonly FormSubmission $submission,
        public readonly array $payload = [],
    ) {}
}
