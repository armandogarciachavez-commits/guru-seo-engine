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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Proyectos';
    protected static ?string $modelLabel = 'Proyecto';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // SECCIÓN 1: DATOS GENERALES
                Section::make('📝 Información General')
                    ->schema([
                        Forms\Components\Hidden::make('user_id')
                            ->default(auth()->id()),

                        Grid::make(2)->schema([
                            Forms\Components\TextInput::make('name')
                                ->label('Nombre Interno del Proyecto')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('Ej: Cliente Juan Pérez'),

                            Forms\Components\TextInput::make('business_name')
                                ->label('Nombre Comercial de la Marca')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('Ej: Restaurante El Velero'),
                        ]),

                        Forms\Components\TextInput::make('uuid')
                            ->label('Project UUID (Llave API)')
                            ->readOnly()
                            ->helperText('Comparte esta llave solo con desarrolladores.')
                            ->columnSpanFull(),
                    ]),

                // SECCIÓN 2: CONFIGURACIÓN TÉCNICA
                Section::make('⚙️ Configuración del Sitio')
                    ->schema([
                        Forms\Components\TextInput::make('domain_url')
                            ->label('URL del Sitio Web')
                            ->url()
                            ->required()
                            ->maxLength(255)
                            ->placeholder('https://tudominio.com')
                            // AQUÍ ESTÁ TU BOTÓN DE AUTO-ANÁLISIS
                            ->suffixAction(
                                Forms\Components\Actions\Action::make('analyze_website')
                                    ->icon('heroicon-o-arrow-path-rounded-square')
                                    ->tooltip('Analizar Web y Rellenar Datos')
                                    ->requiresConfirmation()
                                    ->modalHeading('¿Auto-Descubrir Datos con IA?')
                                    ->modalDescription('Esto analizará el sitio web y llenará automáticamente la Estrategia SEO.')
                                    ->action(function ($state, Forms\Set $set, $livewire) {
                                        if (!$state) {
                                            \Filament\Notifications\Notification::make()->title('Falta URL')->warning()->send();
                                            return;
                                        }
                                        \Filament\Notifications\Notification::make()->title('Analizando...')->body('La IA está leyendo el sitio web.')->info()->send();

                                        try {
                                            // Asegúrate de tener este servicio creado, si no, dará error al hacer clic.
                                            $service = app(\App\Services\WebsiteAnalysisService::class);
                                            $data = $service->analyze($state);

                                            $set('business_context', $data['business_context'] ?? '');
                                            $set('target_audience', $data['target_audience'] ?? '');
                                            $set('target_location', $data['target_location'] ?? '');
                                            $set('brand_voice', $data['brand_voice'] ?? '');
                                            $set('key_services', $data['key_services'] ?? '');
                                            $set('cta_instruction', $data['cta_instruction'] ?? '');

                                            \Filament\Notifications\Notification::make()->title('¡Datos Extraídos!')->success()->send();
                                        } catch (\Exception $e) {
                                            \Filament\Notifications\Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                                        }
                                    })
                            ),

                        Grid::make(3)->schema([
                            Forms\Components\Select::make('cms_type')
                                ->label('Plataforma / CMS')
                                ->options([
                                    'wordpress' => 'WordPress',
                                    'shopify' => 'Shopify',
                                    'laravel' => 'Laravel / Custom',
                                    'static' => 'HTML / Estático',
                                ])
                                ->required(),

                            Forms\Components\TextInput::make('target_language')
                                ->label('Idioma')
                                ->default('es-MX')
                                ->required(),
                            
                            Forms\Components\Select::make('posting_frequency')
                                ->label('Frecuencia de Publicación')
                                ->options([
                                    'daily' => 'Diario',
                                    'weekly' => 'Semanal',
                                    'monthly' => 'Mensual',
                                ])
                                ->required(),
                        ]),
                    ]),

                // SECCIÓN 3: ESTRATEGIA DE MARCA (NUEVOS CAMPOS)
                Section::make('🧠 Identidad de Marca & Estrategia SEO')
                    ->description('Configura esto para que la IA escriba como un experto en tu negocio.')
                    ->schema([
                        Forms\Components\Textarea::make('business_context')
                            ->label('Contexto del Negocio / ¿Quiénes Somos?')
                            ->placeholder('Ej: Somos una agencia líder en Manzanillo. Hablamos con autoridad y cercanía.')
                            ->rows(3)
                            ->columnSpanFull(),
                        
                        Forms\Components\Textarea::make('brand_voice')
                            ->label('Voz y Tono de la Marca')
                            ->placeholder('Ej: Profesional, Divertido, Corporativo, Amigable...')
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('niche')
                            ->label('Nicho / Giro Principal')
                            ->rows(2)
                            ->columnSpanFull(),

                        Grid::make(2)->schema([
                            Forms\Components\TextInput::make('target_location')
                                ->label('Ubicación Objetivo (Local SEO)')
                                ->placeholder('Ej: Manzanillo, Colima'),

                            Forms\Components\TextInput::make('target_audience')
                                ->label('Público Objetivo')
                                ->placeholder('Ej: Restauranteros, Turistas, PyMES'),
                        ]),

                        Forms\Components\TextInput::make('key_services')
                            ->label('Servicios Clave a Vender')
                            ->placeholder('Ej: Diseño Web, Menús Digitales, Publicidad')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('cta_instruction')
                            ->label('Llamada a la Acción (CTA)')
                            ->placeholder('Ej: "Agenda tu cita por WhatsApp al 314..."')
                            ->columnSpanFull(),
                    ])
                    ->collapsible(), // Se puede colapsar para ahorrar espacio

                // SECCIÓN 4: INTEGRACIÓN WORDPRESS (Opcional)
                Section::make('🔌 Integración WordPress')
                    ->description('Solo llena esto si vas a publicar automático en un blog WordPress.')
                    ->schema([
                        Forms\Components\Select::make('integration_type')
                            ->label('Tipo de Integración')
                            ->options([
                                'wordpress' => 'WordPress API (Automático)',
                                'widget' => 'Solo Widget (Manual/API)',
                            ])
                            ->required()
                            ->reactive(),

                        Forms\Components\TextInput::make('wp_api_url')
                            ->label('URL del API WordPress')
                            ->placeholder('https://tuweb.com/wp-json')
                            ->url()
                            ->hidden(fn(Forms\Get $get) => $get('integration_type') !== 'wordpress')
                            ->required(fn(Forms\Get $get) => $get('integration_type') === 'wordpress'),

                        Grid::make(2)->schema([
                            Forms\Components\TextInput::make('wp_username')
                                ->label('Usuario WP')
                                ->hidden(fn(Forms\Get $get) => $get('integration_type') !== 'wordpress'),
                            
                            Forms\Components\TextInput::make('wp_application_password')
                                ->label('Contraseña de Aplicación')
                                ->password()
                                ->hidden(fn(Forms\Get $get) => $get('integration_type') !== 'wordpress'),
                        ]),
                    ])
                    ->collapsed(), // Viene cerrado por defecto para no estorbar

                Forms\Components\DateTimePicker::make('next_run_at')
                    ->label('Próxima Ejecución Programada'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('business_name')
                    ->label('Cliente / Marca')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                
                Tables\Columns\TextColumn::make('domain_url')
                    ->label('Sitio Web')
                    ->icon('heroicon-m-globe-alt')
                    ->color('primary')
                    ->url(fn($record) => $record->domain_url, true), // Abre en nueva pestaña

                Tables\Columns\TextColumn::make('posting_frequency')
                    ->label('Frecuencia')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'daily' => 'success',
                        'weekly' => 'warning',
                        'monthly' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('next_run_at')
                    ->label('Siguiente Post')
                    ->dateTime('d M Y, h:i A')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                // Aquí agregamos el botón de ver API que hicimos antes
                Tables\Actions\Action::make('api_docs')
                    ->label('API Docs')
                    ->icon('heroicon-o-code-bracket')
                    ->modalHeading('Integración API')
                    ->modalContent(fn($record) => view('filament.pages.api-docs-modal', ['record' => $record])) // Opcional si tienes la vista
                    ->action(function(){}), // Solo abre modal
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
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjects::route('/'),
            'create' => Pages\CreateProject::route('/create'),
            'view' => Pages\ViewProject::route('/{record}'),
            'edit' => Pages\EditProject::route('/{record}/edit'),
        ];
    }
}
