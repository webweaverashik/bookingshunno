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
            ['key' => 'cafe_credit.entry_fee_coupon',    'value' => '50', 'type' => 'integer', 'group' => 'credit',  'label' => 'Cafe coupon with entry fee (BDT)'],
            ['key' => 'cafe_credit.per_participant',     'value' => '1',  'type' => 'boolean', 'group' => 'credit',  'label' => 'Issue cafe coupon per participant'],
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
