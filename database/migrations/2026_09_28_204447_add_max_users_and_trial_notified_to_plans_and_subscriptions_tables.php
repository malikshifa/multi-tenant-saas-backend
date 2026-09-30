<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('max_users')->nullable()->after('trial_days');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('trial_expiry_notified_at')->nullable()->after('trial_ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('max_users');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('trial_expiry_notified_at');
        });
    }
};
