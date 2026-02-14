<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectResource\Pages;
use App\Filament\Resources\ProjectResource\RelationManagers;
use App\Models\Project;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use App\Jobs\CrawlSiteJob;

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
                // SECCIÓN 1: DETALLES GENERALES
                Forms\Components\Section::make('Detalles del Proyecto')
                    ->description('Configuración principal.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->label('Nombre del Proyecto'),

                        Forms\Components\TextInput::make('uuid')
                            ->label('API Key (UUID)')
                            ->helperText('Copia este código para conectar tu sitio web o widget.')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn ($record) => $record !== null)
                            ->columnSpanFull(),
                        
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

                // SECCIÓN 2: CONFIGURACIÓN DE CONTENIDO
                Forms\Components\Section::make('Configuración de Contenido')
                    ->schema([
                        // 👇👇👇 AQUÍ ESTÁ EL CAMPO NUEVO 👇👇👇
                        Forms\Components\TextInput::make('niche')
                            ->label('Nicho / Rubro del Negocio')
                            ->placeholder('Ej: Dentista, Bienes Raíces, Restaurante Italiano')
                            ->helperText('CLAVE: Escribe aquí el tipo de negocio para que la IA busque fotos exactas en Pexels.')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        // 👆👆👆 FIN DEL CAMPO NUEVO 👆👆👆

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
                            ->placeholder('Ej: Profesional, amigable, experto...')
                            ->columnSpanFull(),
                    ])->columns(2),

                // SECCIÓN 3: DATOS DE CONTACTO
                Forms\Components\Section::make('Datos de Contacto (Para la IA)')
                    ->description('Estos datos aparecerán automáticamente al final de los artículos.')
                    ->schema([
                        Forms\Components\TextInput::make('phone')
                            ->label('Teléfono / WhatsApp')
                            ->tel()
                            ->placeholder('+52 314 ...'),

                        Forms\Components\TextInput::make('email')
                            ->label('Correo de Contacto')
                            ->email()
                            ->placeholder('contacto@negocio.com'),

                        Forms\Components\Textarea::make('address')
                            ->label('Dirección Física')
                            ->rows(2)
                            ->columnSpanFull()
                            ->placeholder('Calle, Número, Colonia, Ciudad...'),
                    ])->columns(2),

                // SECCIÓN 4: INTEGRACIÓN FACEBOOK
                Forms\Components\Section::make('Integración con Facebook (Automático)')
                    ->description('Configura esto para que los artículos se publiquen solos en tu Fanpage.')
                    ->schema([
                        Forms\Components\TextInput::make('facebook_page_id')
                            ->label('ID de la Página (Page ID)')
                            ->placeholder('Ej: 100083...')
                            ->helperText('Ve a la info de tu Fanpage para obtener este número.'),

                        Forms\Components\TextInput::make('facebook_access_token')
                            ->label('Token de Acceso (Long Lived Token)')
                            ->password() // Oculto por seguridad
                            ->revealable() // Botón de ojo para ver
                            ->columnSpanFull()
                            ->helperText('La llave maestra que permite publicar. No la compartas con nadie.'),
                    ])
                    ->collapsible() // Se puede cerrar
                    ->collapsed(),  // Cerrado por defecto para no estorbar
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
                Tables\Actions\Action::make('audit_site')
                    ->label('Rastrear Sitio')
                    ->icon('heroicon-o-eye')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('¿Iniciar Auditoría Técnica?')
                    ->modalDescription('El sistema analizará el sitio en SEGUNDO PLANO. Puedes seguir trabajando mientras tanto.')
                    ->modalSubmitActionLabel('Sí, Iniciar Rastreo')
                    ->action(function (Project $record) {
                        try {
                            CrawlSiteJob::dispatch($record);
                            Notification::make()
                                ->title('Rastreo Iniciado')
                                ->body('El análisis se está ejecutando en segundo plano.')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error al iniciar')
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
            RelationManagers\CrawlResultsRelationManager::class,
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