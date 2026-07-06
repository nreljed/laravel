<?php

namespace App\Modules\Contracts;

use Carbon\Carbon;
use Illuminate\Support\Collection;

interface Bookable
{
    public function getId(): int;

    public function getName(): string;

    public function getPrice(): float;

    public function getDescription(): string;

    public function getMaxGuests(): int;

    public function isAvailable(Carbon $from, Carbon $to, ?int $excludeBookingId = null): bool;

    public function getAvailabilityData(Carbon $from, Carbon $to): array;

    public function getImages(): Collection;

    public function getMeta(string $key, mixed $default = null): mixed;

    public function getProviderSlug(): string;

    public function getCurrency(): string;

    public function getCleaningFee(): float;

    public function getDamageDeposit(): float;

    public function getOwnerId(): ?int;

    public function getFeaturedImageUrl(): ?string;
}
