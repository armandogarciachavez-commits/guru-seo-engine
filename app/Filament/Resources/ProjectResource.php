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
                // EDITOR DE TEXTO ENRIQUECIDO PARA VER EL RESULTADO
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
                Tables\Actions\EditAction::make(),
                
                // --- AQUÍ ESTÁ EL BOTÓN QUE BUSCAS ---
                Tables\Actions\Action::make('write_article')
                    ->label('Redactar con IA')
                    ->icon('heroicon-o-sparkles')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Redactar Artículo Completo')
                    ->modalDescription('La IA escribirá ~1000 palabras optimizadas. Esto tomará unos 30 segundos.')
                    ->action(function (Article $record) {
                        // 1. Tiempo extra
                        set_time_limit(120); 
                        
                        // 2. Contexto
                        $project = $record->project;
                        $contexto = $project->seo_strategy ?? 'Empresa local profesional.';
                        $ciudad = $project->target_city ?? 'Local';

                        try {
                            $apiKey = env('GEMINI_API_KEY');
                            
                            // 3. Prompt
                            $prompt = "
                                ROL: Redactor SEO Senior.
                                TAREA: Escribir artículo completo.
                                DATOS: Título: '{$record->title}', Keyword: '{$record->keyword}', Ciudad: '{$ciudad}'.
                                CONTEXTO: {$contexto}
                                REGLAS: 800-1000 palabras. Estructura HTML (h2, h3, p, ul). Tono profesional.
                                SALIDA: Solo HTML.
                            ";

                            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                                ->timeout(120)
                                ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey, [
                                    'contents' => [['parts' => [['text' => $prompt]]]],
                                    'generationConfig' => ['temperature' => 0.7]
                                ]);

                            $content = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? null;

                            if (!$content) throw new \Exception("IA vacía.");

                            $content = str_replace(['```html', '```'], '', $content);

                            $record->update([
                                'content' => $content,
                                'status' => 'generated'
                            ]);

                            Notification::make()->title('¡Artículo Redactado!')->success()->send();

                        } catch (\Exception $e) {
                            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                        }
                    }),
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