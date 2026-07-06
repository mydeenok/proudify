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
        Schema::table('templates', function (Blueprint $table) {
            // Admin-defined tokens beyond the fixed system set (title,
            // recipient_name, etc). Each entry: {key, label, type: text|image,
            // required}. Drives both the dynamic certificate-create form and
            // the user-facing inline editor — the schema is the single
            // authority on which {tokens} in html_content a user may fill in,
            // and never the raw HTML itself.
            $table->json('custom_field_schema')->nullable()->after('canvas_json');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn('custom_field_schema');
        });
    }
};
