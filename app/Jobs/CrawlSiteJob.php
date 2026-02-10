<?php

namespace App\Jobs;

use App\Models\Project;
use App\Models\CrawlResult;
use App\Services\SeoCrawler; // Asegúrate de que este servicio exista
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
    public $timeout = 600; // 10 minutos máximo de vida

    /**
     * Create a new job instance.
     */
    public function __construct(Project $project)
    {
        $this->project = $project;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // 1. Log para saber que empezó
        Log::info("JOB START: Iniciando rastreo para proyecto ID: " . $this->project->id);

        // 2. Limpiamos resultados viejos
        CrawlResult::where('project_id', $this->project->id)->delete();

        // 3. Preparamos URL
        $url = $this->project->domain_url;
        if (!str_starts_with($url, 'http')) {
            $url = 'https://' . $url;
        }

        try {
            // 4. Ejecutamos el Crawler (Lo pesado)
            Crawler::create()
                ->setCrawlObserver(new SeoCrawler($this->project))
                ->setCrawlProfile(new CrawlInternalUrls($url))
                ->setTotalCrawlLimit(50) // Analiza 50 páginas
                ->startCrawling($url);

            Log::info("JOB SUCCESS: Rastreo finalizado para: " . $url);

        } catch (\Exception $e) {
            Log::error("JOB ERROR: " . $e->getMessage());
        }
    }
}