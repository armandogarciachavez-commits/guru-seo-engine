<?php

namespace App\Filament\Resources;

use App\Services\SeoCrawler;
use Spatie\Crawler\Crawler;
use Spatie\Crawler\CrawlProfiles\CrawlInternalUrls;
use App\Models\CrawlResult;
use App\Filament\Resources\ProjectResource\Pages;
use App\Filament\Resources\ProjectResource\RelationManagers;
use App\Models\Project;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static ?string $navigationIcon = 'heroicon-o-rocket-launch';
    protected static ?string $navigationLabel = 'Mis Proyectos';
    protected static ?string $modelLabel = 'Proyecto';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detalles del Proyecto')
                    ->description('Configuración principal.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->label('Nombre del Proyecto'),
                        
                        Forms\Components\TextInput::make('domain_url')
                            ->url()
                            ->required()
                            ->prefix('https://')
                            ->maxLength(255)
                            ->label('URL del Sitio'),

                        Forms\Components\Select::make('cms_type')
                            ->options([
                                'wordpress' => 'WordPress',
                                'static' => 'Sitio Estático / HTML',
                                'custom_html' => 'Custom HTML',
                            ])
                            ->required()
                            ->label('CMS'),
                            
                        Forms\Components\Select::make('posting_frequency')
                            ->options([
                                'daily' => 'Diario',
                                'weekly' => 'Semanal',
                                'monthly' => 'Mensual',
                            ])
                            ->default('weekly')
                            ->required()
                            ->label('Frecuencia'),
                    ])->columns(2),

                Forms\Components\Section::make('Configuración de Contenido')
                    ->schema([
                        Forms\Components\TextInput::make('target_language')
                            ->label('Idioma Objetivo')
                            ->default('es-MX')
                            ->required()
                            ->maxLength(10),

                        Forms\Components\TextInput::make('target_city')
                            ->label('Ciudad Objetivo (Local SEO)')
                            ->placeholder('Ej: Manzanillo, Colima')
                            ->maxLength(255),

                        Forms\Components\Textarea::make('brand_voice')
                            ->label('Voz de Marca')
                            ->rows(3)
                            ->placeholder('Ej: Profesional, amigable, experto...') // <--- CORREGIDO AQUÍ
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(
                fn (Project $record): string => Pages\EditProject::getUrl([$record->id]),
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->label('Proyecto'),

                Tables\Columns\TextColumn::make('domain_url')
                    ->url(fn ($record) => $record->domain_url, true) 
                    ->color('primary')
                    ->icon('heroicon-m-link')
                    ->label('Sitio Web'),

                Tables\Columns\TextColumn::make('cms_type')
                    ->badge()
                    ->color('info')
                    ->label('CMS'),
            ])
            ->actions([
                // Acción de Auditoría SEO (Crawler)
                Tables\Actions\Action::make('audit_site')
                    ->label('Rastrear Sitio')
                    ->icon('heroicon-o-eye')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('¿Iniciar Auditoría Técnica?')
                    ->modalDescription('El sistema analizará la estructura del sitio, H1, Metas y enlaces. Esto puede tardar varios minutos.')
                    ->modalSubmitActionLabel('Sí, Iniciar Rastreo')
                    ->action(function (Project $record) {
                        set_time_limit(600);
                        ini_set('max_execution_time', 600);

                        // Limpiar resultados anteriores
                        CrawlResult::where('project_id', $record->id)->delete();

                        $url = $record->domain_url;
                        if (!str_starts_with($url, 'http')) {
                            $url = 'https://' . $url;
                        }

                        try {
                            Crawler::create()
                                ->setCrawlObserver(new SeoCrawler($record))
                                ->setCrawlProfile(new CrawlInternalUrls($url))
                                ->setTotalCrawlLimit(50)
                                ->startCrawling($url);

                            Notification::make()
                                ->title('Rastreo Completado')
                                ->body('Se han analizado las páginas del sitio.')
                                ->success()
                                ->send();

                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error en Rastreo')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\EditAction::make()->button(),
                Tables\Actions\DeleteAction::make()->button(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ArticlesRelationManager::class,
            RelationManagers\CrawlResultsRelationManager::class, // <--- AGREGADA AQUÍ
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjects::route('/'),
            'create' => Pages\CreateProject::route('/create'),
            'edit' => Pages\EditProject::route('/{record}/edit'),
        ];
    }
}