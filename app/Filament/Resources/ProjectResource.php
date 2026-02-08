<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectResource\Pages;
use App\Filament\Resources\ProjectResource\RelationManagers; // <--- IMPORTANTE
use App\Models\Project;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

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
                        // ESTOS ERAN LOS CAMPOS QUE FALTABAN O ESTABAN OCULTOS:
                        Forms\Components\TextInput::make('target_language')
                            ->label('Idioma Objetivo')
                            ->default('es-MX') // Valor por defecto para que no falle
                            ->required()
                            ->maxLength(10),

                        Forms\Components\TextInput::make('target_city')
                            ->label('Ciudad Objetivo (Local SEO)')
                            ->placeholder('Ej: Manzanillo, Colima')
                            ->maxLength(255),

                        Forms\Components\Textarea::make('brand_voice')
                            ->label('Voz de Marca')
                            ->rows(3)
                            ->placeholder('Ej: Profesional, amigable, experto...')
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
            // Aquí registramos la pestaña de artículos
            RelationManagers\ArticlesRelationManager::class,
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