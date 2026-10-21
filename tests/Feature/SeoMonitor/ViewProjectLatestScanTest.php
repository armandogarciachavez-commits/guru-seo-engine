<?php

namespace Tests\Feature\SeoMonitor;

use App\Filament\Resources\ProjectResource\Pages\ViewProject;
use App\Models\SeoOpportunity;
use App\Models\User;
use Livewire\Livewire;

class ViewProjectLatestScanTest extends SeoMonitorTestCase
{
    protected function admin(): User
    {
        return User::factory()->create(['email' => 'admin@gurumkt.com.mx']);
    }

    public function test_shows_only_latest_scan_opportunities(): void
    {
        $project = $this->makeProject();

        // Lote viejo: no debe aparecer.
        SeoOpportunity::create([
            'project_id' => $project->id, 'query' => 'keyword-vieja', 'page' => 'https://demo.mx/v',
            'status' => 'new', 'priority_score' => 50, 'detected_at' => now()->subWeek(),
            'impressions' => 100, 'position' => 9, 'ctr' => 0.0,
        ]);

        // Lote más reciente: debe aparecer con su sugerencia de IA.
        SeoOpportunity::create([
            'project_id' => $project->id, 'query' => 'keyword-nueva', 'page' => 'https://demo.mx/n',
            'status' => 'in_progress', 'priority_score' => 80, 'detected_at' => now(),
            'impressions' => 200, 'position' => 8, 'ctr' => 0.0,
            'ai_suggestions' => ['title' => 'Titulo IA Nuevo', 'meta_description' => 'meta', 'notes' => 'n'],
        ]);

        $this->actingAs($this->admin());

        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])
            ->assertSee('keyword-nueva')
            ->assertSee('Titulo IA Nuevo')
            ->assertDontSee('keyword-vieja');
    }

    public function test_shows_empty_state_when_no_opportunities(): void
    {
        $project = $this->makeProject();
        $this->actingAs($this->admin());

        Livewire::test(ViewProject::class, ['record' => $project->getRouteKey()])
            ->assertSee('Sin oportunidades en el último escaneo');
    }
}
