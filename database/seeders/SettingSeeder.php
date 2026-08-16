<?php

namespace Database\Seeders;

use App\Models\Setting;
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
            ['key' => 'availability.min_lead_hours',     'value' => '24',  'type' => 'integer', 'group' => 'availability', 'label' => 'Minimum notice (hours)'],
            ['key' => 'availability.max_advance_days',   'value' => '120', 'type' => 'integer', 'group' => 'availability', 'label' => 'How far ahead visitors may book (days)'],
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
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(['key' => $setting['key']], $setting);
        }

        app(\App\Services\SettingsRepository::class)->flush();
    }
}
