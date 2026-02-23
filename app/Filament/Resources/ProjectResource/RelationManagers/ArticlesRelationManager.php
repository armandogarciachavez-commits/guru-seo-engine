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
use App\Jobs\WriteArticleJob;
use App\Jobs\GenerateArticlesJob;

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
                    ->required(),

                Forms\Components\Select::make('status')
                    ->options([
                        'pending' => 'Pendiente',
                        'generated' => 'Redactado',
                        'published' => 'Publicado',
                    ])
                    ->default('pending'),

                Forms\Components\RichEditor::make('content')
                    ->label('Contenido (IA)')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('scheduled_date', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('scheduled_date')
                    ->label('Programado')
                    ->date('d M, Y')
                    ->sortable()
                    ->icon('heroicon-m-calendar'),

                Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->limit(30),

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
                Tables\Actions\CreateAction::make()
                    ->label('Programar Nuevo Post'),
                
                // BOTÓN GENERADOR MASIVO (El botón verde)
                Tables\Actions\Action::make('generate_calendar_db')
                    ->label('2. Programar Artículos')
                    ->icon('heroicon-o-calendar-days')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Generación Masiva')
                    ->form([
                        Forms\Components\Select::make('frequency')
                            ->label('Frecuencia')
                            ->options(['2'=>'2/sem', '3'=>'3/sem', '5'=>'Diario'])
                            ->default('3')
                            ->required(),
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Inicio')
                            ->default(now()->addDay())
                            ->required(),
                    ])
                    ->action(function ($data, $livewire) {
                        $project = $livewire->getOwnerRecord();
                        
                        if (empty($project->seo_strategy)) {
                            Notification::make()->title('Error')->body('Falta la radiografía.')->danger()->send();
                            return;
                        }

                        try {
                            GenerateArticlesJob::dispatch(
    							$project, 
    							(int) $data['frequency'], 
    							$data['start_date']
								)->onConnection('database'); // <--- EL MARTILLAZO 🔨

                            Notification::make()
                                ->title("Programación Masiva Iniciada")
                                ->body("Ejecuta el worker en la consola.")
                                ->success()
                                ->persistent()
                                ->send();

                        } catch (\Exception $e) {
                            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->actions([
                // BOTÓN IA INDIVIDUAL (El de la varita mágica)
                Tables\Actions\Action::make('write_article')
                    ->label('IA')
                    ->icon('heroicon-o-sparkles')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Redactar con IA')
                    ->modalDescription('La IA escribirá este artículo en segundo plano.')
                    ->action(function (Article $record) {
                        try {
                            // LLAMADA LIMPIA (Sin ->onConnection, ya lo definimos en .env)
                            WriteArticleJob::dispatch($record)->onConnection('database');

                            Notification::make()
                                ->title('Redacción Iniciada ✍️')
                                ->body('Ejecuta el worker en la consola.')
                                ->success()
                                ->send();

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