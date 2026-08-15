<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentTransaction;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * PHASE 13 — everything that talks to SSLCommerz.
 *
 * Two jobs, and the second is the important one:
 *
 *   initiate()  open a session and get the URL to send the visitor to
 *   validate()  ask SSLCommerz, server to server, whether a payment is real
 *
 * §11 of the brief: a payment is NEVER successful because the visitor reached
 * the success URL. That URL is a redirect in the visitor's own browser and can
 * be typed by hand, replayed, or shared. The only thing that settles money is
 * validate() answering VALID for an amount and a tran_id that match what we
 * asked for. Every guard in here exists because leaving it out is a way to give
 * away workshops for free.
 *
 * Credentials come from config/services.php, which reads .env. They are never
 * in the database and never in the admin panel — see the note there.
 */
class SslCommerzService
{
    /**
     * Deliberately not a config value.
     *
     * Sandbox versus live is decided by which credentials are loaded, and
     * loading live credentials while pointing at the sandbox host — or the
     * reverse — produces failures that look like a broken integration rather
     * than a misconfiguration. Tying the host to the same switch removes the
     * possibility.
     */
    private const HOSTS = [
        true  => 'https://sandbox.sslcommerz.com',
        false => 'https://securepay.sslcommerz.com',
    ];

    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    /*
    |--------------------------------------------------------------------------
    | Availability
    |--------------------------------------------------------------------------
    */

    /**
     * Whether online payment can be offered at all right now.
     *
     * Two separate questions, and both have to be yes. Credentials missing is a
     * deployment fact; the settings toggle is an operational one — the studio
     * turns it off when the gateway is having a bad day and takes payment by
     * hand instead, without waiting for a deploy.
     */
    public function isAvailable(): bool
    {
        return $this->isEnabled() && $this->isConfigured();
    }

    public function isEnabled(): bool
    {
        return (bool) $this->settings->get('payments.online_enabled', true);
    }

    public function isConfigured(): bool
    {
        return filled($this->storeId()) && filled($this->storePassword());
    }

    /*
    |--------------------------------------------------------------------------
    | Starting a payment
    |--------------------------------------------------------------------------
    */

    /**
     * Open a checkout session and return the URL to redirect the visitor to.
     *
     * @throws RuntimeException when SSLCommerz refuses, or cannot be reached.
     */
    public function initiate(Payment $payment, PaymentTransaction $attempt, float $amount): string
    {
        $reservation = $payment->reservation;
        $visitor     = $reservation?->user;

        $payload = [
            'store_id'     => $this->storeId(),
            'store_passwd' => $this->storePassword(),

            // Two decimal places as a string. SSLCommerz compares this against
            // what it later reports back, and a float rendered as 1500 rather
            // than 1500.00 has been known to fail that comparison.
            'total_amount' => number_format($amount, 2, '.', ''),
            'currency'     => 'BDT',

            /*
             | The attempt's own reference IS the tran_id.
             |
             | This is what makes the whole callback story safe. It is unique,
             | it is ours, it exists in the database before the visitor leaves,
             | and every callback carries it back — so a callback can always be
             | tied to exactly one attempt, and a second callback for the same
             | attempt is recognisably a repeat rather than a second payment.
             */
            'tran_id' => $attempt->reference,

            'success_url' => route('payment.gateway.success'),
            'fail_url'    => route('payment.gateway.fail'),
            'cancel_url'  => route('payment.gateway.cancel'),
            'ipn_url'     => route('payment.gateway.ipn'),

            'cus_name'    => $visitor?->name ?: 'Visitor',
            'cus_email'   => $visitor?->email ?: config('shunno.contact.email'),
            'cus_phone'   => $visitor?->phone ?: config('shunno.contact.phone'),
            'cus_add1'    => config('shunno.contact.address'),
            'cus_city'    => 'Dhaka',
            'cus_country' => 'Bangladesh',

            // Nothing is posted to anybody. Saying so up front stops SSLCommerz
            // demanding a shipping address it would otherwise require.
            'shipping_method' => 'NO',
            'num_of_item'     => 1,

            'product_name'     => $reservation?->title() ?: 'Studio visit',
            'product_category' => 'Workshop',
            'product_profile'  => 'general',

            // Comes back untouched in the callback. A cheap second way to tie a
            // response to a reservation when reading raw logs.
            'value_a' => $payment->reference,
            'value_b' => $reservation?->reference_code,
        ];

        try {
            $response = Http::asForm()
                ->timeout(30)
                ->post($this->host() . '/gwprocess/v4/api.php', $payload);
        } catch (ConnectionException $e) {
            Log::error('SSLCommerz unreachable while opening a session.', [
                'payment' => $payment->reference,
                'attempt' => $attempt->reference,
                'error'   => $e->getMessage(),
            ]);

            throw new RuntimeException(
                'We could not reach the payment gateway. Please try again in a moment.'
            );
        }

        $body = $response->json() ?? [];

        // failedreason is SSLCommerz's own spelling.
        if (($body['status'] ?? null) !== 'SUCCESS' || blank($body['GatewayPageURL'] ?? null)) {
            Log::error('SSLCommerz refused to open a session.', [
                'payment' => $payment->reference,
                'attempt' => $attempt->reference,
                'status'  => $body['status'] ?? null,
                'reason'  => $body['failedreason'] ?? null,
            ]);

            throw new RuntimeException(
                'The payment gateway would not start a session. Please try again, or contact the studio.'
            );
        }

        return $body['GatewayPageURL'];
    }

    /*
    |--------------------------------------------------------------------------
    | Verifying a payment
    |--------------------------------------------------------------------------
    */

    /**
     * Ask SSLCommerz whether a payment really happened, and whether it matches.
     *
     * Returns the validation payload on success, or null on any failure at all.
     * A null answer must be treated as "not paid" — never as "probably fine".
     *
     * @param  string  $valId  From the callback. Untrusted until this returns.
     */
    public function validate(string $valId, PaymentTransaction $attempt, float $expected): ?array
    {
        try {
            $response = Http::timeout(30)->get(
                $this->host() . '/validator/api/validationserverAPI.php',
                [
                    'val_id'       => $valId,
                    'store_id'     => $this->storeId(),
                    'store_passwd' => $this->storePassword(),
                    'format'       => 'json',
                    'v'            => 1,
                ],
            );
        } catch (ConnectionException $e) {
            Log::error('SSLCommerz unreachable during validation.', [
                'attempt' => $attempt->reference,
                'val_id'  => $valId,
                'error'   => $e->getMessage(),
            ]);

            return null;
        }

        $body = $response->json() ?? [];

        /*
         | Four checks, and all four have to pass.
         |
         | 1. STATUS. VALID means settled; VALIDATED means settled and already
         |    acknowledged by us before. Both are money. Anything else — FAILED,
         |    PENDING, INVALID_TRANSACTION — is not.
         |
         | 2. TRAN_ID. Ties the answer to the attempt we asked about. Without
         |    it, a val_id from somebody else's genuinely paid transaction would
         |    validate happily against this reservation.
         |
         | 3. AMOUNT. Compared in poisha, not as floats, and against what we
         |    ASKED for rather than what the response volunteers. This is the
         |    check that stops a tampered redirect settling a 3,000 taka
         |    workshop with a 10 taka payment.
         |
         | 4. CURRENCY. BDT or nothing.
         */
        $status = strtoupper((string) ($body['status'] ?? ''));

        if (! in_array($status, ['VALID', 'VALIDATED'], true)) {
            Log::warning('SSLCommerz validation returned a non-paid status.', [
                'attempt' => $attempt->reference,
                'val_id'  => $valId,
                'status'  => $status,
                'reason'  => $body['error'] ?? null,
            ]);

            return null;
        }

        if (($body['tran_id'] ?? null) !== $attempt->reference) {
            Log::critical('SSLCommerz validation was for a different transaction.', [
                'attempt'  => $attempt->reference,
                'returned' => $body['tran_id'] ?? null,
                'val_id'   => $valId,
            ]);

            return null;
        }

        $paidPoisha     = (int) round(((float) ($body['amount'] ?? 0)) * 100);
        $expectedPoisha = (int) round($expected * 100);

        if ($paidPoisha !== $expectedPoisha) {
            Log::critical('SSLCommerz validation amount did not match.', [
                'attempt'  => $attempt->reference,
                'expected' => $expected,
                'returned' => $body['amount'] ?? null,
            ]);

            return null;
        }

        if (strtoupper((string) ($body['currency'] ?? 'BDT')) !== 'BDT') {
            Log::critical('SSLCommerz validation returned a foreign currency.', [
                'attempt'  => $attempt->reference,
                'currency' => $body['currency'] ?? null,
            ]);

            return null;
        }

        return $body;
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    private function sandbox(): bool
    {
        return (bool) config('services.sslcommerz.sandbox', true);
    }

    private function host(): string
    {
        return self::HOSTS[$this->sandbox()];
    }

    private function storeId(): ?string
    {
        return config('services.sslcommerz.store_id');
    }

    private function storePassword(): ?string
    {
        return config('services.sslcommerz.store_password');
    }
}
