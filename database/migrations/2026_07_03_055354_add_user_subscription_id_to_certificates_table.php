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
        // Null for admin-issued certificates (no quota applies to them);
        // set for user-issued ones, recording exactly which subscription
        // instance's quota this certificate consumed.
        Schema::table('certificates', function (Blueprint $table) {
            $table->foreignId('user_subscription_id')->nullable()->after('certificate_batch_id')
                ->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_subscription_id');
        });
    }
};
