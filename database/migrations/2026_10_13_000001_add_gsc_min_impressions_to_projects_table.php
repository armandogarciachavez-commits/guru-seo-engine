<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Umbral de impresiones del detector de quick-wins, configurable por proyecto.
 * NULL = usar el default del detector (QuickWinDetectorService::$minImpressions).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (! Schema::hasColumn('projects', 'gsc_min_impressions')) {
                $table->unsignedInteger('gsc_min_impressions')->nullable()->after('gsc_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'gsc_min_impressions')) {
                $table->dropColumn('gsc_min_impressions');
            }
        });
    }
};
