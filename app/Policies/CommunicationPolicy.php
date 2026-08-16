<?php

namespace App\Policies;

use App\Models\Auth\User;
use App\Models\Communication;

/**
 * PHASE 13B.
 *
 * Resending is granted to Manager as well as Admin, on the same reasoning that
 * gave Manager offline payment recording in 12C: repeating a message the
 * visitor was already sent is not a decision about the studio's business. The
 * content is fixed, the recipient is fixed, and nothing about the reservation
 * changes. Whoever is on the phone to a visitor saying "I never got it" is the
 * right person to fix it.
 *
 * The limits that matter are elsewhere and are not about who: internal mail
 * cannot be resent at all, and a resend is throttled per original message so a
 * frustrated click cannot become five emails. Both live on the model, because
 * they are true regardless of who is asking.
 */
class CommunicationPolicy
{
    public function view(User $user, Communication $communication): bool
    {
        return $user->can('reservations.view');
    }

    public function resend(User $user, Communication $communication): bool
    {
        return $user->can('communications.resend')
            && $communication->isResendable();
    }
}
