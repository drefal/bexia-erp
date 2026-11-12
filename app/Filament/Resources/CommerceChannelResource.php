<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CommerceChannelResource\Pages;
use App\Models\CommerceChannel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CommerceChannelResource extends Resource
{
    protected static ?string $model = CommerceChannel::class;

    // Catalogo global: no pertenece al tenant actual.
    protected static bool $isScopedToTenant = false;

    protected static ?string $navigationIcon =
        'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup =
        'Configuración Bexia';

    protected static ?string $navigationLabel =
        'Marketplaces';

    protected static ?string $modelLabel =
        'Marketplace';

    protected static ?string $pluralModelLabel =
        'Marketplaces';

    protected static ?int $navigationSort = 90;

    protected static function isPlatformAdmin(): bool
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
        return static::isPlatformAdmin();
    }

    public static function canViewAny(): bool
    {
        return static::isPlatformAdmin();
    }

    public static function canView(Model $record): bool
    {
        return static::isPlatformAdmin();
    }

    public static function canCreate(): bool
    {
        return static::isPlatformAdmin();
    }

    public static function canEdit(Model $record): bool
    {
        return static::isPlatformAdmin();
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
                'Identificación del marketplace'
            )
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(120),

                    Forms\Components\TextInput::make('code')
                        ->label('Código interno')
                        ->required()
                        ->alphaDash()
                        ->maxLength(40)
                        ->unique(ignoreRecord: true)
                        ->disabledOn('edit')
                        ->helperText(
                            'Identificador estable del conector.'
                        ),

                    Forms\Components\Textarea::make('description')
                        ->label('Descripción')
                        ->rows(3)
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make(
                        'documentation_url'
                    )
                        ->label('Documentación oficial API / SDK')
                        ->url()
                        ->maxLength(500)
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Forms\Components\Section::make(
                'Estado y capacidades'
            )
                ->schema([
                    Forms\Components\Select::make(
                        'lifecycle_status'
                    )
                        ->label('Estado de desarrollo')
                        ->options([
                            'draft' => 'Borrador',
                            'testing' => 'En pruebas',
                            'ready' => 'Preparado',
                            'suspended' => 'Suspendido',
                            'archived' => 'Archivado',
                        ])
                        ->default('draft')
                        ->required(),

                    Forms\Components\Select::make(
                        'capabilities'
                    )
                        ->label('Capacidades previstas')
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
                        ->helperText(
                            'Capacidades declaradas, no activación de API.'
                        )
                        ->columnSpanFull(),

                    Forms\Components\Placeholder::make(
                        'activation_notice'
                    )
                        ->label('Activación del conector')
                        ->content(
                            'La activación operativa se habilitará ' .
                            'después de validar el adaptador, ' .
                            'sus credenciales y sus permisos.'
                        )
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Marketplace')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->searchable(),

                Tables\Columns\TextColumn::make(
                    'lifecycle_status'
                )
                    ->label('Estado')
                    ->badge(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Operativo')
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCommerceChannels::route('/'),
            'create' => Pages\CreateCommerceChannel::route(
                '/create'
            ),
            'edit' => Pages\EditCommerceChannel::route(
                '/{record}/edit'
            ),
        ];
    }
}
