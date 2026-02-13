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

        // Buscar artículos NO publicados cuya fecha programada ya pasó
        $count = Article::where('status', '!=', 'published')
            ->whereDate('scheduled_date', '<=', $now)
            ->update(['status' => 'published']);

        if ($count > 0) {
            $this->info("¡Se han publicado automáticamente {$count} artículos!");
        } else {
            $this->info("No hay artículos pendientes para publicar hoy.");
        }
    }
}