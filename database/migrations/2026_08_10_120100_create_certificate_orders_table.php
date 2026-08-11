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
        Schema::create('certificate_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['single', 'bulk']);
            $table->foreignId('template_id')->constrained()->cascadeOnDelete();

            // Bulk only - the batch already exists (created at upload time)
            // before payment happens.
            $table->foreignId('certificate_batch_id')->nullable()->constrained('certificate_batches')->nullOnDelete();

            // Single only, populated after issuance - lets a receipt/status
            // page link straight to the certificate that was paid for.
            $table->foreignId('certificate_id')->nullable()->constrained('certificates')->nullOnDelete();

            // Single only - the full validated store() payload, so checkout
            // can call IssueSingleCertificateAction with the exact original
            // submission once payment is confirmed.
            $table->json('payload')->nullable();

            $table->unsignedInteger('quantity');

            // Price snapshot at order-creation time - an admin changing
            // BillingSetting mid-checkout must never mutate an order that's
            // already in flight.
            $table->decimal('unit_price', 10, 2);
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2);
            $table->char('currency', 3)->default('INR');

            $table->enum('status', ['pending', 'paid', 'failed', 'cancelled', 'expired', 'refunded'])->default('pending');

            $table->string('razorpay_order_id')->nullable()->unique();
            $table->string('razorpay_payment_id')->nullable()->unique();
            $table->string('razorpay_signature')->nullable();
            $table->string('razorpay_refund_id')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->foreignId('refunded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificate_orders');
    }
};
