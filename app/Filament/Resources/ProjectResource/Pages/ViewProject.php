<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use App\Models\Project;
use Filament\Actions;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewProject extends ViewRecord
{
    protected static string $resource = ProjectResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Datos del proyecto')
                ->columns(3)
                ->schema([
                    TextEntry::make('name')->label('Nombre'),
                    TextEntry::make('domain_url')->label('Dominio')
                        ->url(fn ($state) => $state)->openUrlInNewTab(),
                    TextEntry::make('gsc_property')->label('Propiedad GSC')
                        ->placeholder('No configurada'),
                    TextEntry::make('gsc_enabled')->label('Monitoreo GSC')->badge()
                        ->formatStateUsing(fn ($state) => $state ? 'Activo' : 'Inactivo')
                        ->color(fn ($state) => $state ? 'success' : 'gray'),
                    TextEntry::make('gsc_min_impressions')->label('Min. impresiones')
                        ->placeholder('10 (default)'),
                ]),

            Section::make('Último escaneo SEO')
                ->description(fn (Project $record) => $record->latestScanAt()
                    ? 'Corrida del '.$record->latestScanAt()->format('d/m/Y H:i').' · ordenadas por prioridad'
                    : 'Aún sin escaneos para este proyecto.')
                ->icon('heroicon-o-magnifying-glass-circle')
                ->schema([
                    TextEntry::make('sin_datos')
                        ->hiddenLabel()
                        ->state('Sin oportunidades en el último escaneo (o Search Console no está configurado para este proyecto).')
                        ->color('gray')
                        ->visible(fn (Project $record) => $record->latestScanOpportunities()->isEmpty()),

                    RepeatableEntry::make('latestScan')
                        ->hiddenLabel()
                        ->visible(fn (Project $record) => $record->latestScanOpportunities()->isNotEmpty())
                        ->state(fn (Project $record) => $record->latestScanOpportunities()
                            ->map(fn ($o) => [
                                'query' => $o->query,
                                'priority_score' => $o->priority_score,
                                'status' => $o->status,
                                'position' => round((float) $o->position, 1),
                                'impressions' => $o->impressions,
                                'ctr' => round((float) $o->ctr * 100, 1).'%',
                                'potential_clicks' => '+'.(int) $o->potential_clicks,
                                'ai_title' => $o->ai_suggestions['title'] ?? null,
                                'ai_meta' => $o->ai_suggestions['meta_description'] ?? null,
                                'ai_notes' => $o->ai_suggestions['notes'] ?? null,
                            ])->all())
                        ->schema([
                            Grid::make(4)->schema([
                                TextEntry::make('query')->label('Keyword')
                                    ->weight('bold')->columnSpan(2),
                                TextEntry::make('priority_score')->label('Prioridad')
                                    ->badge()->color('warning'),
                                TextEntry::make('status')->label('Estado')->badge()
                                    ->formatStateUsing(fn ($state) => match ($state) {
                                        'new' => 'Nueva',
                                        'in_progress' => 'En progreso',
                                        'applied' => 'Aplicada',
                                        'dismissed' => 'Descartada',
                                        default => (string) $state,
                                    })
                                    ->color(fn ($state) => match ($state) {
                                        'applied' => 'success',
                                        'dismissed' => 'gray',
                                        'in_progress' => 'info',
                                        default => 'warning',
                                    }),
                            ]),
                            Grid::make(4)->schema([
                                TextEntry::make('position')->label('Posición'),
                                TextEntry::make('impressions')->label('Impresiones'),
                                TextEntry::make('ctr')->label('CTR'),
                                TextEntry::make('potential_clicks')->label('Clics potenciales'),
                            ]),
                            TextEntry::make('ai_title')->label('Title sugerido (IA)')
                                ->copyable()->placeholder('— sin sugerencia —'),
                            TextEntry::make('ai_meta')->label('Meta sugerida (IA)')
                                ->copyable()->placeholder('—'),
                            TextEntry::make('ai_notes')->label('Notas')
                                ->color('gray')->placeholder('—'),
                        ]),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('generate_article')
                ->label('Generar Artículo IA')
                ->color('primary')
                ->icon('heroicon-o-sparkles')
                ->form([
                    \Filament\Forms\Components\TextInput::make('keyword')
                        ->label('Palabra Clave (Keyword)')
                        ->required()
                        ->placeholder('Ej: Zapatos de seguridad industrial')
                        ->helperText('Sobre qué tema quieres que escriba la IA?'),
                ])
                ->action(function (array $data, \App\Models\Project $record) {
                    $keyword = $data['keyword'];

                    // Notify start (optional, as the modal stays open with loading state usually)
                    \Filament\Notifications\Notification::make()
                        ->title('Generando contenido...')
                        ->body('Analizando competencia y redactando. Por favor espera.')
                        ->info()
                        ->send();

                    try {
                        // 1. Competitor Analysis
                        $competitorService = app(\App\Services\CompetitorAnalysisService::class);
                        // We need to handle potential failures in analysis gracefully or let it throw
                        $competitorData = $competitorService->analyze($keyword, $record->target_language);

                        // 2. Gemini Generation
                        $geminiService = app(\App\Services\GeminiContentService::class);
                        $htmlContent = $geminiService->generateArticle($record, $keyword, $competitorData);

                        // 3. Save Article
                        $record->articles()->create([
                            'keyword' => $keyword,
                            'title' => ucwords($keyword),
                            'slug' => \Illuminate\Support\Str::slug($keyword) . '-' . time(),
                            'html_content' => $htmlContent,
                            'status' => 'completed',
                            'competitor_data' => $competitorData,
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->title('¡Artículo Generado Exitosamente!')
                            ->success()
                            ->send();

                    } catch (\Exception $e) {
                        \Filament\Notifications\Notification::make()
                            ->title('Error al generar')
                            ->body('Ocurrió un error: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->modalHeading('Generar Nuevo Artículo')
                ->modalDescription('Ingresa la palabra clave principal. El sistema analizará la competencia y redactará un artículo optimizado.')
                ->modalSubmitActionLabel('Generar (Puede tardar uns segundos)'),
        ];
    }
}
