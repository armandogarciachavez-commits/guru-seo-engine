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

    protected static ?string $title = 'Artículos Generados';
    protected static ?string $icon = 'heroicon-m-document-text';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->label('Título')
                    ->required()
                    ->columnSpanFull(),
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
                Tables\Columns\TextColumn::make('scheduled_date')->label('Fecha')->date()->sortable(),
                Tables\Columns\TextColumn::make('title')->label('Título')->limit(30),
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
                // --- AQUÍ ESTÁ LA BALA DE PLATA CONTRA EL ZOMBI ---
                Tables\Actions\Action::make('write_article_relation')
                    ->label('Redactar IA')
                    ->icon('heroicon-o-sparkles')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->action(function (Article $record) {
                        set_time_limit(120);
                        
                        $project = $this->getOwnerRecord();
                        $contexto = $project->seo_strategy ?? 'Negocio local.';
                        $ciudad = $project->target_city ?? 'Local';

                        try {
                            $apiKey = env('GEMINI_API_KEY');
                            $prompt = "ROL: Redactor SEO. TAREA: Escribir artículo de 1000 palabras. TEMA: {$record->title}. CIUDAD: {$ciudad}. CONTEXTO: {$contexto}. FORMATO: HTML.";

                            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                                ->timeout(120)
                                ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey, [
                                    'contents' => [['parts' => [['text' => $prompt]]]],
                                    'generationConfig' => ['temperature' => 0.7]
                                ]);

                            $content = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? null;
                            if (!$content) throw new \Exception("IA vacía");
                            
                            $record->update(['content' => str_replace(['```html', '```'], '', $content), 'status' => 'generated']);
                            
                            Notification::make()->title('Redactado con éxito')->success()->send();

                        } catch (\Exception $e) {
                            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                        }
                    }),
                // ---------------------------------------------------

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
