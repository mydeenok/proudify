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
        // Optimistic-locking counter for the Visual Builder's autosave/
        // publish endpoints. Without this, two tabs/editors on the same
        // template could silently overwrite each other's canvas_json -
        // last write wins, no conflict detection. Starts at 1 so an
        // existing template's very first builder save (client loaded
        // version 1) matches cleanly.
        Schema::table('templates', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1)->after('canvas_json');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn('version');
        });
    }
};
