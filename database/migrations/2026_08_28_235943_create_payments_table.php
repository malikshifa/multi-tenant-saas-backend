<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignId('subscription_id')
                ->constrained('subscriptions')
                ->restrictOnDelete();

            $table->decimal('amount', 12, 2);

            $table->string('currency', 3)
                ->default('USD');

            $table->string('payment_method');

            $table->string('status')
                ->default('pending');

            /*
             * Stripe:
             * checkout_session_id
             *
             * PayPal:
             * order_id
             */
            $table->string('gateway_reference')
                ->nullable()
                ->index();

            /*
             * Hosted checkout URL returned by
             * Stripe / PayPal.
             */
            $table->text('checkout_url')
                ->nullable();

            /*
             * Final transaction/payment ID.
             *
             * Stripe:
             * payment_intent
             *
             * PayPal:
             * capture_id
             */
            $table->string('transaction_id')
                ->nullable()
                ->index();

            $table->timestamp('paid_at')
                ->nullable();

            $table->text('failure_reason')
                ->nullable();

            $table->timestamps();

            $table->index([
                'tenant_id',
                'subscription_id',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
