<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\Article;
use Illuminate\Support\Facades\Http;
use Filament\Notifications\Notification;

class ArticlesRelationManager extends RelationManager
{
    protected static string $relationship = 'articles';

    protected static ?string $title = 'Calendario Editorial & Redacción';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->label('Título del Artículo'),

                Forms\Components\TextInput::make('keyword')
                    ->label('Palabra Clave'),

                Forms\Components\DatePicker::make('scheduled_date')
                    ->label('Fecha de Publicación')
                    ->required(), // Opcional: hazlo requerido si quieres forzar una fecha

                Forms\Components\Select::make('status')
                    ->options([
                        'pending' => 'Pendiente',
                        'generated' => 'Redactado',
                        'published' => 'Publicado',
                    ])
                    ->default('pending'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            // ORDENAR: Mostrar primero los próximos a salir
            ->defaultSort('scheduled_date', 'asc') 
            ->columns([
                // 1. FECHA (Lo que faltaba)
                Tables\Columns\TextColumn::make('scheduled_date')
                    ->label('Programado')
                    ->date('d M, Y') // Ej: 10 Feb, 2026
                    ->sortable()
                    ->icon('heroicon-m-calendar'),

                // 2. TÍTULO
                Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->limit(30),

                // 3. ESTADO
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
                Tables\Actions\CreateAction::make()
                    ->label('Programar Nuevo Post'),
            ])
            ->actions([
                // Acción de Redactar con IA (Copiada del Resource principal para tenerla aquí también)
                Tables\Actions\Action::make('write_article')
                    ->label('IA')
                    ->icon('heroicon-o-sparkles')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->action(function (Article $record) {
                        // TIEMPO EXTRA
                        set_time_limit(120);
                        
                        // OBTENER DATOS
                        $project = $this->getOwnerRecord(); // En RelationManager, el dueño es el Proyecto
                        $projectUrl = $project->domain_url;
                        
                        // Datos contacto
                        $phone = $project->phone ?? 'No especificado';
                        $email = $project->email ?? 'No especificado';
                        $address = $project->address ?? 'No especificado';

                        try {
                            $apiKey = env('GEMINI_API_KEY');
                            
                            $prompt = "
                                ROL: Redactor SEO.
                                TÍTULO: '{$record->title}'
                                KEYWORD: '{$record->keyword}'
                                DATOS CONTACTO: {$project->name}, {$phone}, {$email}, {$address}, {$projectUrl}.
                                
                                INSTRUCCIÓN: Escribe un artículo de blog en HTML puro (800 palabras).
                                CIERRE: Incluye un llamado a la acción con los datos de contacto y el enlace al sitio ({$projectUrl}).
                            ";

                            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                                ->timeout(120)
                                ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . $apiKey, [
                                    'contents' => [['parts' => [['text' => $prompt]]]],
                                    'generationConfig' => ['temperature' => 0.7]
                                ]);

                            $content = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? null;

                            if (!$content) throw new \Exception("Error IA");

                            $content = str_replace(['```html', '```'], '', $content);

                            $record->update([
                                'content' => $content,
                                'status' => 'generated'
                            ]);

                            Notification::make()->title('Redactado con éxito')->success()->send();

                        } catch (\Exception $e) {
                            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}