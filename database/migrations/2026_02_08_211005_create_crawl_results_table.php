<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crawl_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete(); // Vinculado al proyecto
            $table->string('url');
            $table->integer('status_code'); // 200, 404, 500
            $table->string('title')->nullable();
            $table->text('h1')->nullable(); // Puede ser largo
            $table->text('meta_description')->nullable();
            $table->integer('word_count')->nullable(); // Conteo de palabras
            $table->json('internal_links')->nullable(); // Enlaces internos encontrados
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crawl_results');
    }
};