<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Http;
use Filament\Notifications\Notification;
use App\Models\Article; // Importante para que reconozca el modelo

class ArticlesRelationManager extends RelationManager
{
    protected static string $relationship = 'articles';

    protected static ?string $title = 'Artículos Generados'; // El título que veías en tu captura
    protected static ?string $icon = 'heroicon-m-document-text';

    public function form(Form $form): Form
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
                // IMPORTANTE: El editor para ver el resultado
                Forms\Components\RichEditor::make('content')
                    ->label('Contenido (Generado por IA)')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('scheduled_date')
                    ->label('Fecha')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->limit(30)
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
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                // --- BOTÓN 1: REDACTAR CON IA ---
                Tables\Actions\Action::make('write_article_relation')
                    ->label('Redactar con IA')
                    ->icon('heroicon-o-sparkles')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Redactar Artículo')
                    ->modalDescription('La IA escribirá el contenido optimizado para este título. Tardará unos 30 segundos.')
                    ->action(function (Article $record) {
                        // TIEMPO EXTRA
                        set_time_limit(120);
                        
                        // CONTEXTO
                        $project = $this->getOwnerRecord(); // En RelationManager, el dueño es el proyecto
                        $contexto = $project->seo_strategy ?? 'Negocio local profesional.';
                        $ciudad = $project->target_city ?? 'Local';

                        try {
                            $apiKey = env('GEMINI_API_KEY');
                            
                            $prompt = "
                                ROL: Redactor SEO Senior.
                                TAREA: Escribir artículo de blog.
                                DATOS: Título: '{$record->title}', Keyword: '{$record->keyword}', Ciudad: '{$ciudad}'.
                                CONTEXTO MARCA: {$contexto}
                                REGLAS: 
                                1. Longitud 800-1000 palabras.
                                2. Usa HTML (h2, h3, p, ul).
                                3. Tono persuasivo.
                                SALIDA: Solo código HTML.
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

                // BOTONES NORMALES
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}