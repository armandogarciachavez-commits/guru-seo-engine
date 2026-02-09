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
        Schema::table('articles', function (Blueprint $table) {
            // Solo crea la columna si NO existe
            if (!Schema::hasColumn('articles', 'status')) {
                $table->string('status')->default('pending');
            }
            
            if (!Schema::hasColumn('articles', 'scheduled_date')) {
                $table->date('scheduled_date')->nullable();
            }

            if (!Schema::hasColumn('articles', 'keyword')) {
                $table->string('keyword')->nullable();
            }

            if (!Schema::hasColumn('articles', 'intention')) {
                $table->string('intention')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn(['status', 'scheduled_date', 'keyword', 'intention']);
        });
    }
};