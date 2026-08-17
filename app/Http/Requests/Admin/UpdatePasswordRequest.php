<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * PHASE 19 — the current-password requirement has been REMOVED, at your
 * instruction. Worth recording what that costs, once, so it is a decision on
 * the record rather than a gap somebody finds later.
 *
 * An unattended admin session is now enough to change the password and lock the
 * real owner out. The login OTP does not help: the session is already
 * authenticated and never needs the password again. Two things soften it — the
 * change signs out every other session, and the 'password-change' limiter caps
 * attempts — but neither addresses the unattended browser, which is the case
 * the check was for.
 *
 * It is a defensible trade and plenty of products make it. Restoring it is one
 * line: put 'current_password' => ['required', 'current_password'] back in the
 * rules and the field back in the form.
 *
 * NOTE THAT THE EMAIL CHANGE ON THE PROFILE FORM STILL ASKS FOR IT, and that is
 * not an oversight. The two are not equivalent. A password taken from you can
 * be recovered — the reset link goes to your address, which is still yours. An
 * ADDRESS taken from you cannot: the reset link goes to the attacker, and the
 * account is gone for good. Keeping the check on the one that is irreversible,
 * and dropping it on the one that is not, is a coherent place to draw the line.
 */
class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            /*
             | min(8) + uncompromised(). The second is the one that earns its
             | place: it checks the candidate against Have I Been Pwned's
             | breached-password corpus over a k-anonymity range query, so only
             | the first five characters of the hash ever leave the server and
             | the password itself never does.
             |
             | Composition rules — a symbol, a digit, a capital — are not used.
             | They push people towards Password1! and its cousins, all of which
             | are in that corpus. Length plus "has this one already leaked" is
             | the check that actually correlates with a password being hard to
             | guess.
             |
             | If the studio's hosting blocks outbound HTTPS the check fails
             | OPEN — Laravel treats an unreachable API as a pass — so this
             | cannot lock anybody out of changing their password.
             */
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->uncompromised(),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'password.confirmed' => 'The two new passwords do not match.',
            'password.required'  => 'Enter a new password.',
        ];
    }
}
