<?php

namespace Nreljed\MarketingModule\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nreljed\MarketingModule\Enums\ContactStatus;
use Nreljed\MarketingModule\Models\Contact;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    protected $model = Contact::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => fake()->numerify('+1##########'),
            'status' => ContactStatus::Lead,
            'source' => 'manual',
            'metadata' => [],
            'last_contacted_at' => null,
        ];
    }

    /**
     * A contact that has opted in.
     */
    public function subscribed(): static
    {
        return $this->state(fn (): array => [
            'status' => ContactStatus::Subscribed,
        ]);
    }
}
