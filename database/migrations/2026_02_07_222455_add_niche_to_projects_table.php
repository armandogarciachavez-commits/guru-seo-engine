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
        Schema::table('projects', function (Blueprint $table) {
            // Agregamos las columnas solo si no existen
            if (!Schema::hasColumn('projects', 'niche')) {
                $table->text('niche')->nullable()->after('name');
            }
            if (!Schema::hasColumn('projects', 'business_name')) {
                $table->string('business_name')->nullable()->after('name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['niche', 'business_name']);
        });
    }
};