<?php

namespace Tests\Feature\SeoMonitor;

use App\Filament\Resources\ProjectResource\Pages\EditProject;
use App\Filament\Resources\ProjectResource\RelationManagers\SeoOpportunitiesRelationManager;
use App\Models\SeoOpportunity;
use App\Models\User;
use Livewire\Livewire;

class SeoOpportunitiesRelationManagerTest extends SeoMonitorTestCase
{
    protected function admin(): User
    {
        return User::factory()->create(['email' => 'admin@gurumkt.com.mx']);
    }

    public function test_shows_only_latest_scan_batch(): void
    {
        $project = $this->makeProject();

        $old = SeoOpportunity::create([
            'project_id' => $project->id, 'query' => 'keyword-vieja', 'page' => 'https://demo.mx/v',
            'status' => 'new', 'priority_score' => 50, 'detected_at' => now()->subWeek(),
            'impressions' => 100, 'position' => 9, 'ctr' => 0.0,
        ]);

        $new = SeoOpportunity::create([
            'project_id' => $project->id, 'query' => 'keyword-nueva', 'page' => 'https://demo.mx/n',
            'status' => 'in_progress', 'priority_score' => 80, 'detected_at' => now(),
            'impressions' => 200, 'position' => 8, 'ctr' => 0.0,
            'ai_suggestions' => ['title' => 'Titulo IA', 'meta_description' => 'm', 'notes' => 'n'],
        ]);

        $this->actingAs($this->admin());

        Livewire::test(SeoOpportunitiesRelationManager::class, [
            'ownerRecord' => $project,
            'pageClass' => EditProject::class,
        ])
            ->assertCanSeeTableRecords([$new])
            ->assertCanNotSeeTableRecords([$old]);
    }

    public function test_empty_when_no_opportunities(): void
    {
        $project = $this->makeProject();
        $this->actingAs($this->admin());

        Livewire::test(SeoOpportunitiesRelationManager::class, [
            'ownerRecord' => $project,
            'pageClass' => EditProject::class,
        ])
            ->assertCountTableRecords(0)
            ->assertSee('Sin oportunidades en el último escaneo');
    }
}
