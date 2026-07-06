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
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Fixes Bug 1 from the reference-app audit: verification was
            // UUID-only with no secondary secret and no revocation path.
            // verification_code is a short, human-typeable second factor;
            // verification_signature is an HMAC over uuid+code+issued_at
            // so a row edited directly in the database (bypassing the app)
            // fails verification even if uuid/code superficially match.
            $table->string('verification_code', 8)->unique();
            $table->string('verification_signature');

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->constrained()->restrictOnDelete();

            $table->string('title');
            $table->string('recipient_name');
            $table->string('recipient_email');
            $table->text('description')->nullable();

            $table->date('date_of_issue');
            $table->date('date_of_expiry')->nullable();

            // Flexible extra placeholder data instead of a growing list of
            // single-purpose columns (the reference app's validation_info/
            // company_details/behalf_email pattern).
            $table->json('custom_fields')->nullable();
            $table->json('company_logos')->nullable();
            $table->string('signature_path')->nullable();

            $table->string('pdf_path')->nullable();
            $table->string('image_path')->nullable();
            $table->string('qr_code_path')->nullable();

            // Fixes Bug 4: PDF->JPG conversion failures were silent.
            $table->enum('image_generation_status', ['pending', 'processing', 'completed', 'failed'])
                ->default('pending');

            $table->enum('status', ['active', 'revoked'])->default('active');
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index('recipient_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
