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
            $table->text('business_context')->nullable();
            $table->string('target_location')->nullable();
            $table->string('target_audience')->nullable();
            $table->string('key_services')->nullable();
            $table->string('cta_instruction')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['business_context', 'target_location', 'target_audience', 'key_services', 'cta_instruction']);
        });
    }
};
