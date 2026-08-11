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
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            // Purely descriptive metadata, not an enforced security
            // boundary - see the analysis behind this feature: Origin/
            // Referer headers can't be trusted to gate access (server-side
            // callers rarely send them, and any client can fake them), so
            // this exists only to let an admin label and recognize which
            // integration a key belongs to.
            $table->string('website_name')->nullable()->after('name');
            $table->string('website_url')->nullable()->after('website_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropColumn(['website_name', 'website_url']);
        });
    }
};
