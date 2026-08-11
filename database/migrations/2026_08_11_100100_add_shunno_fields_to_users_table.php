<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One users table, three Spatie roles: Admin, Manager, Visitor.
 *
 * Visitors are created automatically when a reservation request arrives and
 * never choose a password — they sign in by OTP. Rather than a NULL password,
 * they get an unusable random hash (see ReservationService), so nothing depends
 * on how the auth guard happens to treat NULL. `password_set_at` records
 * whether a real password was ever chosen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // VARCHAR, never numeric. Excel turned 01406639867 into
            // 1.406639867E9 in the Google Form export and lost the leading zero.
            $table->string('phone', 20)->nullable()->after('email');
            $table->string('whatsapp', 20)->nullable()->after('phone');

            $table->timestamp('password_set_at')->nullable()->after('password');
            $table->boolean('is_active')->default(true)->after('password_set_at');
            $table->string('source', 32)->default('web')->after('is_active');

            $table->unsignedInteger('total_reservations')->default(0)->after('source');
            $table->timestamp('last_reservation_at')->nullable()->after('total_reservations');
            $table->timestamp('last_login_at')->nullable()->after('last_reservation_at');

            $table->softDeletes();

            $table->index('phone');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['phone']);
            $table->dropIndex(['is_active']);
            $table->dropSoftDeletes();
            $table->dropColumn([
                'phone', 'whatsapp', 'password_set_at', 'is_active', 'source',
                'total_reservations', 'last_reservation_at', 'last_login_at',
            ]);
        });
    }
};
