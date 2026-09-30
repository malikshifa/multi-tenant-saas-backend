<?php

use App\Enums\TenantStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();

            // Basic tenant information
            $table->string('name');
            $table->string('slug')->unique();

            // Tenant status
            $table->enum('status', TenantStatus::values())->default(TenantStatus::PROVISIONING->value);

            // Tenant database information
            $table->string('database_name')->unique();
            $table->string('database_host')->default('127.0.0.1');
            $table->unsignedInteger('database_port')->default(3306);
            $table->string('database_username');
            $table->text('database_password')->nullable();

            // Provisioning information
            $table->timestamp('provisioned_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
