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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('thumbnail_path')->nullable();

            // Never nullable — the reference app's yearly-limit columns
            // were added as nullable, and NULL silently coerced to 0 in
            // PHP, letting paying customers issue zero certificates. That
            // failure mode is structurally impossible here.
            $table->unsignedInteger('certificates_per_month')->default(0);
            $table->unsignedInteger('certificates_per_year')->default(0);
            $table->unsignedInteger('users_per_month')->default(0);
            $table->unsignedInteger('users_per_year')->default(0);

            $table->decimal('cost_month_inr', 10, 2)->default(0);
            $table->decimal('cost_year_inr', 10, 2)->default(0);
            $table->decimal('cost_month_usd', 10, 2)->default(0);
            $table->decimal('cost_year_usd', 10, 2)->default(0);

            // Singleton invariant enforced in the Action layer (see
            // ActivateFreePlanAction) rather than a DB constraint — MySQL
            // has no native partial/filtered unique index.
            $table->boolean('is_default_free_plan')->default(false);

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
