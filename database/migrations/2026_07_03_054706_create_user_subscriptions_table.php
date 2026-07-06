<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->restrictOnDelete();

            // Snapshotted at purchase time, deliberately decoupled from the
            // live plan — an admin editing a plan's limits later cannot
            // retroactively change a paying customer's limits mid-cycle.
            $table->unsignedInteger('certificates_limit')->default(0);
            $table->unsignedInteger('users_limit')->default(0);
            $table->unsignedInteger('certificates_used')->default(0);
            $table->unsignedInteger('users_used')->default(0);

            // Pairs with the scheduled ResetUsageCountersJob — counters
            // reset only once this period has actually elapsed, not on a
            // fixed calendar schedule.
            $table->timestamp('current_period_started_at')->useCurrent();
            $table->enum('billing_period', ['monthly', 'yearly'])->default('monthly');

            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->char('currency', 3)->default('INR');

            // Explicit failed state — the reference app could get stuck at
            // "pending" forever with no way to resolve it.
            $table->enum('payment_status', ['pending', 'completed', 'failed', 'refunded'])->default('pending');

            $table->string('razorpay_order_id')->nullable();
            $table->string('razorpay_payment_id')->nullable()->unique();
            $table->string('razorpay_signature')->nullable();
            $table->timestamp('payment_verified_at')->nullable();

            $table->timestamp('start_date')->useCurrent();
            $table->timestamp('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_renew')->default(false);
            $table->json('payment_details')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'is_active']);
            $table->index('payment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_subscriptions');
    }
};
