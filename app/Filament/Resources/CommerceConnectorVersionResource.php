<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CommerceConnectorVersionResource\Pages;
use App\Models\CommerceConnectorVersion;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CommerceConnectorVersionResource extends Resource
{
    protected static ?string $model = CommerceConnectorVersion::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $navigationIcon =
        'heroicon-o-puzzle-piece';

    protected static ?string $navigationGroup =
        'Configuración Bexia';

    protected static ?string $navigationLabel =
        'Conectores Commerce';

    protected static ?string $modelLabel =
        'Versión de conector';

    protected static ?string $pluralModelLabel =
        'Conectores Commerce';

    protected static ?int $navigationSort = 91;

    protected static function platformAdmin(): bool
    {
        $user = auth()->user();

        return (bool) (
            $user
            && method_exists($user, 'isSystemAdmin')
            && $user->isSystemAdmin()
        );
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::platformAdmin();
    }

    public static function canViewAny(): bool
    {
        return static::platformAdmin();
    }

    public static function canView(Model $record): bool
    {
        return static::platformAdmin();
    }

    public static function canCreate(): bool
    {
        return static::platformAdmin();
    }

    public static function canEdit(Model $record): bool
    {
        return static::platformAdmin();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(
                'Identificación del conector'
            )
                ->schema([
                    Forms\Components\Select::make(
                        'commerce_channel_id'
                    )
                        ->label('Marketplace')
                        ->relationship('channel', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->disabledOn('edit'),

                    Forms\Components\TextInput::make('version')
                        ->label('Versión del conector Bexia')
                        ->required()
                        ->maxLength(60)
                        ->disabledOn('edit')
                        ->helperText(
                            'Ejemplo: 1.0.0. No reutilizar una versión publicada.'
                        ),

                    Forms\Components\TextInput::make(
                        'adapter_key'
                    )
                        ->label('Adaptador técnico')
                        ->required()
                        ->maxLength(120)
                        ->disabledOn('edit')
                        ->helperText(
                            'Identificador de un adaptador desarrollado por Bexia. ' .
                            'No ejecuta código por sí mismo.'
                        ),

                    Forms\Components\TextInput::make(
                        'api_version'
                    )
                        ->label('Versión API del proveedor')
                        ->maxLength(100),

                    Forms\Components\Select::make('auth_type')
                        ->label('Método de autorización')
                        ->options([
                            'oauth2' => 'OAuth 2.0',
                            'api_key' => 'API Key',
                            'access_token' => 'Token de acceso',
                            'custom' => 'Autorización específica',
                        ])
                        ->required(),

                    Forms\Components\Select::make('status')
                        ->label('Estado técnico')
                        ->options([
                            'draft' => 'Borrador',
                            'testing' => 'En pruebas',
                            'ready' => 'Preparado',
                            'suspended' => 'Suspendido',
                            'deprecated' => 'Obsoleto',
                        ])
                        ->default('draft')
                        ->required(),
                ])
                ->columns(2),

            Forms\Components\Section::make(
                'Capacidades e instrucciones'
            )
                ->schema([
                    Forms\Components\Select::make(
                        'capabilities'
                    )
                        ->label('Capacidades declaradas')
                        ->multiple()
                        ->options([
                            'products' => 'Productos',
                            'listings' => 'Publicaciones',
                            'inventory' => 'Inventario',
                            'prices' => 'Precios',
                            'orders' => 'Pedidos',
                            'shipping' => 'Envíos',
                            'returns' => 'Devoluciones',
                            'settlements' => 'Liquidaciones',
                        ])
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make(
                        'connection_instructions'
                    )
                        ->label(
                            'Instrucciones para conectar una cuenta'
                        )
                        ->rows(6)
                        ->columnSpanFull()
                        ->helperText(
                            'Indicar los pasos que verá el cliente. ' .
                            'Nunca introducir claves ni secretos aquí.'
                        ),

                    Forms\Components\Placeholder::make(
                        'runtime_notice'
                    )
                        ->label('Estado operativo')
                        ->content(
                            'El registro de una versión no activa el conector. ' .
                            'Su adaptador, autorización y sincronización ' .
                            'deben verificarse por separado.'
                        )
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make(
                    'channel.name'
                )
                    ->label('Marketplace')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make(
                    'version'
                )
                    ->label('Versión')
                    ->sortable(),

                Tables\Columns\TextColumn::make(
                    'adapter_key'
                )
                    ->label('Adaptador'),

                Tables\Columns\TextColumn::make(
                    'auth_type'
                )
                    ->label('Autorización'),

                Tables\Columns\TextColumn::make(
                    'status'
                )
                    ->label('Estado')
                    ->badge(),

                Tables\Columns\IconColumn::make(
                    'is_enabled'
                )
                    ->label('Operativo')
                    ->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCommerceConnectorVersions::route('/'),
            'create' => Pages\CreateCommerceConnectorVersion::route(
                '/create'
            ),
            'edit' => Pages\EditCommerceConnectorVersion::route(
                '/{record}/edit'
            ),
        ];
    }
}
