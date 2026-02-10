<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\CrawlResult;
use App\Models\Article;
use Illuminate\Support\HtmlString;
use Filament\Notifications\Notification;
use Carbon\Carbon;
// Importamos AMBOS Jobs
use App\Jobs\GenerateRadiographyJob; 
use App\Jobs\GenerateArticlesJob;

class CrawlResultsRelationManager extends RelationManager
{
    protected static string $relationship = 'crawlResults';
    protected static ?string $recordTitleAttribute = 'title';
    protected static ?string $title = 'Centro de Comando SEO';
    protected static ?string $icon = 'heroicon-m-cpu-chip';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('url')->required(),
            Forms\Components\TextInput::make('title'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('status_code')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        $state == 200 => 'success',
                        $state >= 400 => 'danger',
                        default => 'gray',
                    })->sortable(),
                Tables\Columns\TextColumn::make('url')->label('URL')->limit(30),
                Tables\Columns\TextColumn::make('title')->label('Meta Titulo')->limit(30)->searchable(),
            ])
            ->headerActions([
                
                // BOTON 1: RADIOGRAFIA
                Tables\Actions\Action::make('analyze_save_strategy')
                    ->label('1. Crear Radiografia Maestra')
                    ->icon('heroicon-o-document-magnifying-glass')
                    ->color('danger')
                    ->modalHeading('Generar Estrategia')
                    ->modalDescription('Proceso en SEGUNDO PLANO.')
                    ->form([
                        Forms\Components\Textarea::make('competitors')
                            ->label('Competidores')
                            ->placeholder('Ej: competencia.com')
                            ->rows(2),
                    ])
                    ->action(function ($data, $livewire) {
                        $project = $livewire->getOwnerRecord();
                        $competitors = $data['competitors'] ?? null;

                        if ($project->crawlResults->isEmpty()) {
                            Notification::make()->title('Error: Primero rastrea el sitio')->danger()->send();
                            return;
                        }

                        try {
                            GenerateRadiographyJob::dispatch($project, $competitors);
                            Notification::make()->title('Generacion Iniciada')->body('La IA esta trabajando.')->success()->persistent()->send();
                        } catch (\Exception $e) {
                            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                        }
                    }),

                // BOTON 2: VER
                Tables\Actions\Action::make('view_strategy')
                    ->label('Ver Radiografia')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading('Estrategia Visual')
                    ->modalWidth('7xl')
                    ->modalContent(function ($livewire) {
                        $strategy = $livewire->getOwnerRecord()->fresh()->seo_strategy;
                        if (empty($strategy)) return new HtmlString('<div class="p-4 text-center">Esperando analisis...</div>');
                        // Limpieza basica visual
                        $strategy = mb_convert_encoding($strategy, 'UTF-8', 'UTF-8');
                        return new HtmlString("<div class='prose dark:prose-invert max-w-none'>{$strategy}</div>");
                    }),

                // --- BOTON 3: PROGRAMAR (AHORA CON JOB) ---
                Tables\Actions\Action::make('generate_calendar_db')
                    ->label('2. Programar Articulos')
                    ->icon('heroicon-o-calendar-days')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Generacion Masiva')
                    ->form([
                        Forms\Components\Select::make('frequency')
                            ->label('Frecuencia')
                            ->options(['2'=>'2/sem', '3'=>'3/sem', '5'=>'Diario'])
                            ->default('3')
                            ->required(),
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Inicio')
                            ->default(now()->addDay())
                            ->required(),
                    ])
                    ->action(function ($data, $livewire) {
                        $project = $livewire->getOwnerRecord();
                        
                        // Verificacion rapida
                        if (empty($project->seo_strategy)) {
                            Notification::make()->title('Error: No hay estrategia creada')->danger()->send();
                            return;
                        }

                        try {
                            // ENVIAMOS AL JOB (Aqui evitamos el error UTF-8 en vivo)
                            GenerateArticlesJob::dispatch(
                                $project, 
                                (int) $data['frequency'], 
                                $data['start_date']
                            );

                            Notification::make()
                                ->title("Programacion Iniciada")
                                ->body("La IA esta generando los titulos en segundo plano. Apareceran en el calendario pronto.")
                                ->success()
                                ->persistent()
                                ->send();

                        } catch (\Exception $e) {
                            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}