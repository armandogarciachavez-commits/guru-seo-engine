<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Http;
use App\Models\CrawlResult;
use App\Models\Article;
use Illuminate\Support\HtmlString;
use Filament\Notifications\Notification;
use Carbon\Carbon;
// Importamos el Job para el trabajo pesado
use App\Jobs\GenerateRadiographyJob; 

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
                
                // --- BOTON 1: GENERAR ESTRATEGIA (VIA JOB) ---
                Tables\Actions\Action::make('analyze_save_strategy')
                    ->label('1. Crear Radiografia Maestra')
                    ->icon('heroicon-o-document-magnifying-glass')
                    ->color('danger')
                    ->modalHeading('Generar Estrategia')
                    ->modalDescription('Este proceso se ejecutara en SEGUNDO PLANO. Puedes cerrar esta ventana.')
                    ->form([
                        Forms\Components\Textarea::make('competitors')
                            ->label('Competidores')
                            ->placeholder('Ej: competencia.com')
                            ->rows(2),
                    ])
                    ->action(function ($data, $livewire) {
                        $project = $livewire->getOwnerRecord();
                        $competitors = $data['competitors'] ?? null;

                        // Validar que haya datos
                        if ($project->crawlResults->isEmpty()) {
                            Notification::make()->title('Error: Primero debes rastrear el sitio')->danger()->send();
                            return;
                        }

                        try {
                            // Enviamos al Job
                            GenerateRadiographyJob::dispatch($project, $competitors);

                            Notification::make()
                                ->title('Generacion Iniciada')
                                ->body('La IA esta trabajando en segundo plano. Revisa en unos minutos.')
                                ->success()
                                ->persistent()
                                ->send();

                        } catch (\Exception $e) {
                            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                        }
                    }),

                // --- BOTON 2: VER ESTRATEGIA ---
                Tables\Actions\Action::make('view_strategy')
                    ->label('Ver Radiografia')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading('Estrategia Visual')
                    ->modalWidth('7xl')
                    ->action(function ($livewire) {
                         if (empty($livewire->getOwnerRecord()->fresh()->seo_strategy)) {
                             Notification::make()
                                ->title('Aun no esta lista')
                                ->body('La IA sigue pensando. Intenta de nuevo en unos momentos.')
                                ->warning()
                                ->send();
                         }
                    })
                    ->modalContent(function ($livewire) {
                        $strategy = $livewire->getOwnerRecord()->fresh()->seo_strategy;
                        if (empty($strategy)) return new HtmlString('<div class="p-4 text-center">Esperando analisis...</div>');
                        return new HtmlString("<div class='prose dark:prose-invert max-w-none'>{$strategy}</div>");
                    }),

                // --- BOTON 3: PROGRAMAR ARTICULOS (LOGICA COMPLETA) ---
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
                        set_time_limit(300);
                        ini_set('max_execution_time', 300);

                        $project = $livewire->getOwnerRecord()->fresh();
                        if (empty($project->seo_strategy)) {
                            Notification::make()->title('Error: Falta Estrategia')->danger()->send();
                            return;
                        }

                        $frecuencia = (int) $data['frequency'];
                        $postsA_Generar = $frecuencia * 4; 
                        $fechaInicio = Carbon::parse($data['start_date']);

                        try {
                            $apiKey = env('GEMINI_API_KEY');
                            // Prompt JSON
                            $prompt = "ACTUA COMO: API JSON. CONTEXTO: {$project->seo_strategy}. TAREA: {$postsA_Generar} ideas de articulos. RESPUESTA SOLO JSON: [{\"title\":\"...\",\"keyword\":\"...\",\"intention\":\"...\"}]";

                            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                                ->timeout(60)
                                ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . $apiKey, [
                                    'contents' => [['parts' => [['text' => $prompt]]]],
                                    'generationConfig' => ['temperature' => 0.5]
                                ]);

                            $jsonText = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? '[]';
                            $jsonText = str_replace(['```json', '```', '```'], '', $jsonText); 
                            $plan = json_decode($jsonText, true);

                            if (!is_array($plan)) throw new \Exception("Error al leer JSON de IA");

                            $currentDate = $fechaInicio->copy();
                            
                            // Creamos los articulos
                            foreach ($plan as $item) {
                                // Logica simple de fechas
                                if ($frecuencia == 3) $currentDate->addDays(2); 
                                elseif ($frecuencia == 5) {
                                    $currentDate->addDay();
                                    if ($currentDate->isWeekend()) $currentDate->addDays(2);
                                } else $currentDate->addDays(3);
                                
                                Article::create([
                                    'project_id' => $project->id,
                                    'title' => $item['title'],
                                    'keyword' => $item['keyword'] ?? 'General',
                                    'status' => 'pending', 
                                    'scheduled_date' => $currentDate->format('Y-m-d'),
                                ]);
                            }

                            Notification::make()
                                ->title("Exito: {$postsA_Generar} Articulos Programados")
                                ->body("Se han añadido a la cola del calendario.")
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