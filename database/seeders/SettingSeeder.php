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
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
