<?php

namespace Nreljed\BookingModule\Booking\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Nreljed\BookingModule\Booking\Models\Booking;
use Nreljed\Platform\Models\User;

class BookingPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('bookings.view');
    }

    public function view(User $user, Booking $booking): bool
    {
        return $this->isOwnerOrAdmin($user, $booking);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('bookings.create');
    }

    public function update(User $user, Booking $booking): bool
    {
        return $this->isOwnerOrAdmin($user, $booking);
    }

    public function delete(User $user, Booking $booking): bool
    {
        return $this->isOwnerOrAdmin($user, $booking);
    }

    public function transition(User $user, Booking $booking): bool
    {
        return $user->isAdmin();
    }

    protected function isOwnerOrAdmin(User $user, Booking $booking): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->id === $booking->user_id) {
            return true;
        }

        return $booking->guest_email !== null
            && $booking->guest_email !== ''
            && strtolower($booking->guest_email) === strtolower($user->email);
    }
}
