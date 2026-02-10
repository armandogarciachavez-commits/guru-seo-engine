<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            // Si no existe la columna, la creamos
            if (!Schema::hasColumn('articles', 'scheduled_date')) {
                $table->date('scheduled_date')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('scheduled_date');
        });
    }
};