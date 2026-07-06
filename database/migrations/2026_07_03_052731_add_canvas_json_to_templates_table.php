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
            // Working canvas state — always safe to edit/autosave. Publishing
            // renders this to html_content (the field Browsershot actually
            // reads at issuance time), so an in-progress edit on a template
            // that's currently issuing certificates never affects them.
            $table->json('canvas_json')->nullable()->after('html_content');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn('canvas_json');
        });
    }
};
