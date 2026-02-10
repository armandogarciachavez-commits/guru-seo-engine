<?php

namespace App\Jobs;

use App\Models\Project;
use App\Models\CrawlResult;
use App\Services\SeoCrawler;
use Spatie\Crawler\Crawler;
use Spatie\Crawler\CrawlProfiles\CrawlInternalUrls;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CrawlSiteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $project;
    public $timeout = 600; // 10 minutos máximo

    public function __construct(Project $project)
    {
        $this->project = $project;
    }

    public function handle(): void
    {
        // 1. Limpiamos resultados viejos para empezar de cero
        CrawlResult::where('project_id', $this->project->id)->delete();

        // 2. Preparamos la URL
        $url = $this->project->domain_url;
        if (!str_starts_with($url, 'http')) {
            $url = 'https://' . $url;
        }

        Log::info("JOB: Iniciando rastreo para: " . $url);

        try {
            // 3. Ejecutamos el Crawler (Esto es lo que tardaba 40s)
            Crawler::create()
                ->setCrawlObserver(new SeoCrawler($this->project))
                ->setCrawlProfile(new CrawlInternalUrls($url))
                ->setTotalCrawlLimit(50) // Analiza hasta 50 páginas
                ->startCrawling($url);

            Log::info("JOB: Rastreo finalizado exitosamente.");

        } catch (\Exception $e) {
            Log::error("JOB ERROR: " . $e->getMessage());
        }
    }
}