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

    // Título en mayúsculas para confirmar visualmente el cambio
    protected static ?string $title = 'ARTÍCULOS SEO (VERSIÓN FINAL)';
    
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
                        Forms\Components\DatePicker::make('scheduled_date')
                            ->label('Fecha'),
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
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                // --- BOTÓN IA (Lógica Simplificada para evitar errores de sintaxis) ---
                Tables\Actions\Action::make('write_article_ai')
                    ->label('Redactar con IA')
                    ->icon('heroicon-o-sparkles')
                    ->color('info')
                    ->requiresConfirmation()
                    ->action(function (Article $record, $livewire) {
                        // 1. Obtener datos
                        $project = $livewire->getOwnerRecord();
                        $ciudad = $project->target_city ?? 'Local';
                        $contexto = $project->seo_strategy ?? 'Negocio Profesional';

                        try {
                            // 2. Llamada API
                            $apiKey = env('GEMINI_API_KEY');
                            if (!$apiKey) throw new \Exception("Falta la API Key en .env");

                            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                                ->timeout(60)
                                ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey, [
                                    'contents' => [['parts' => [['text' => 
                                        "Escribe un artículo de blog de 800 palabras sobre: {$record->title}. 
                                        Enfoque: SEO Local para {$ciudad}. 
                                        Contexto: {$contexto}.
                                        Formato: HTML (h2, h3, p, ul). Sin markdown."
                                    ]]]],
                                ]);

                            $json = $response->json();
                            $texto = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;

                            if (!$texto) throw new \Exception("La IA no respondió nada.");

                            // 3. Guardar
                            $record->update([
                                'content' => str_replace(['```html', '```'], '', $texto),
                                'status' => 'generated'
                            ]);

                            Notification::make()->title('Éxito')->body('Artículo redactado')->success()->send();

                        } catch (\Exception $e) {
                            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                        }
                    }),
                
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}