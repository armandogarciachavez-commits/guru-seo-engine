<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Http;
use Filament\Notifications\Notification;
use App\Models\Article;

class ArticlesRelationManager extends RelationManager
{
    protected static string $relationship = 'articles';

    protected static ?string $title = 'Calendario Editorial & Redacción'; // Título más descriptivo
    protected static ?string $icon = 'heroicon-m-calendar-days';

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
                        Forms\Components\DateTimePicker::make('scheduled_date') // DateTimePicker para elegir hora también
                            ->label('Fecha y Hora de Publicación')
                            ->required(),
                    ]),

                Forms\Components\RichEditor::make('content')
                    ->label('Contenido')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                // 1. TÍTULO
                Tables\Columns\TextColumn::make('title')
                    ->label('Título del Artículo')
                    ->limit(40)
                    ->searchable()
                    ->weight('bold')
                    ->tooltip(fn (Article $record): string => $record->title),

                // 2. ESTADO
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'generated' => 'warning',
                        'published' => 'success',
                        default => 'gray',
                    }),

                // 3. FECHA Y HORA (Formato solicitado)
                Tables\Columns\TextColumn::make('scheduled_date')
                    ->label('Fecha Programada')
                    ->dateTime('d/m/Y h:i A') // Ej: 10/02/2026 04:30 PM
                    ->sortable()
                    ->icon('heroicon-m-calendar'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Agregar Manualmente'),
            ])
            ->actions([
                // --- BOTÓN IA ---
                Tables\Actions\Action::make('write_article_ai')
                    ->label('Redactar IA')
                    ->icon('heroicon-o-sparkles')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Generar Contenido')
                    ->action(function (Article $record, $livewire) {
                        $project = $livewire->getOwnerRecord();
                        $ciudad = $project->target_city ?? 'Local';
                        $contexto = $project->seo_strategy ?? 'Negocio Profesional';

                        try {
                            $apiKey = env('GEMINI_API_KEY');
                            
                            $prompt = "
                                ACTÚA COMO: Redactor SEO Senior.
                                TAREA: Escribir artículo de blog de 800-1000 palabras.
                                TÍTULO: {$record->title}
                                CIUDAD OBJETIVO: {$ciudad}.
                                CONTEXTO NEGOCIO: {$contexto}.
                                FORMATO: HTML limpio (h2, h3, p, ul).
                                TONO: Persuasivo, experto y optimizado para conversión.
                            ";

                            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                                ->timeout(60)
                                ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey, [
                                    'contents' => [['parts' => [['text' => $prompt]]]],
                                ]);

                            $texto = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? null;
                            if (!$texto) throw new \Exception("Sin respuesta de IA");

                            $record->update([
                                'content' => str_replace(['```html', '```'], '', $texto),
                                'status' => 'generated'
                            ]);

                            Notification::make()->title('Contenido Generado')->success()->send();

                        } catch (\Exception $e) {
                            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                        }
                    }),
                
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            // --- AQUÍ ESTÁ LA ACCIÓN EN LOTE (BULK ACTIONS) ---
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Borrar Seleccionados'),
                ]),
            ]);
    }
}