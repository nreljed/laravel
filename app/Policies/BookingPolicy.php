<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Booking\Models\Booking;
use Illuminate\Auth\Access\HandlesAuthorization;

class BookingPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('bookings.view');
    }

    public function view(User $user, Booking $booking): bool
    {
        return $user->id === $booking->user_id || $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('bookings.create');
    }

    public function update(User $user, Booking $booking): bool
    {
        return $user->id === $booking->user_id || $user->isAdmin();
    }

    public function delete(User $user, Booking $booking): bool
    {
        return $user->id === $booking->user_id || $user->isAdmin();
    }

    public function transition(User $user, Booking $booking): bool
    {
        return $user->isAdmin();
    }
}
