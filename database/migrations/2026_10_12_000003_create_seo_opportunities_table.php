<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Oportunidades de "quick win" detectadas a partir de los datos de GSC.
 *
 * Una oportunidad = keyword en posición media 5–15 con impresiones altas
 * y CTR por debajo del esperado para su posición (metadato mejorable).
 *
 * ai_suggestions guarda la propuesta de la IA (Fase 4): title + meta
 * description optimizados, listos para aplicar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            $table->string('query', 500);
            $table->string('page', 500);

            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->decimal('ctr', 7, 4)->default(0);
            $table->decimal('position', 7, 2)->default(0);

            // Potencial: clics extra estimados si el CTR sube al objetivo de su posición.
            $table->unsignedInteger('potential_clicks')->default(0);
            $table->decimal('priority_score', 12, 2)->default(0);

            // new | in_progress | applied | dismissed
            $table->string('status')->default('new');

            // {title, meta_description, notes} generado por la IA
            $table->json('ai_suggestions')->nullable();

            $table->date('window_from')->nullable();
            $table->date('window_to')->nullable();
            $table->timestamp('detected_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['project_id', 'priority_score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_opportunities');
    }
};
