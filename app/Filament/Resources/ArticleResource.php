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
                                ROL: Redactor SEO Senior experto en marketing local.
                                TAREA: Escribir un artículo de blog atractivo y persuasivo.
                                
                                DATOS DEL ARTÍCULO: 
                                - Título: '{$record->title}'
                                - Keyword: '{$record->keyword}'
                                - Ciudad Objetivo: '{$ciudad}'
                                
                                DATOS DE CONTACTO REALES (USAR AL FINAL):
                                - Nombre del Negocio: '{$project->name}'
                                - Sitio Web: '{$projectUrl}'
                                - Teléfono/WhatsApp: '{$phone}'
                                - Email: '{$email}'
                                - Dirección: '{$address}'
                                
                                CONTEXTO DE MARCA: 
                                {$contexto}
                                
                                REGLAS OBLIGATORIAS DE REDACCIÓN: 
                                1. Longitud: 800 a 1000 palabras.
                                2. Formato: HTML puro (<h2>, <h3>, <p>, <ul>). NO uses Markdown.
                                3. Estilo: Párrafos cortos, lectura fácil, tono profesional pero cercano.
                                4. ENLACES: Si incluyes enlaces internos, usa EXCLUSIVAMENTE la base: '{$projectUrl}'. PROHIBIDO inventar otros dominios.
                                
                                5. CIERRE OBLIGATORIO (CALL TO ACTION):
                                   Debes terminar el artículo con una sección h2 llamada 'Visítanos' o 'Contáctanos'.
                                   En esta sección, invita al cliente a actuar.
                                   INCLUYE EXPLÍCITAMENTE los datos de contacto provistos arriba (Teléfono, Dirección, Email) si son diferentes a 'No especificado'.
                                   Si el dato es 'No especificado', no lo menciones.
                                   Asegúrate de poner el enlace al sitio web.
                                
                                SALIDA: Solo el código HTML del artículo.
                            ";

                            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                                ->timeout(120)
                                ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . $apiKey, [
                                    'contents' => [['parts' => [['text' => $prompt]]]],
                                    'generationConfig' => ['temperature' => 0.7]
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