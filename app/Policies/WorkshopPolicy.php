<?php

namespace App\Policies;

use App\Models\Auth\User;
use App\Models\Workshop;

/**
 * Laravel discovers this automatically (App\Models\Workshop -> App\Policies\
 * WorkshopPolicy), so no provider registration is needed.
 *
 * The permission names are the ones RolePermissionSeeder already creates.
 * Manager holds only workshops.view, which is why the admin table renders
 * read-only for that role rather than hiding itself entirely.
 */
class WorkshopPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('workshops.view');
    }

    public function view(User $user, Workshop $workshop): bool
    {
        return $user->can('workshops.view');
    }

    public function create(User $user): bool
    {
        return $user->can('workshops.create');
    }

    public function update(User $user, Workshop $workshop): bool
    {
        return $user->can('workshops.update');
    }

    public function delete(User $user, Workshop $workshop): bool
    {
        return $user->can('workshops.delete');
    }
}
