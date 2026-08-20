<?php

namespace Database\Seeders;

use App\Models\Setting\Setting;
use App\Services\Setting\SettingsRepository;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'group_discount.min_participants', 'value' => '4',  'type' => 'integer', 'group' => 'pricing', 'label' => 'Group discount applies from'],
            ['key' => 'group_discount.percentage',       'value' => '10', 'type' => 'integer', 'group' => 'pricing', 'label' => 'Group discount (%)'],
            ['key' => 'booking_fee_percentage',          'value' => '50', 'type' => 'integer', 'group' => 'payment', 'label' => 'Booking fee (%)'],
            ['key' => 'payment_deadline_hours',          'value' => '48', 'type' => 'integer', 'group' => 'payment', 'label' => 'Hours to pay after approval'],

            /*
             | PHASE 13 — the operational switch for online payment.
             |
             | Separate from the SSLCommerz credentials, which live in .env and
             | only in .env. This one answers "should we be offering online
             | payment right now" — a question the studio needs to answer on a
             | bad afternoon without waiting for a deploy. Turning it off hides
             | the Pay online button; requests, deadlines and offline recording
             | all carry on.
             */
            ['key' => 'payments.online_enabled',         'value' => '1',  'type' => 'boolean', 'group' => 'payment', 'label' => 'Accept payment online'],

            /*
             | PHASE 14A — how long café credit lasts, counted from the VISIT
             | date rather than from issue. Credit is issued when payment lands,
             | which can be weeks before the day; counting from then would hand
             | somebody a coupon that expired before they had been.
             */
            ['key' => 'cafe_credit.validity_days',      'value' => '30', 'type' => 'integer', 'group' => 'voucher', 'label' => 'Café credit valid for (days after visit)'],
            /*
             | PHASE 14A superseded two keys that used to sit here —
             | cafe_credit.entry_fee_coupon and cafe_credit.per_participant.
             |
             | Both were placeholders from Phase 4, written before the client
             | had decided anything. The amount is now workshops.cafe_credit_per_person,
             | which lets the studio set a different figure per experience and
             | add a second credit-earning visit type without a deploy; and
             | per-participant is no longer a question, because the client
             | settled it — the value is always the per-head figure times the
             | party size, issued as ONE coupon.
             |
             | They are left out rather than set to 0. A seeder that keeps
             | writing a key nothing reads is how a future contributor spends an
             | afternoon working out which of two settings is the real one.
             |
             | Existing installs keep the orphan rows until they are deleted by
             | hand; nothing reads them, so they are inert. See the phase notes.
             */
            ['key' => 'reservation.max_participants',    'value' => '30', 'type' => 'integer', 'group' => 'general', 'label' => 'Largest group accepted online'],

            /*
             | PHASE 7A — availability
             |
             | enforce_capacity ships OFF. max_participants on every seeded
             | workshop is the placeholder 12 from Phase 4; enforcing against an
             | unconfirmed number would start refusing real bookings. Once the
             | client confirms the per-session capacities and they are entered
             | in the workshop admin, set this to 1 and the whole capacity
             | pathway activates with no code change.
             */
            ['key' => 'availability.enforce_capacity',   'value' => '0',   'type' => 'boolean', 'group' => 'availability', 'label' => 'Enforce per-session capacity'],
            ['key' => 'availability.min_lead_hours',     'value' => '12',  'type' => 'integer', 'group' => 'availability', 'label' => 'Minimum notice (hours)'],
            ['key' => 'availability.max_advance_days',   'value' => '14', 'type' => 'integer', 'group' => 'availability', 'label' => 'How far ahead visitors may book (days)'],
            ['key' => 'availability.slot_step_minutes',  'value' => '30',  'type' => 'integer', 'group' => 'availability', 'label' => 'Start times every (minutes)'],

            /*
             | PHASE 11 — notifications
             |
             | Ships ON, because a reservation system that silently tells nobody
             | anything is worse than one that occasionally sends a clumsy email.
             |
             | Turn it OFF for two real situations: a production database
             | restored into staging, where sending is actively harmful, and the
             | first days of go-live if the client wants the workflow running
             | while the wording is still being agreed. It silences EVERY
             | outbound reservation email, including the ones to staff.
             */
            ['key' => 'notifications.enabled',           'value' => '1',   'type' => 'boolean', 'group' => 'notifications', 'label' => 'Send reservation emails'],

            // Ships OFF. The console runs artisan against the live database, so
            // it is switched on for the hour it is needed rather than left
            // standing open because it helped once.
            ['key' => 'system.maintenance_console',      'value' => '0',   'type' => 'boolean', 'group' => 'general', 'label' => 'Maintenance console'],

            /*
             | PHASE 17 — studio identity and contact, moved out of
             | config/shunno.php so the client can correct a phone number
             | without a deploy.
             |
             | Seeded with the values already in config/shunno.php rather than
             | with blanks. These are printed on the public site and in every
             | email footer, so an empty row would blank the footer on the first
             | deploy after this seeder runs. config() remains the fallback, per
             | SettingsRepository::get().
             */
            ['key' => 'studio.name',      'value' => 'Shunno Art Cafe',                            'type' => 'string', 'group' => 'general', 'label' => 'Studio name'],
            ['key' => 'contact.email',    'value' => 'artcafe.shunno@gmail.com',                   'type' => 'string', 'group' => 'general', 'label' => 'Contact email'],
            ['key' => 'contact.phone',    'value' => '+8801799020731',                             'type' => 'string', 'group' => 'general', 'label' => 'Contact phone'],
            ['key' => 'contact.whatsapp', 'value' => '8801711532891',                              'type' => 'string', 'group' => 'general', 'label' => 'WhatsApp'],
            ['key' => 'contact.address',  'value' => '5/6 Block F, Lalmatia, Dhaka 1207, Bangladesh', 'type' => 'string', 'group' => 'general', 'label' => 'Address'],

            /*
             | PHASE 17 — SMTP.
             |
             | SEEDED EMPTY, DELIBERATELY. An empty value means "not configured
             | here", and RuntimeConfigServiceProvider skips empty keys — so a
             | fresh install keeps running on .env until an Admin fills the form
             | in. Seeding these with anything real would override a working
             | .env the moment this seeder ran, which is the opposite of what a
             | seeder should do to a live site.
             |
             | mail.password is absent from this list on purpose: it is written
             | only through SettingsRepository::setSecret(), which encrypts it.
             | A seeder row would create it as plain text with type 'string' and
             | quietly bypass that.
             */
            ['key' => 'mail.host',         'value' => 'booking.studioshunno.net', 'type' => 'string',  'group' => 'mail', 'label' => 'SMTP host'],
            ['key' => 'mail.port',         'value' => '465', 'type' => 'integer', 'group' => 'mail', 'label' => 'SMTP port'],
            ['key' => 'mail.username',     'value' => 'info@booking.studioshunno.net', 'type' => 'string',  'group' => 'mail', 'label' => 'SMTP username'],
            ['key' => 'mail.encryption',   'value' => 'zWQOemyDX2', 'type' => 'string',  'group' => 'mail', 'label' => 'Encryption'],
            ['key' => 'mail.from_address', 'value' => 'info@booking.studioshunno.net', 'type' => 'string',  'group' => 'mail', 'label' => 'Send emails from'],
            ['key' => 'mail.from_name',    'value' => 'Studio Shunno', 'type' => 'string',  'group' => 'mail', 'label' => 'Sender name'],

            /*
             | PHASE 19 — SSLCommerz, moved out of .env into this table.
             |
             | Store IDs are seeded empty for the same reason the mail keys are:
             | empty means "not configured here", and
             | RuntimeConfigServiceProvider leaves config alone until both an ID
             | and a password exist. So an install still running on .env keeps
             | working until somebody fills the form in.
             |
             | THE MODE DEFAULTS TO SANDBOX and that is deliberate. A missing or
             | unrecognised value must never transact for real — the failure of
             | guessing wrong in that direction is money moving when nobody
             | meant it to, and the failure of guessing wrong the other way is a
             | test payment that does not settle.
             |
             | Neither store password appears here. Both are written only
             | through SettingsRepository::setSecret(), which encrypts them; a
             | seeder row would create them as plain text with type 'string' and
             | quietly bypass that.
             */
            ['key' => 'sslcommerz.mode',             'value' => 'sandbox', 'type' => 'string', 'group' => 'gateway', 'label' => 'Gateway mode'],
            ['key' => 'sslcommerz.sandbox_store_id', 'value' => '',        'type' => 'string', 'group' => 'gateway', 'label' => 'Sandbox store ID'],
            ['key' => 'sslcommerz.live_store_id',    'value' => '',        'type' => 'string', 'group' => 'gateway', 'label' => 'Live store ID'],
        ];

        foreach ($settings as $setting) {
            /*
             | PHASE 17 — VALUES ARE SEEDED ONCE, THEN LEFT ALONE.
             |
             | This used to be a plain updateOrCreate, which rewrote `value` on
             | every run. That was survivable while the table held defaults
             | nobody had touched. It stopped being survivable when Phase 17 put
             | the studio's contact details and its SMTP configuration in here:
             | a routine `db:seed` after a deploy would silently reset the
             | booking fee, the payment deadline and the mail host back to the
             | shipped defaults, and the only symptom would be email quietly
             | stopping.
             |
             | So: create the row with its default if it is missing, and
             | otherwise update only the METADATA — label, group, type,
             | description. Those are ours to change between releases. The value
             | belongs to the client from the moment they first save it.
             |
             | To reset a setting deliberately, delete the row and re-seed.
             */
            $existing = Setting::where('key', $setting['key'])->first();

            if (! $existing) {
                Setting::create($setting);

                continue;
            }

            $existing->update([
                'type' => $setting['type'],
                'group' => $setting['group'],
                'label' => $setting['label'],
                'description' => $setting['description'] ?? $existing->description,
            ]);
        }

        app(SettingsRepository::class)->flush();
    }
}
