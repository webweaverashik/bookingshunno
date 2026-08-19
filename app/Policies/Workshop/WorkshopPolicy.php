<?php

namespace App\Policies\Workshop;

use App\Models\Auth\User;
use App\Models\Workshop\Workshop;

/**
 * Laravel discovers this automatically (App\Models\Workshop\Workshop -> App\Policies\
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

    /**
     * Nobody, ever — the client's decision, and a sound one.
     *
     * A workshop is referenced by every reservation item that ever quoted it,
     * by the price snapshot on those items, and by every report ranged over a
     * period it ran in. Deleting one does not remove a mistake, it removes the
     * explanation for a row of history: last March's takings stop adding up and
     * nobody can say why. Deactivating takes it off the website and out of the
     * booking form, which is the thing anyone actually wants.
     *
     * Returning false rather than removing the ability: @can('delete') in Blade
     * and Gate::authorize() in the controller both keep working and both say
     * no, so a stale page or an old bookmark gets a clean refusal instead of a
     * 500. The permission row itself stays in the seeder for the same reason —
     * granting it back would not re-enable anything without changing this.
     */
    public function delete(User $user, Workshop $workshop): bool
    {
        return false;
    }
}
