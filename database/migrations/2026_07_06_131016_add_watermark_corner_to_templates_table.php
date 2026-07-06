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
            // Computed once per template (not per certificate - see
            // CertificateRenderService::resolveWatermarkCorner) by rendering
            // a sample and measuring which corner has the least visual
            // content, then cached here so every certificate issued from
            // this template reads it instantly instead of re-analyzing.
            $table->string('watermark_corner')->nullable()->after('canvas_json');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn('watermark_corner');
        });
    }
};
