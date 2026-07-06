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
        Schema::table('certificates', function (Blueprint $table) {
            // Keyed by the template's custom_field_schema token key, mirrors
            // custom_fields but holds storage paths instead of text — kept
            // as a separate column (rather than mixed into custom_fields)
            // so text values stay in one place and are always safe to
            // e()-escape as plain strings, with no risk of a path being
            // mistaken for displayable text or vice versa.
            $table->json('custom_image_fields')->nullable()->after('custom_fields');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropColumn('custom_image_fields');
        });
    }
};
