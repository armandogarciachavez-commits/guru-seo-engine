<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshots de métricas de Google Search Console por proyecto.
 *
 * Cada corrida del monitor inserta un lote de filas (grano: query + page)
 * para la ventana de 28 días consultada. Se guarda captured_at para poder
 * quedarse con el lote más reciente y, a futuro, graficar tendencias.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gsc_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            $table->date('window_from');
            $table->date('window_to');

            $table->string('query', 500)->nullable();
            $table->string('page', 500)->nullable();

            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->decimal('ctr', 7, 4)->default(0);       // 0.0000 – 1.0000
            $table->decimal('position', 7, 2)->default(0);  // posición media

            $table->timestamp('captured_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'window_to']);
            $table->index(['project_id', 'position']);
            $table->index(['project_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gsc_metrics');
    }
};
