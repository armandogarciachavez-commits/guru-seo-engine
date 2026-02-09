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

    protected static ?string $title = 'Artículos Generados'; // Título profesional restaurado
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
                            ->label('Fecha Programada'),
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
                // 1. TÍTULO (Primero, como debe ser)
                Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->limit(50)
                    ->searchable()
                    ->weight('bold'), // Negrita para que destaque

                // 2. ESTADO (Con colores bonitos)
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',     // Pendiente
                        'generated' => 'warning', // Redactado (Amarillo)
                        'published' => 'success', // Publicado (Verde)
                        default => 'gray',
                    }),

                // 3. FECHA (Al final)
                Tables\Columns\TextColumn::make('scheduled_date')
                    ->label('Fecha')
                    ->date('d/m/Y') // Formato día/mes/año
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Crear Manualmente'),
            ])
            ->actions([
                // --- BOTÓN IA QUE YA FUNCIONA ---
                Tables\Actions\Action::make('write_article_ai')
                    ->label('Redactar IA')
                    ->icon('heroicon-o-sparkles')
                    ->color('info') // Azulito
                    ->requiresConfirmation()
                    ->modalHeading('¿Redactar este artículo?')
                    ->modalDescription('La IA generará el contenido basándose en el título y la ciudad del proyecto.')
                    ->action(function (Article $record, $livewire) {
                        // 1. Obtener datos
                        $project = $livewire->getOwnerRecord();
                        $ciudad = $project->target_city ?? 'Local';
                        $contexto = $project->seo_strategy ?? 'Negocio Profesional';

                        try {
                            // 2. Llamada API
                            $apiKey = env('GEMINI_API_KEY');
                            
                            $prompt = "
                                Escribe un artículo de blog de 800 palabras.
                                Título: {$record->title}
                                Enfoque: SEO Local para {$ciudad}. 
                                Contexto: {$contexto}.
                                Formato: HTML estricto (h2, h3, p, ul).
                                Tono: Profesional y persuasivo.
                            ";

                            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                                ->timeout(60)
                                ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey, [
                                    'contents' => [['parts' => [['text' => $prompt]]]],
                                ]);

                            $json = $response->json();
                            $texto = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;

                            if (!$texto) throw new \Exception("La IA no respondió.");

                            // 3. Guardar
                            $record->update([
                                'content' => str_replace(['```html', '```'], '', $texto),
                                'status' => 'generated'
                            ]);

                            Notification::make()->title('¡Artículo Redactado!')->success()->send();

                        } catch (\Exception $e) {
                            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                        }
                    }),
                // --------------------------------
                
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}