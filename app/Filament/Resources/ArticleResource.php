<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ArticleResource\Pages;
use App\Models\Article;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Http;
use Filament\Notifications\Notification;

class ArticleResource extends Resource
{
    protected static ?string $model = Article::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Artículos SEO';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->label('Título')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('keyword')
                            ->label('Palabra Clave'),
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pendiente',
                                'generated' => 'Redactado',
                                'published' => 'Publicado',
                            ])
                            ->default('pending'),
                        Forms\Components\DatePicker::make('scheduled_date')
                            ->label('Fecha Programada'),
                    ]),
                // EDITOR DE TEXTO ENRIQUECIDO
                Forms\Components\RichEditor::make('content')
                    ->label('Contenido del Artículo')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('scheduled_date')
                    ->label('Fecha')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->limit(40)
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'generated' => 'warning',
                        'published' => 'success',
                        default => 'gray',
                    }),
            ])
            ->actions([
                // 1. EL BOTÓN MÁGICO
                Tables\Actions\Action::make('write_article')
                    ->label('Redactar con IA')
                    ->icon('heroicon-o-sparkles')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Redactar Artículo Completo')
                    ->modalDescription('La IA usará los datos de contacto reales del proyecto para cerrar el artículo.')
                    ->action(function (Article $record) {
                        // TIEMPO EXTRA
                        set_time_limit(120); 
                        
                        // --- 1. OBTENER DATOS DEL PROYECTO ---
                        $project = $record->project;
                        
                        // Datos básicos
                        $contexto = $project->seo_strategy ?? 'Negocio local profesional.';
                        $ciudad = $project->target_city ?? 'Local';
                        $projectUrl = $project->domain_url; 

                        // Datos de contacto (con valores por defecto si están vacíos)
                        $phone = $project->phone ?? 'No especificado';
                        $email = $project->email ?? 'No especificado';
                        $address = $project->address ?? 'No especificado';

                        try {
                            $apiKey = env('GEMINI_API_KEY');
                            
                            // --- 2. EL PROMPT MAESTRO ---
                           $prompt = "
						ROL: Redactor SEO Senior experto en marketing local y copywriting de conversión.
						OBJETIVO: Crear un artículo que ayude de verdad al lector, posicione en Google y convierta.

						DATOS DEL ARTÍCULO:
						- Título: '{$record->title}'
						- Keyword principal: '{$record->keyword}'
						- Ciudad objetivo: '{$ciudad}'

						DATOS DE CONTACTO REALES (USAR AL FINAL):
						- Nombre del negocio: '{$project->name}'
						- Sitio web (URL base para enlaces internos): '{$projectUrl}'
						- Teléfono/WhatsApp: '{$phone}'
						- Email: '{$email}'
						- Dirección: '{$address}'

						CONTEXTO DE MARCA (voz, servicios, ventajas, restricciones):
						{$contexto}

						REGLAS OBLIGATORIAS:
						1) Longitud total: 850 a 1050 palabras (no menos de 850).
						2) SALIDA: SOLO HTML puro. Prohibido Markdown. Prohibido backticks. Usa solo: <h2>, <h3>, <p>, <ul>, <li>, <strong>, <a>.
						3) Estilo: párrafos cortos, lectura fácil, tono profesional y cercano. Español neutro.
						4) Intención de búsqueda: orienta el artículo a resolver dudas prácticas relacionadas con la keyword y la ciudad.
						5) Keyword:
						   - Debe aparecer en los primeros 100 caracteres.
						   - Densidad máxima: 1.5% (no repetirla de forma forzada).
						   - Usa variaciones y términos relacionados de forma natural (sin listas de keywords).
						6) Estructura obligatoria:
						   - 1 intro (sin h2)
						   - 4 a 6 secciones <h2>
						   - En al menos 2 secciones usa <h3> para subsecciones.
						   - Incluir 1 lista <ul> con pasos, checklist o recomendaciones accionables.
						   - Incluir una sección de FAQs con 4 preguntas y respuestas (en <h2> y <h3>).
						7) Contenido útil real:
						   - Evita relleno y generalidades. Cada sección debe aportar una idea práctica, ejemplo o recomendación.
						   - Prohibido usar frases vacías como: 'hoy en día', 'en el mundo actual', 'es importante mencionar', 'sin duda alguna'.
						   - No inventes datos específicos (años, premios, estadísticas, números exactos) si no están en el CONTEXTO DE MARCA.
						8) Enlaces internos:
						   - Si incluyes enlaces, deben usar ÚNICAMENTE la base '{$projectUrl}' (mismo dominio).
						   - Máximo 2 enlaces internos.
						   - Los enlaces deben ser útiles (no forzados) y con anchor text descriptivo.
						   - Ejemplo permitido: <a href='{$projectUrl}/servicios'>servicios</a>
						   - Prohibido inventar otros dominios.

						SALIDA EN HTML (FORMATO EXACTO):
						A) Al inicio del HTML, agrega un bloque de comentarios con:
						   <!--
						   META_TITLE: (máx 60 caracteres, incluye keyword + ciudad)
						   META_DESCRIPTION: (150-160 caracteres, incluye keyword + ciudad, con beneficio claro)
						   SLUG: (minúsculas, sin acentos, con guiones, basado en keyword + ciudad)
						   -->
						B) Luego el contenido del artículo con la estructura solicitada.

						CIERRE OBLIGATORIO (CALL TO ACTION):
						- Termina SIEMPRE con un <h2> llamado 'Visítanos' o 'Contáctanos'.
						- Debe invitar a la acción sin sonar agresivo.
						- Incluye el enlace al sitio web.
						- Incluye explícitamente solo los datos que NO sean 'No especificado':
						   Teléfono/WhatsApp, Dirección, Email.
						- No menciones campos con 'No especificado'.

						IMPORTANTE: Devuelve SOLO el HTML final, sin explicaciones.
						";


                            $response = Http::withHeaders(['Content-Type' => 'application/json'])
    ->timeout(120)
    ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . $apiKey, [
        'contents' => [['parts' => [['text' => $prompt]]]], // <--- ¡AQUÍ FALTABA LA COMA!
        'generationConfig' => [                              // <--- Solo una comilla simple '
            'temperature' => 0.5,
            'topP' => 0.9,
            'maxOutputTokens' => 2048
        ]
    ]);

                            $content = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? null;

                            if (!$content) throw new \Exception("La IA no devolvió texto.");

                            $content = str_replace(['```html', '```'], '', $content);

                            $record->update([
                                'content' => $content,
                                'status' => 'generated'
                            ]);

                            Notification::make()->title('¡Artículo Creado!')->body('Se incluyeron los datos de contacto correctos.')->success()->send();

                        } catch (\Exception $e) {
                            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                        }
                    }),

                // 2. BOTONES ESTÁNDAR
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
    
    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArticles::route('/'),
            'create' => Pages\CreateArticle::route('/create'),
            'edit' => Pages\EditArticle::route('/{record}/edit'),
        ];
    }
}