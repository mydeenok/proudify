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
        // Append-only audit log of every public verify-page lookup. Doubles
        // as the source for the "verification rate" analytics metric
        // planned for Milestone 6.
        Schema::create('certificate_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certificate_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->enum('result', ['valid', 'revoked', 'expired', 'not_found'])->index();
            $table->timestamps();

            $table->index(['certificate_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificate_verifications');
    }
};
