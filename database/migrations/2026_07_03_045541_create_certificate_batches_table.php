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
        // Batch-level counters/status, replacing the reference app's
        // in-memory, request-scoped $skippedRows array with a persisted,
        // resumable, partially-retryable structure.
        Schema::create('certificate_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->constrained()->restrictOnDelete();

            // Set when an admin issues on behalf of another user/org
            // (Milestone 6's admin bulk-upload org-assignment).
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('original_filename');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('succeeded_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);

            $table->enum('status', [
                'uploaded', 'mapping', 'validating', 'queued', 'processing', 'completed', 'completed_with_errors', 'failed',
            ])->default('uploaded');

            // Persists the "Map Columns" wizard step's field->column
            // assignments, and the temp upload path until rows are parsed.
            $table->json('column_mapping')->nullable();
            $table->string('temp_upload_path')->nullable();
            $table->string('error_report_path')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificate_batches');
    }
};
