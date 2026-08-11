<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('billing_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('price_per_certificate_inr', 8, 2)->default(20.00);
            $table->unsignedInteger('bulk_discount_threshold')->default(5);
            $table->decimal('bulk_discount_percent', 5, 2)->default(10.00);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Single-row config table - seed the one row now so
        // BillingSetting::current() never has to handle a missing row.
        DB::table('billing_settings')->insert([
            'id' => 1,
            'price_per_certificate_inr' => 20.00,
            'bulk_discount_threshold' => 5,
            'bulk_discount_percent' => 10.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_settings');
    }
};
