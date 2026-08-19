<?php

namespace App\Http\Controllers\Admin\Setting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Setting\GatewaySettingsRequest;
use App\Http\Requests\Admin\Setting\GeneralSettingsRequest;
use App\Http\Requests\Admin\Setting\MailSettingsRequest;
use App\Http\Requests\Admin\Setting\PaymentSettingsRequest;
use App\Http\Requests\Admin\Setting\ReservationSettingsRequest;
use App\Services\Setting\SettingsRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * PHASE 17 — the settings screen.
 *
 * FIVE TABS, FIVE FORMS, FIVE ENDPOINTS. Not one giant form with one Save.
 * The tabs hold unrelated things — a studio phone number and an SMTP port have
 * nothing to do with each other — and a single form means a validation error on
 * the mail tab blocks saving the phone number, while a single Save button means
 * every save rewrites every row whether it changed or not. Separate endpoints
 * keep each concern independently correct.
 *
 * ---------------------------------------------------------------------------
 * WHAT IS EDITABLE HERE, AND WHAT IS NOT
 * ---------------------------------------------------------------------------
 * Editable: business settings, and the SMTP connection.
 *
 * PHASE 19 changed what that means. The SSLCommerz credentials used to be
 * read-only here and live in .env alone; they are now held in this table, both
 * stores, with a mode setting choosing between them. Every credential on this
 * screen — the SMTP password and both store passwords — is ENCRYPTED WITH
 * APP_KEY before it is written and never sent back to the browser. A leaked
 * database backup is useless without the .env beside it.
 *
 * That is a smaller guarantee than .env-only gave, and it is worth being clear
 * about which part was given up: anyone holding an Admin session can now change
 * where the studio's money goes, where its email comes from, and whether the
 * gateway is transacting for real. The rate limits, the encryption at rest, and
 * the standing warning on the gateway tab are what remains in place of it.
 *
 * Everything here is gated on settings.view / settings.update at the route,
 * both of which are Admin-only in the seeder. A Manager runs the floor; they do
 * not change the booking fee.
 */
class SettingController extends Controller
{
    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    public function index(): View
    {
        return view('admin.settings.index', [
            'values' => $this->settings->all(),

            /*
             | The mail tab shows the EFFECTIVE configuration, not just the rows.
             | On a fresh install the table is empty and .env is doing the work,
             | so a form rendered from the table alone would show blanks beside a
             | mailer that is working perfectly — and the first person to hit
             | Save would wipe it. Reading config() means the form shows what is
             | actually in use, and `mailFromEnv` tells the admin where it came
             | from.
             */
            'mail' => [
                'host'         => $this->settings->get('mail.host') ?: config('mail.mailers.smtp.host'),
                'port'         => $this->settings->get('mail.port') ?: config('mail.mailers.smtp.port'),
                'username'     => $this->settings->get('mail.username') ?: config('mail.mailers.smtp.username'),
                'encryption'   => $this->settings->get('mail.encryption') ?: config('mail.mailers.smtp.scheme'),
                'from_address' => $this->settings->get('mail.from_address') ?: config('mail.from.address'),
                'from_name'    => $this->settings->get('mail.from_name') ?: config('mail.from.name'),
                'has_password' => $this->settings->hasSecret('mail.password')
                    || (bool) config('mail.mailers.smtp.password'),
            ],

            'mailFromEnv' => ! $this->settings->get('mail.host'),
            'mailer'      => config('mail.default'),

            'gateway' => $this->gatewaySnapshot(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Saving
    |--------------------------------------------------------------------------
    | Each of these takes an already-validated payload and hands it to
    | setMany(), which writes in a transaction and flushes the cache once. The
    | type on each key matters: SettingsRepository casts on read, and an integer
    | stored as 'string' comes back as "50" and starts behaving like one in a
    | comparison.
    */

    public function updateGeneral(GeneralSettingsRequest $request): JsonResponse
    {
        $data = $request->validated();

        $this->settings->setMany([
            'studio.name'                 => ['value' => $data['studio_name'], 'type' => 'string'],
            'contact.email'               => ['value' => $data['contact_email'], 'type' => 'string'],
            'contact.phone'               => ['value' => $data['contact_phone'], 'type' => 'string'],
            'contact.whatsapp'            => ['value' => $data['contact_whatsapp'] ?? '', 'type' => 'string'],
            'contact.address'             => ['value' => $data['contact_address'], 'type' => 'string'],
            'notifications.enabled'       => ['value' => $data['notifications_enabled'], 'type' => 'boolean'],

            // Off by default and off again as soon as the job is done — see the
            // note beside the switch in the general settings tab.
            'system.maintenance_console'  => ['value' => $data['maintenance_console'], 'type' => 'boolean'],
        ]);

        return $this->saved('Studio details updated.');
    }

    public function updateReservations(ReservationSettingsRequest $request): JsonResponse
    {
        $data = $request->validated();

        $this->settings->setMany([
            'reservation.max_participants'   => ['value' => $data['max_participants'], 'type' => 'integer'],
            'availability.min_lead_hours'    => ['value' => $data['min_lead_hours'], 'type' => 'integer'],
            'availability.max_advance_days'  => ['value' => $data['max_advance_days'], 'type' => 'integer'],
            'availability.slot_step_minutes' => ['value' => $data['slot_step_minutes'], 'type' => 'integer'],
            'availability.enforce_capacity'  => ['value' => $data['enforce_capacity'], 'type' => 'boolean'],
        ]);

        /*
         | Capacity enforcement ships OFF because every seeded workshop still
         | carries the placeholder capacity of 12 from Phase 4. Turning it on
         | against unconfirmed numbers starts refusing real bookings, so the
         | save succeeds and says so rather than silently letting it happen.
         */
        $warning = $data['enforce_capacity'] && ! $this->settings->get('availability.enforce_capacity')
            ? ' Capacity is now enforced — check every workshop has its real maximum set, not the placeholder.'
            : '';

        return $this->saved('Reservation rules updated.' . $warning);
    }

    public function updatePayments(PaymentSettingsRequest $request): JsonResponse
    {
        $data = $request->validated();

        $this->settings->setMany([
            'booking_fee_percentage'          => ['value' => $data['booking_fee_percentage'], 'type' => 'integer'],
            'payment_deadline_hours'          => ['value' => $data['payment_deadline_hours'], 'type' => 'integer'],
            'payments.online_enabled'         => ['value' => $data['online_enabled'], 'type' => 'boolean'],
            'group_discount.min_participants' => ['value' => $data['discount_min_participants'], 'type' => 'integer'],
            'group_discount.percentage'       => ['value' => $data['discount_percentage'], 'type' => 'integer'],
            'cafe_credit.validity_days'       => ['value' => $data['cafe_credit_validity_days'], 'type' => 'integer'],
        ]);

        /*
         | These figures are read when a payment request is BUILT, not when it
         | is settled. Changing the booking fee does not re-price anything
         | already sent, and staff should know that before they wonder why an
         | outstanding request still says 50%.
         */
        return $this->saved('Payment rules updated. Requests already sent keep the figures they were created with.');
    }

    public function updateMail(MailSettingsRequest $request): JsonResponse
    {
        $data = $request->validated();

        $this->settings->setMany([
            'mail.host'         => ['value' => $data['host'], 'type' => 'string'],
            'mail.port'         => ['value' => $data['port'], 'type' => 'integer'],
            'mail.username'     => ['value' => $data['username'] ?? '', 'type' => 'string'],
            'mail.encryption'   => ['value' => $data['encryption'] ?? '', 'type' => 'string'],
            'mail.from_address' => ['value' => $data['from_address'], 'type' => 'string'],
            'mail.from_name'    => ['value' => $data['from_name'], 'type' => 'string'],
        ]);

        /*
         | The password is written ONLY when a new one was typed.
         |
         | The field renders empty every time, because the stored value is never
         | sent to the browser. If an empty submission were treated as "clear
         | it", then saving this form to correct a typo in the from-name would
         | silently delete the password and stop all mail. Empty means unchanged.
         */
        if (filled($data['password'] ?? null)) {
            $this->settings->setSecret('mail.password', $data['password']);
        }

        return $this->saved('Mail settings saved. Send a test email to confirm they work.');
    }

    /*
    |--------------------------------------------------------------------------
    | Test email
    |--------------------------------------------------------------------------
    */

    /**
     * Prove the SMTP settings actually work, by sending somewhere real.
     *
     * PHASE 19 — the recipient is now supplied by the form rather than fixed to
     * the signed-in Admin, at your request. Sensible: the address you need to
     * test against is often a Gmail account or the client's own inbox, not the
     * one you happen to be signed in as.
     *
     * It does mean this endpoint sends attacker-influenced mail to an
     * attacker-chosen address, so three things hold it in place:
     *
     *   Admin session plus settings.update — the smallest group of people in
     *   the system, and the same people who could change the SMTP server
     *   outright if they wanted to abuse it.
     *
     *   The 'test-email' limiter, keyed on the USER: two a minute, ten an hour.
     *   Not enough to be worth anyone's time as a relay.
     *
     *   THE BODY IS FIXED. Nothing from the request reaches the message. This
     *   is the part that matters — a caller who could choose both the recipient
     *   and the text would have a genuine open relay, and the address alone is
     *   worth very little without it.
     *
     * Every send is logged with who asked for it, so a misuse leaves a trail.
     *
     * This replaces the GET /send-test-email route in routes/web.php, which was
     * unauthenticated, unthrottled, and had a personal Gmail address hardcoded.
     */
    public function testMail(Request $request): JsonResponse
    {
        $validated = $request->validate(
            ['email' => ['required', 'email', 'max:255']],
            ['email.required' => 'Where should the test go?', 'email.email' => 'That is not a valid email address.'],
        );

        $recipient = $validated['email'];

        Log::info('Test email requested', [
            'by'        => $request->user()->email,
            'recipient' => $recipient,
        ]);

        try {
            Mail::raw(
                "This is a test message from the Shunno Art Cafe booking system.\n\n"
                    . 'Sent at ' . now()->format('j F Y, g:i A') . " (Dhaka time).\n"
                    . 'Mailer: ' . config('mail.default') . ' via ' . config('mail.mailers.smtp.host') . "\n\n"
                    . 'If you are reading this, outgoing email is working.',
                fn ($message) => $message->to($recipient)->subject('Shunno booking system — test email'),
            );
        } catch (TransportExceptionInterface $e) {
            Log::error('Settings test email failed: ' . $e->getMessage());

            /*
             | The transport message is shown deliberately. "Could not send" is
             | useless to whoever has to fix it, and the reason is almost always
             | the thing they got wrong two fields up — a wrong port, a refused
             | password, an unknown host. It reaches only a signed-in Admin.
             */
            return response()->json([
                'success' => false,
                'message' => 'Could not send: ' . $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => "Test email sent to {$recipient}. If it does not arrive, check the spam folder before changing anything.",
        ]);
    }

    public function updateGateway(GatewaySettingsRequest $request): JsonResponse
    {
        $data = $request->validated();

        $this->settings->setMany([
            'sslcommerz.mode'             => ['value' => $data['mode'], 'type' => 'string'],
            'sslcommerz.sandbox_store_id' => ['value' => $data['sandbox_store_id'] ?? '', 'type' => 'string'],
            'sslcommerz.live_store_id'    => ['value' => $data['live_store_id'] ?? '', 'type' => 'string'],
        ]);

        /*
         | Store passwords, encrypted, and only when a new one was typed.
         |
         | Same rule as the SMTP password and for the same reason: the stored
         | value is never sent to the browser, so the field is always empty when
         | the form loads. Treating empty as "clear it" would mean that opening
         | this tab to correct a store ID silently destroys the password and
         | stops every online payment — with no error, until a visitor tries to
         | pay.
         */
        foreach (['sandbox', 'live'] as $mode) {
            if (filled($data["{$mode}_store_password"] ?? null)) {
                $this->settings->setSecret("sslcommerz.{$mode}_store_password", $data["{$mode}_store_password"]);
            }
        }

        return $this->saved(
            $data['mode'] === 'live'
                ? 'Saved. The gateway is LIVE — transactions from now on move real money.'
                : 'Saved. The gateway is in sandbox mode, so nothing will actually be charged.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Gateway
    |--------------------------------------------------------------------------
    */

    /**
     * Everything the SSLCommerz tab shows.
     *
     * Store IDs come back in full because they go in a form the Admin is about
     * to edit — masking a field somebody has to retype is theatre. The
     * PASSWORDS are never read here, only reported as present or absent.
     *
     * `configured` describes the ACTIVE mode specifically, not whether any
     * credentials exist anywhere. A live store fully set up while the mode is
     * sandbox means online payment is running on sandbox credentials, and the
     * banner should say so rather than reporting a green light because some
     * other store is complete.
     */
    private function gatewaySnapshot(): array
    {
        $mode    = $this->settings->get('sslcommerz.mode', 'sandbox');
        $sandbox = $mode !== 'live';

        return [
            'mode'         => $mode,

            'sandbox_store_id'   => (string) ($this->settings->get('sslcommerz.sandbox_store_id') ?: ''),
            'live_store_id'      => (string) ($this->settings->get('sslcommerz.live_store_id') ?: ''),
            'sandbox_has_secret' => $this->settings->hasSecret('sslcommerz.sandbox_store_password'),
            'live_has_secret'    => $this->settings->hasSecret('sslcommerz.live_store_password'),

            // Read through config, so this reflects what SslCommerzService will
            // actually use after RuntimeConfigServiceProvider has resolved the
            // mode — including the .env fallback on an install where nothing has
            // been saved yet.
            'configured'   => (bool) config('services.sslcommerz.store_id')
                && (bool) config('services.sslcommerz.store_password'),

            'sandbox'      => $sandbox,

            /*
             | The mismatch that will bite on go-live, surfaced rather than left
             | in a document. SSLCommerz validates that requests arrive from the
             | domain the store is registered to, and this store is registered to
             | the parent domain while the application transacts from the booking
             | subdomain. Nothing in the code can detect the registered value —
             | so the URLs are shown and the check is handed to a person.
             */
            'app_host'     => parse_url((string) config('app.url'), PHP_URL_HOST),

            'urls' => [
                'ipn'     => route('payment.gateway.ipn'),
                'success' => route('payment.gateway.success'),
                'fail'    => route('payment.gateway.fail'),
                'cancel'  => route('payment.gateway.cancel'),
            ],
        ];
    }

    private function saved(string $message): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message]);
    }
}
