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
        // Null for single-issue certificates, set for bulk-issued ones.
        // Added here rather than in the original create_certificates_table
        // migration because bulk upload is genuinely new functionality
        // (Milestone 3), not a fix to something that already existed.
        Schema::table('certificates', function (Blueprint $table) {
            $table->foreignId('certificate_batch_id')->nullable()->after('template_id')
                ->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('certificate_batch_id');
        });
    }
};
