<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewProject extends ViewRecord
{
    protected static string $resource = ProjectResource::class;

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
