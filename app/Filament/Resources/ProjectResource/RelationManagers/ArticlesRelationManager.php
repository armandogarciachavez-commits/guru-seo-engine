<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use App\Models\Article;
use Illuminate\Support\Facades\Http;

class ArticlesRelationManager extends RelationManager
{
    protected static string $relationship = 'articles';
    protected static ?string $recordTitleAttribute = 'title';
    protected static ?string $title = 'Artículos Generados';
    protected static ?string $icon = 'heroicon-m-document-text';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->label('Título'),
                
                Forms\Components\Select::make('status')
                    ->options([
                        'draft' => 'Borrador',
                        'published' => 'Publicado',
                    ])
                    ->required()
                    ->label('Estado'),

                Forms\Components\RichEditor::make('content')
                    ->columnSpanFull()
                    ->label('Contenido'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->limit(50),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'published' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d/m/Y H:i')
                    ->label('Fecha'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Crear Manualmente'),

                Tables\Actions\Action::make('generate_ai')
                    ->label('Generar con Gemini 2.5 Flash')
                    ->icon('heroicon-o-bolt')
                    ->color('warning')
                    ->form([
                        Forms\Components\TextInput::make('topic')
                            ->label('¿Sobre qué quieres escribir?')
                            ->placeholder('Ej: Estrategias de SEO 2026')
                            ->required(),
                        
                        Forms\Components\Select::make('tone')
                            ->label('Tono')
                            ->options([
                                'professional' => 'Profesional',
                                'casual' => 'Casual',
                                'persuasive' => 'Persuasivo',
                                'informative' => 'Informativo',
                            ])
                            ->default('professional'),
                    ])
                    ->action(function (array $data, $livewire) {
                        // 1. LA LÍNEA MÁGICA: Evita que el servidor mate el proceso
                        set_time_limit(300); // 5 minutos de vida
                        ini_set('max_execution_time', 300); 

                        $project = $livewire->getOwnerRecord();
                        
                        Notification::make()
                            ->title('Gemini trabajando...')
                            ->body('Generando contenido (esto puede tomar hasta 1 minuto)...')
                            ->warning()
                            ->persistent()
                            ->send();

                        try {
                            $apiKey = env('GEMINI_API_KEY');
                            
                            if (empty($apiKey)) {
                                throw new \Exception('Falta la API KEY en .env');
                            }

                            $prompt = "
                                Actúa como un experto SEO.
                                Escribe un artículo sobre: '{$data['topic']}'.
                                Tono: {$data['tone']}.
                                Longitud: Extensa (+1000 palabras).
                                Formato: HTML puro (h2, h3, p, ul, li). NO uses h1.
                                Devuelve SOLO el HTML limpio.
                            ";

                            // Usamos Flash porque es rápido y reduce riesgo de timeout
                            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

                            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                                ->timeout(120) // Esperamos hasta 2 min a Google
                                ->post($url, [
                                    'contents' => [['parts' => [['text' => $prompt]]]],
                                    'generationConfig' => [
                                        'temperature' => 0.7,
                                        'maxOutputTokens' => 8192,
                                    ]
                                ]);

                            if ($response->failed()) {
                                throw new \Exception('Error Google: ' . $response->body());
                            }

                            $json = $response->json();
                            $aiContent = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';

                            if (empty($aiContent)) {
                                throw new \Exception('Respuesta vacía de Gemini');
                            }

                            $aiContent = str_replace(['```html', '```'], '', $aiContent);

                            Article::create([
                                'project_id' => $project->id,
                                'title' => $data['topic'],
                                'content' => $aiContent,
                                'status' => 'draft',
                                'is_published' => false,
                            ]);

                            Notification::make()
                                ->title('¡Éxito!')
                                ->body('Artículo generado correctamente.')
                                ->success()
                                ->send();

                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\Action::make('publish')
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->color('success')
                    ->action(function (Article $record) {
                        $record->update(['status' => 'published']);
                    })
                    ->visible(fn (Article $record) => $record->status !== 'published'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}