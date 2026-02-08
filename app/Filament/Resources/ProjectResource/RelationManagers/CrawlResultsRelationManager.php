<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Http;
use App\Models\CrawlResult;
use Illuminate\Support\HtmlString;

class CrawlResultsRelationManager extends RelationManager
{
    protected static string $relationship = 'crawlResults';

    protected static ?string $recordTitleAttribute = 'title';
    protected static ?string $title = 'Inteligencia de Sitio (Crawler)';
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
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('url')
                    ->label('URL')
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->url),

                Tables\Columns\TextColumn::make('title')
                    ->label('Meta Título')
                    ->limit(40)
                    ->searchable(),

                Tables\Columns\TextColumn::make('word_count')
                    ->label('Palabras')
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('analyze_mega_architect')
                    ->label('EJECUTAR MEGA AUDITORÍA 360°')
                    ->icon('heroicon-o-rocket-launch')
                    ->color('danger') // Rojo para denotar potencia máxima
                    ->modalHeading('Configuración de la Auditoría Autónoma')
                    ->modalDescription('Este proceso analizará toda la estructura rastreada y generará un plan estratégico completo. Puede tardar hasta 2 minutos.')
                    ->modalWidth('7xl')
                    ->form([
                        // CAPTURAMOS LOS DATOS FALTANTES PARA TU PROMPT
                        Forms\Components\Textarea::make('competitors')
                            ->label('Competidores Conocidos (Opcional)')
                            ->placeholder('Ej: competencia1.com, elgigante.mx')
                            ->rows(2),
                        Forms\Components\Select::make('frequency')
                            ->label('Capacidad de Publicación')
                            ->options([
                                '1 semanal' => 'Baja (1 post/semana)',
                                '3 semanales' => 'Media (3 posts/semana)',
                                'Diario' => 'Alta (Diario)',
                            ])
                            ->default('3 semanales')
                            ->required(),
                    ])
                    ->action(function ($data, $livewire) {
                        // Vacío intencionalmente
                    })
                    ->modalContent(function ($livewire, $data) {
                        
                        // 1. OBTENER DATOS (Materia Prima)
                        $project = $livewire->getOwnerRecord();
                        $results = $project->crawlResults;

                        if ($results->isEmpty()) {
                            return new HtmlString('<p class="text-danger font-bold">⚠️ Sin datos. Ejecuta el Rastreo primero.</p>');
                        }

                        // Preparamos la "Foto del Sitio" (Hasta 60 URLs para dar contexto sólido)
                        $siteMapData = $results->where('status_code', 200)->take(60)->map(function($r) {
                            $h1 = $r->h1 ?: '(Sin H1)';
                            $title = $r->title ?: '(Sin Title)';
                            $words = $r->word_count ?? 0;
                            return "URL: {$r->url} | H1: {$h1} | Title: {$title} | Palabras: {$words}";
                        })->implode("\n");

                        // Variables del Proyecto
                        $ciudad = $project->target_city ?? 'Ubicación del cliente';
                        $competidores = $data['competitors'] ?? 'Líderes del mercado local';
                        $frecuencia = $data['frequency'] ?? '3 artículos semanales';
                        $nombreNegocio = $project->name;

                        try {
                            $apiKey = env('GEMINI_API_KEY');
                            
                            // 2. EL PROMPT MAESTRO (Tu visión hecha código)
                            $prompt = "
                                ROL: Actúa como un SISTEMA EXPERTO EN SEO TÉCNICO Y ESTRATEGIA DE CONTENIDOS AUTÓNOMA.
                                Tu objetivo no es dar consejos, es entregar un PLAN DE EJECUCIÓN DETALLADO.

                                --- DATOS DE ENTRADA ---
                                SITIO WEB: {$project->domain_url}
                                UBICACIÓN: {$ciudad}
                                COMPETIDORES A VENCER: {$competidores}
                                CAPACIDAD DE PRODUCCIÓN: {$frecuencia}
                                
                                RADIOGRAFÍA ACTUAL DEL SITIO (Datos Rastreados):
                                {$siteMapData}

                                --- INSTRUCCIONES DE EJECUCIÓN (FASES 1-6) ---

                                FASE 1: DIAGNÓSTICO DE ARQUITECTURA
                                - Analiza la lista de URLs de arriba. ¿Tiene sentido la estructura?
                                - Detecta 'Thin Content' (páginas con <300 palabras).
                                - Detecta canibalización (títulos muy parecidos).
                                
                                FASE 2: AUDITORÍA TÉCNICA PRIORIZADA (Tabla)
                                Genera una tabla con 3 columnas: [Problema Detectado] | [Impacto (Alto/Medio)] | [Acción Técnica Exacta].
                                *Nota: Basa esto en los H1 faltantes, títulos duplicados o URLs sin contenido que ves en los datos.*

                                FASE 3: ANÁLISIS DE NICHO Y BRECHAS
                                - ¿Qué vende realmente este sitio? (Define el 'Money Site').
                                - ¿Qué están vendiendo los competidores ({$competidores}) que este sitio NO tiene?
                                - Identifica 3 Sub-nichos explotables en {$ciudad}.

                                FASE 4: INVESTIGACIÓN DE KEYWORDS (Tabla)
                                Genera una tabla: [Keyword Objetivo] | [Intención (Comercial/Info)] | [Página Sugerida (Nueva o Existente)].
                                *Enfócate en términos que traigan ventas en {$ciudad}.*

                                FASE 5: ESTRATEGIA DE CONTENIDO (CALENDARIO)
                                Crea un plan para el Primer Mes ({$frecuencia}):
                                - Semana 1: [Título del Post] (Objetivo: Tráfico/Venta)
                                - Semana 2: [Título del Post] ...
                                - Semana 3: [Título del Post] ...
                                - Semana 4: [Título del Post] ...

                                FASE 6: GENERACIÓN DE CONTENIDO PILOTO
                                Elige la MEJOR idea de la Fase 5 y desarróllala aquí brevemente:
                                - Título Optimizado (H1)
                                - Meta Descripción (con CTA)
                                - Estructura del artículo (H2, H3)
                                - Pregunta Frecuente (Schema FAQ)

                                --- FORMATO DE SALIDA ---
                                Usa HTML estricto.
                                Usa <h2> para los títulos de Fase.
                                Usa tablas de Tailwind para las secciones requeridas (<table class='w-full text-sm text-left border...'>).
                                Sé profesional, directo y estratégico. Habla en nombre de Guru Marketing.
                            ";

                            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                                ->timeout(300) // <--- ¡AQUÍ ESTÁ EL FIX! (5 Minutos de espera)
                                ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey, [
                                    'contents' => [['parts' => [['text' => $prompt]]]],
                                    'generationConfig' => [
                                        'temperature' => 0.4, 
                                        'maxOutputTokens' => 8192, 
                                    ]
                                ]);

                            $aiReport = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? 'Error en análisis.';
                            
                            // Limpieza
                            $aiReport = str_replace(['```html', '```'], '', $aiReport);
                            
                            // Estilos de Tablas CSS Inline
                            $aiReport = str_replace('<table>', '<table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 my-4 border border-gray-200 rounded-lg overflow-hidden">', $aiReport);
                            $aiReport = str_replace('<thead>', '<thead class="bg-gray-50 dark:bg-gray-800">', $aiReport);
                            $aiReport = str_replace('<th>', '<th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider border-b">', $aiReport);
                            $aiReport = str_replace('<td>', '<td class="px-6 py-4 whitespace-normal text-sm text-gray-800 dark:text-gray-200 border-b border-gray-100 dark:border-gray-700">', $aiReport);

                            return new HtmlString("<div class='prose dark:prose-invert max-w-none space-y-6'>{$aiReport}</div>");

                        } catch (\Exception $e) {
                            return new HtmlString("<p class='text-red-500 font-bold'>Error de conexión IA: {$e->getMessage()}</p>");
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}