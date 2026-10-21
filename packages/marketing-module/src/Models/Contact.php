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
        'first_name',
        'last_name',
        'phone',
        'status',
        'source',
        'metadata',
        'last_contacted_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'last_contacted_at' => 'datetime',
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
}
