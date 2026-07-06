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
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category')->nullable();
            $table->longText('html_content');
            $table->string('thumbnail_path')->nullable();

            // Drives the Browsershot render — see Bug 7 in the reference-app
            // audit, where page format/orientation were hard-coded instead
            // of read from the template.
            $table->string('page_format')->default('a4');
            $table->enum('orientation', ['landscape', 'portrait'])->default('landscape');

            $table->boolean('is_active')->default(false);
            $table->boolean('is_exclusive')->default(false);

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
