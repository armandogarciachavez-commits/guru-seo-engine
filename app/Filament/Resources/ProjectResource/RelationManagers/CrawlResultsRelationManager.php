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
                Tables\Columns\TextColumn::make('title')->label('Meta Título')->limit(30)->searchable(),
            ])
            ->headerActions([
                
                // --- BOTÓN 1: GENERAR ESTRATEGIA (CON DISEÑO VISUAL) ---
                Tables\Actions\Action::make('analyze_save_strategy')
                    ->label('1. Crear Radiografía Maestra')
                    ->icon('heroicon-o-document-magnifying-glass')
                    ->color('danger')
                    ->modalHeading('Generar Estrategia Visual')
                    ->modalDescription('Analizando competencia y mercado... La página se recargará al finalizar.')
                    ->modalWidth('lg')
                    ->form([
                        Forms\Components\Textarea::make('competitors')
                            ->label('Competidores')
                            ->placeholder('Ej: competencia1.com, otrodentista.mx')
                            ->rows(2),
                    ])
                    ->action(function ($data, $livewire) {
                        set_time_limit(300);
                        ini_set('max_execution_time', 300);
                        
                        $project = $livewire->getOwnerRecord();
                        $results = $project->crawlResults;

                        if ($results->isEmpty()) {
                            Notification::make()->title('Error: Sin datos')->danger()->send();
                            return;
                        }

                        $siteMapData = $results->where('status_code', 200)->take(40)->map(fn($r) => "URL: {$r->url} | H1: {$r->h1} | Title: {$r->title}")->implode("\n");
                        $ciudad = $project->target_city ?? 'Local';
                        $competidores = $data['competitors'] ?? 'N/A';

                        try {
                            $apiKey = env('GEMINI_API_KEY');
                            
                            // PROMPT DISEÑADOR + ESTRATEGA
                            $prompt = "
                                ROL: Consultor de Marketing Digital de Alto Nivel.
                                TAREA: Crear Auditoría Estratégica para {$project->domain_url}.
                                DATOS RASTREADOS: {$siteMapData}
                                COMPETENCIA: {$competidores}
                                
                                INSTRUCCIONES DE FORMATO (ESTRICTO):
                                - NO devuelvas bloques de texto planos.
                                - Usa TABLAS HTML para comparar datos.
                                - Usa 'Cards' o recuadros para los hallazgos importantes.
                                - Usa clases de Tailwind CSS para dar estilo (bg-gray-100, p-4, rounded, text-blue-600, etc.).

                                ESTRUCTURA REQUERIDA:
                                1. <div class='bg-blue-50 p-4 rounded'><h2>🔍 Diagnóstico de Identidad</h2>...</div>
                                2. Tabla de Brechas vs Competencia (Columnas: Tema | Estado Actual | Oportunidad).
                                3. Tabla de Keywords de Oro (Columnas: Keyword | Intención | Prioridad).
                                4. <div class='grid grid-cols-2 gap-4'>... (Tarjetas para los Pilares de Contenido) ...</div>
                            ";

                            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                                ->timeout(120)
                                ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey, [
                                    'contents' => [['parts' => [['text' => $prompt]]]],
                                    'generationConfig' => ['temperature' => 0.5]
                                ]);

                            $aiReport = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? null;
                            if (!$aiReport) throw new \Exception("IA vacía.");
                            
                            $aiReport = str_replace(['```html', '```'], '', $aiReport);

                            // GUARDAR Y RECARGAR
                            $project->forceFill(['seo_strategy' => $aiReport])->save();
                            Notification::make()->title('Radiografía Creada con Éxito')->success()->send();
                            return redirect(request()->header('Referer'));

                        } catch (\Exception $e) {
                            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                        }
                    }),

                // --- BOTÓN 2: VER (VISUALIZADOR) ---
                Tables\Actions\Action::make('view_strategy')
                    ->label('Ver Radiografía')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading('Estrategia Visual')
                    ->modalWidth('7xl')
                    ->action(function ($livewire) {
                         if (empty($livewire->getOwnerRecord()->fresh()->seo_strategy)) {
                             Notification::make()->title('Vacío')->body('Ejecuta el botón Rojo primero.')->warning()->send();
                         }
                    })
                    ->modalContent(function ($livewire) {
                        $strategy = $livewire->getOwnerRecord()->fresh()->seo_strategy;
                        if (empty($strategy)) return new HtmlString('<div class="p-4 text-center">⚠️ Nada guardado.</div>');
                        return new HtmlString("<div class='prose dark:prose-invert max-w-none'>{$strategy}</div>");
                    }),

                // --- BOTÓN 3: PROGRAMAR (CON RECIBO DETALLADO) ---
                Tables\Actions\Action::make('generate_calendar_db')
                    ->label('2. Programar Artículos')
                    ->icon('heroicon-o-calendar-days')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Generación Masiva')
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
                            $prompt = "ACTÚA COMO: API JSON. CONTEXTO: {$project->seo_strategy}. TAREA: {$postsA_Generar} ideas. JSON: [{\"title\":\"...\",\"keyword\":\"...\",\"intention\":\"...\"}]";

                            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                                ->timeout(60)
                                ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey, [
                                    'contents' => [['parts' => [['text' => $prompt]]]],
                                    'generationConfig' => ['temperature' => 0.5]
                                ]);

                            $jsonText = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? '[]';
                            $jsonText = str_replace(['```json', '```', '```'], '', $jsonText); 
                            $plan = json_decode($jsonText, true);

                            if (!is_array($plan)) throw new \Exception("Error JSON IA");

                            $currentDate = $fechaInicio->copy();
                            $listaCreada = "<ul>"; // Iniciar lista HTML para el reporte

                            foreach ($plan as $item) {
                                if ($frecuencia == 3) $currentDate->addDays(2); 
                                elseif ($frecuencia == 5) {
                                    $currentDate->addDay();
                                    if ($currentDate->isWeekend()) $currentDate->addDays(2);
                                } else $currentDate->addDays(3);
                                
                                // Guardar en BD
                                Article::create([
                                    'project_id' => $project->id,
                                    'title' => $item['title'],
                                    'keyword' => $item['keyword'] ?? 'General',
                                    'intention' => $item['intention'] ?? 'Informativa',
                                    'status' => 'pending', 
                                    'scheduled_date' => $currentDate->format('Y-m-d'),
                                ]);

                                // Agregar al reporte visual
                                $fechaFormato = $currentDate->format('d/m/Y');
                                $listaCreada .= "<li>✅ <strong>{$fechaFormato}</strong>: {$item['title']}</li>";
                            }
                            $listaCreada .= "</ul>";

                            // NOTIFICACIÓN PERSISTENTE CON LA LISTA
                            Notification::make()
                                ->title("¡Éxito! {$postsA_Generar} Artículos Programados")
                                ->body(new HtmlString("Se han añadido a la cola:<br>" . $listaCreada))
                                ->success()
                                ->persistent() // <--- ESTO HACE QUE NO DESAPAREZCA SOLO
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