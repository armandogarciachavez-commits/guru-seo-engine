<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->enum('integration_type', ['widget', 'wordpress'])->default('widget');
            $table->string('wp_api_url')->nullable();
            $table->string('wp_username')->nullable();
            $table->string('wp_application_password')->nullable();
            
            // ❌ BORRADO: posting_frequency (Ya existe en la tabla original)
            
            $table->dateTime('next_run_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'integration_type',
                'wp_api_url',
                'wp_username',
                'wp_application_password',
                // ❌ BORRADO: posting_frequency (No lo borramos aquí porque pertenece a la tabla original)
                'next_run_at'
            ]);
        });
    }
};