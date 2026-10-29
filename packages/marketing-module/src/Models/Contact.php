<?php

namespace Nreljed\MarketingModule\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Nreljed\MarketingModule\Database\Factories\ContactFactory;
use Nreljed\MarketingModule\Enums\ContactStatus;

/**
 * An audience member.
 *
 * Deliberately thin: a contact exists so automations have something to
 * address. Custom fields, notes, ownership and activity history are a CRM,
 * and a CRM is a different product with a different cost.
 */
class Contact extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'user_id',
        'first_name',
        'last_name',
        'phone',
        'status',
        'source',
        'metadata',
        'last_contacted_at',
        'email_consent_at',
        'sms_consent_at',
        'whatsapp_consent_at',
        'unsubscribed_at',
        'sms_opted_out_at',
        'whatsapp_opted_out_at',
        'suppressed_at',
        'bounce_count',
        'last_bounced_at',
        'total_spent',
        'order_count',
        'last_order_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'last_contacted_at' => 'datetime',
        'email_consent_at' => 'datetime',
        'sms_consent_at' => 'datetime',
        'whatsapp_consent_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
        'sms_opted_out_at' => 'datetime',
        'whatsapp_opted_out_at' => 'datetime',
        'suppressed_at' => 'datetime',
        'bounce_count' => 'integer',
        'last_bounced_at' => 'datetime',
        'total_spent' => 'decimal:2',
        'order_count' => 'integer',
        'last_order_at' => 'datetime',
        'status' => ContactStatus::class,
    ];

    protected static function newFactory(): Factory
    {
        return ContactFactory::new();
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'contact_tags');
    }

    public function segments(): BelongsToMany
    {
        return $this->belongsToMany(Segment::class, 'segment_memberships');
    }

    /**
     * The contact's display name, falling back to the email address.
     */
    public function displayName(): string
    {
        $name = trim(($this->first_name ?? '').' '.($this->last_name ?? ''));

        return $name !== '' ? $name : (string) ($this->email ?? '');
    }

    public function canReceiveEmail(): bool
    {
        return $this->email !== null
            && $this->email !== ''
            && $this->unsubscribed_at === null
            && $this->suppressed_at === null;
    }

    public function canReceiveSms(): bool
    {
        return $this->phone !== null
            && $this->phone !== ''
            && $this->sms_consent_at !== null
            && $this->sms_opted_out_at === null
            && $this->suppressed_at === null;
    }

    public function canReceiveWhatsApp(): bool
    {
        return $this->phone !== null
            && $this->phone !== ''
            && $this->whatsapp_consent_at !== null
            && $this->whatsapp_opted_out_at === null
            && $this->suppressed_at === null;
    }

    public function unsubscribe(): void
    {
        $this->forceFill(['unsubscribed_at' => now()])->save();
    }

    public function suppress(): void
    {
        $this->forceFill(['suppressed_at' => now()])->save();
    }

    public function recordBounce(): void
    {
        $this->forceFill([
            'bounce_count' => $this->bounce_count + 1,
            'last_bounced_at' => now(),
        ])->save();

        if ($this->bounce_count >= 3) {
            $this->suppress();
        }
    }
}
