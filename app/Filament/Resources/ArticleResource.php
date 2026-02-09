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
                // 1. EL BOTÓN MÁGICO (Primero para que se vea)
                Tables\Actions\Action::make('write_article')
                    ->label('Redactar con IA')
                    ->icon('heroicon-o-sparkles')
                    ->color('primary') // Color Azul/Dorado según tema
                    ->requiresConfirmation()
                    ->modalHeading('Redactar Artículo Completo')
                    ->modalDescription('La IA escribirá ~1000 palabras optimizadas para este título. Tardará unos 30-40 segundos.')
                    ->action(function (Article $record) {
                        // TIEMPO EXTRA
                        set_time_limit(120); 
                        
                        // DATOS
                        $project = $record->project;
                        // Usamos fallback por seguridad
                        $contexto = $project->seo_strategy ?? 'Negocio local profesional.';
                        $ciudad = $project->target_city ?? 'Local';

                        try {
                            $apiKey = env('GEMINI_API_KEY');
                            
                            $prompt = "
                                ROL: Redactor SEO Senior.
                                TAREA: Escribir artículo completo de blog.
                                DATOS: 
                                - Título: '{$record->title}'
                                - Keyword: '{$record->keyword}'
                                - Ciudad: '{$ciudad}'
                                
                                CONTEXTO MARCA: 
                                {$contexto}
                                
                                REGLAS OBLIGATORIAS: 
                                1. Longitud: 800 a 1000 palabras.
                                2. Formato: HTML puro (<h2>, <h3>, <p>, <ul>). NO uses Markdown.
                                3. Estilo: Párrafos cortos, fácil de leer.
                                4. Cierre: Call to Action (CTA) invitando a contactar.
                                
                                SALIDA: Solo el código HTML.
                            ";

                            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                                ->timeout(120)
                                ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey, [
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

                            Notification::make()->title('¡Artículo Creado!')->body('Revisa el contenido en Editar.')->success()->send();

                        } catch (\Exception $e) {
                            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                        }
                    }),

                // 2. BOTONES ESTÁNDAR
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
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