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
        // Per-row tracking so a batch's progress UI and error report don't
        // need to re-parse the source file, and so a failed chunk can be
        // diagnosed/retried at the row level.
        Schema::create('certificate_batch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certificate_batch_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('row_data');
            $table->enum('status', ['pending', 'processing', 'succeeded', 'skipped', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->foreignId('certificate_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['certificate_batch_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificate_batch_items');
    }
};
