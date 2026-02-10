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
    public $timeout = 600; // Permitir que este trabajo corra por 10 minutos

    public function __construct(Project $project)
    {
        $this->project = $project;
    }

    public function handle(): void
    {
        // Limpiamos resultados viejos
        CrawlResult::where('project_id', $this->project->id)->delete();

        $url = $this->project->domain_url;
        if (!str_starts_with($url, 'http')) {
            $url = 'https://' . $url;
        }

        Log::info("Iniciando rastreo para: " . $url);

        try {
            Crawler::create()
                ->setCrawlObserver(new SeoCrawler($this->project))
                ->setCrawlProfile(new CrawlInternalUrls($url))
                ->setTotalCrawlLimit(50) // Límite de páginas
                // ->setDelayBetweenRequests(500) // Opcional: Medio segundo de pausa para no saturar
                ->startCrawling($url);

            Log::info("Rastreo finalizado para: " . $url);

        } catch (\Exception $e) {
            Log::error("Error en rastreo: " . $e->getMessage());
        }
    }
}