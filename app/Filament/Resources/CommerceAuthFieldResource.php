<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CommerceAuthFieldResource\Pages;
use App\Models\CommerceAuthField;
use App\Models\CommerceConnectorVersion;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CommerceAuthFieldResource extends Resource
{
    protected static ?string $model = CommerceAuthField::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $navigationIcon =
        'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup =
        'Configuración Bexia';

    protected static ?string $navigationLabel =
        'Campos de conexión';

    protected static ?string $modelLabel =
        'Campo de conexión';

    protected static ?string $pluralModelLabel =
        'Campos de conexión';

    protected static ?int $navigationSort = 92;

    public static function platformAdmin(): bool
    {
        $user = auth()->user();

        return (bool) (
            $user
            && method_exists($user, 'isSystemAdmin')
            && $user->isSystemAdmin()
        );
    }

    public static function versionIsEditable(
        int $versionId
    ): bool {
        return CommerceConnectorVersion::query()
            ->whereKey($versionId)
            ->whereIn('status', ['draft', 'testing'])
            ->where('is_enabled', false)
            ->exists();
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
        return static::platformAdmin()
            && static::versionIsEditable(
                (int) $record->commerce_connector_version_id
            );
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
                'Identificación y propietario'
            )
                ->schema([
                    Forms\Components\Select::make(
                        'commerce_connector_version_id'
                    )
                        ->label('Versión del conector')
                        ->relationship(
                            name: 'connectorVersion',
                            titleAttribute: 'version',
                            modifyQueryUsing: fn (Builder $query): Builder =>
                                $query
                                    ->whereIn(
                                        'status',
                                        ['draft', 'testing']
                                    )
                                    ->where('is_enabled', false)
                        )
                        ->getOptionLabelFromRecordUsing(
                            fn (CommerceConnectorVersion $record): string =>
                                ($record->channel?->name ?? 'Canal')
                                . ' / ' . $record->version
                        )
                        ->searchable()
                        ->preload()
                        ->required()
                        ->disabledOn('edit'),

                    Forms\Components\TextInput::make('field_key')
                        ->label('Clave técnica')
                        ->required()
                        ->maxLength(100)
                        ->regex('/^[a-z][a-z0-9_]*$/')
                        ->disabledOn('edit')
                        ->helperText(
                            'Ejemplo: client_id, shop_domain.'
                        ),

                    Forms\Components\TextInput::make('label')
                        ->label('Etiqueta visible')
                        ->required()
                        ->maxLength(150),

                    Forms\Components\Select::make(
                        'credential_owner'
                    )
                        ->label('Quién proporciona el dato')
                        ->options([
                            'app' => 'Administrador Bexia',
                            'account' => 'Empresa cliente',
                        ])
                        ->required(),

                    Forms\Components\Select::make('field_type')
                        ->label('Tipo de campo')
                        ->options([
                            'text' => 'Texto',
                            'password' => 'Contraseña / secreto',
                            'url' => 'URL',
                            'number' => 'Número',
                            'select' => 'Selector',
                        ])
                        ->default('text')
                        ->required(),

                    Forms\Components\TextInput::make('sort_order')
                        ->label('Orden de presentación')
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->default(0)
                        ->required(),

                    Forms\Components\Toggle::make('is_required')
                        ->label('Obligatorio')
                        ->default(false),

                    Forms\Components\Toggle::make('is_secret')
                        ->label('Dato secreto')
                        ->default(false)
                        ->helperText(
                            'Los valores se guardarán en un '
                            . 'almacén cifrado separado.'
                        ),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Campo visible')
                        ->default(true),
                ])
                ->columns(2),

            Forms\Components\Section::make(
                'Instrucciones para la conexión'
            )
                ->schema([
                    Forms\Components\Textarea::make('help_text')
                        ->label('Ayuda para el usuario')
                        ->rows(4)
                        ->helperText(
                            'Explicar dónde obtener el dato. '
                            . 'No incluir credenciales reales.'
                        )
                        ->columnSpanFull(),

                    Forms\Components\Placeholder::make(
                        'definition_notice'
                    )
                        ->label('Seguridad')
                        ->content(
                            'Aquí sólo se definen campos. '
                            . 'No se capturan tokens, contraseñas '
                            . 'ni Client Secrets reales.'
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
                    'connectorVersion.channel.name'
                )
                    ->label('Marketplace')
                    ->searchable(),

                Tables\Columns\TextColumn::make(
                    'connectorVersion.version'
                )
                    ->label('Versión'),

                Tables\Columns\TextColumn::make('field_key')
                    ->label('Clave técnica')
                    ->searchable(),

                Tables\Columns\TextColumn::make('label')
                    ->label('Etiqueta')
                    ->searchable(),

                Tables\Columns\TextColumn::make(
                    'credential_owner'
                )
                    ->label('Propietario')
                    ->formatStateUsing(
                        fn (string $state): string =>
                            $state === 'app'
                                ? 'Bexia'
                                : 'Cliente'
                    ),

                Tables\Columns\TextColumn::make('field_type')
                    ->label('Tipo'),

                Tables\Columns\IconColumn::make('is_required')
                    ->label('Obligatorio')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_secret')
                    ->label('Secreto')
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
            'index' => Pages\ListCommerceAuthFields::route('/'),
            'create' => Pages\CreateCommerceAuthField::route('/create'),
            'edit' => Pages\EditCommerceAuthField::route(
                '/{record}/edit'
            ),
        ];
    }
}
