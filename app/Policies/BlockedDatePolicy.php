<?php

namespace App\Policies;

use App\Models\Auth\User;
use App\Models\BlockedDate;

/**
 * Also carries the gate for the operating-hours and booking-rules cards, which
 * have no model of their own. Hanging them here keeps every availability
 * authorisation decision in one file rather than scattering raw permission
 * strings through the controller.
 *
 * Manager holds availability.view only, so the page renders read-only for that
 * role — deliberate, per RolePermissionSeeder: date and capacity configuration
 * is Admin work.
 */
class BlockedDatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('availability.view');
    }

    public function view(User $user, BlockedDate $blockedDate): bool
    {
        return $user->can('availability.view');
    }

    public function create(User $user): bool
    {
        return $user->can('availability.block-date');
    }

    public function update(User $user, BlockedDate $blockedDate): bool
    {
        return $user->can('availability.block-date');
    }

    public function delete(User $user, BlockedDate $blockedDate): bool
    {
        return $user->can('availability.block-date');
    }

    /** Operating hours and booking rules. */
    public function manageAvailability(User $user): bool
    {
        return $user->can('availability.update');
    }
}
