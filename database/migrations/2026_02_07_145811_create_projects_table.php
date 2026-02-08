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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('domain_url');
            $table->string('target_language'); // e.g. 'es-MX'
            $table->text('brand_voice');
            $table->string('target_city')->nullable();
            $table->enum('cms_type', ['wordpress', 'static']);
            
            // ✅ AQUÍ AGREGAMOS LA COLUMNA DIRECTAMENTE Y SIN ERRORES:
            $table->enum('posting_frequency', ['daily', 'weekly', 'monthly'])->default('weekly');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};