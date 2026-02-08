<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectResource\Pages;
use App\Models\Project;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    // Icono de Cohete para confirmar que es la versión nueva 🚀
    protected static ?string $navigationIcon = 'heroicon-o-rocket-launch';

    // Etiquetas para asegurar cambio visual en el menú
    protected static ?string $navigationLabel = 'Mis Proyectos';
    protected static ?string $modelLabel = 'Proyecto';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detalles del Proyecto')
                    ->description('Información principal para la generación de contenido.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre del Proyecto')
                            ->required()
                            ->maxLength(255),
                        
                        Forms\Components\TextInput::make('domain_url')
                            ->label('URL del Sitio Web')
                            ->url()
                            ->required()
                            ->prefix('https://')
                            ->maxLength(255),

                        Forms\Components\Select::make('cms_type')
                            ->label('Plataforma (CMS)')
                            ->options([
                                'wordpress' => 'WordPress',
                                'static' => 'Sitio Estático / HTML',
                                'custom_html' => 'Custom HTML',
                            ])
                            ->required(),
                            
                        Forms\Components\Select::make('posting_frequency')
                            ->label('Frecuencia de Publicación')
                            ->options([
                                'daily' => 'Diario',
                                'weekly' => 'Semanal',
                                'monthly' => 'Mensual',
                            ])
                            ->default('weekly')
                            ->required(),
                    ])->columns(2),

                Forms\Components\Section::make('Identidad y Configuración')
                    ->schema([
                        Forms\Components\TextInput::make('target_language')
                            ->label('Idioma Objetivo')
                            ->default('es-MX')
                            ->required(),
                            
                        Forms\Components\TextInput::make('target_city')
                            ->label('Ciudad Objetivo (Local SEO)')
                            ->placeholder('Ej: Manzanillo'),

                        Forms\Components\Textarea::make('brand_voice')
                            ->label('Voz de Marca')
                            ->rows(4)
                            ->columnSpanFull()
                            ->helperText('Describe cómo debe hablar la IA.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // IMPORTANTE: Esto habilita el clic en toda la fila para editar
            ->recordUrl(
                fn (Project $record): string => Pages\EditProject::getUrl([$record->id]),
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Proyecto')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('domain_url')
                    ->label('Sitio Web')
                    ->icon('heroicon-m-link')
                    ->url(fn ($record) => $record->domain_url, true) 
                    ->color('primary')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('cms_type')
                    ->label('CMS')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'wordpress' => 'success',
                        'static' => 'warning',
                        'custom_html' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('posting_frequency')
                    ->label('Frecuencia')
                    ->icon('heroicon-m-clock')
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                // Botones explícitos de Editar y Borrar
                Tables\Actions\EditAction::make()
                    ->label('Editar')
                    ->button()
                    ->color('primary'),

                Tables\Actions\DeleteAction::make()
                    ->label('Borrar')
                    ->button()
                    ->color('danger'),
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
            // Aqu� registramos el manager que acabamos de crear
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