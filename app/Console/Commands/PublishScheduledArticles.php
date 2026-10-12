<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Article;
use Carbon\Carbon;

class PublishScheduledArticles extends Command
{
    // Nombre del comando para ejecutarlo manualmente
    protected $signature = 'articles:publish-scheduled';

    // Descripción
    protected $description = 'Publica automáticamente los artículos cuya fecha programada ya llegó';

    public function handle()
    {
        // Obtener fecha y hora actual
        $now = Carbon::now();

        // Buscar artículos listos cuya fecha programada ya llegó
        $articles = Article::with('project')
            ->whereIn('status', ['generated', 'approved'])
            ->whereDate('scheduled_date', '<=', $now)
            ->get();

        $count = 0;

        foreach ($articles as $article) {
            // Nunca publicar artículos sin contenido
            if (blank($article->content) && blank($article->html_content)) {
                continue;
            }

            // Si el proyecto requiere aprobación manual, solo publicar aprobados
            if ($article->project && ! $article->project->auto_publish && $article->status !== 'approved') {
                continue;
            }

            $article->update(['status' => 'published']);
            $count++;
        }

        if ($count > 0) {
            $this->info("¡Se han publicado automáticamente {$count} artículos!");
        } else {
            $this->info("No hay artículos pendientes para publicar hoy.");
        }
    }
}