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
        // BulkUploadController::statusData()'s "recent activity" poll runs
        // whereIn('status', [...])->latest('updated_at')->limit(8) scoped
        // to one batch. The existing (certificate_batch_id, status) index
        // covers the WHERE but not the ORDER BY, so every poll needed a
        // filesort on top of the index lookup - this composite index
        // covers the whole query (equality on certificate_batch_id, then
        // status, then a range/sort on updated_at), same leftmost-prefix
        // columns as the old index plus the sort key, so no filesort.
        Schema::table('certificate_batch_items', function (Blueprint $table) {
            $table->index(['certificate_batch_id', 'status', 'updated_at'], 'certificate_batch_items_recent_activity_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('certificate_batch_items', function (Blueprint $table) {
            $table->dropIndex('certificate_batch_items_recent_activity_index');
        });
    }
};
