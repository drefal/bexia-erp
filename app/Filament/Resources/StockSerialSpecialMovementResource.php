<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockSerialSpecialMovementResource\Pages;
use App\Models\StockSerialSpecialMovement;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class StockSerialSpecialMovementResource extends Resource
{
    protected static ?string $model = StockSerialSpecialMovement::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Inventario';

    protected static ?string $navigationLabel = 'Auditoría números de serie';

    protected static ?string $modelLabel = 'movimiento de auditoría de serie';

    protected static ?string $pluralModelLabel = 'auditoría números de serie';

    protected static ?int $navigationSort = 71;

    protected static bool $isScopedToTenant = false;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && static::canManage('inventory.menu.view');
    }

    public static function canViewAny(): bool
    {
        return auth()->check() && static::canManage('inventory.menu.view');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = StockSerialSpecialMovement::query()
            ->with([
                'serialNumber',
                'product',
                'variant',
                'lot',
                'sourceWarehouse',
                'sourceLocation',
                'destinationWarehouse',
                'destinationLocation',
                'createdByUser',
                'confirmedByUser',
            ]);

        $companyId = static::currentCompanyId();

        if ($companyId && Schema::hasColumn('stock_serial_special_movements', 'company_id')) {
            $query->where('company_id', $companyId);
        } elseif (Schema::hasColumn('stock_serial_special_movements', 'company_id')) {
            $query->whereNull('company_id');
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Auditoría')
                    ->schema([
                        Forms\Components\Select::make('movement_type')
                            ->label('Tipo')
                            ->options(StockSerialSpecialMovement::typeLabels())
                            ->native(false)
                            ->disabled(),

                        Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->options([
                                'draft' => 'Borrador',
                                'confirmed' => 'Confirmado',
                                'cancelled' => 'Cancelado',
                            ])
                            ->native(false)
                            ->disabled(),

                        Forms\Components\TextInput::make('serial_number_before')
                            ->label('Serie original / actual')
                            ->disabled(),

                        Forms\Components\TextInput::make('serial_number_after')
                            ->label(fn (?StockSerialSpecialMovement $record): string => match ($record?->movement_type) {
                                StockSerialSpecialMovement::TYPE_SERIAL_CORRECTION => 'Serie nueva',
                                StockSerialSpecialMovement::TYPE_DUPLICATE_CONFLICT => 'Serie relacionada / conflictiva',
                                StockSerialSpecialMovement::TYPE_EXTERNAL_RELOCATION_IN => 'Serie recibida',
                                StockSerialSpecialMovement::TYPE_EXTERNAL_RELOCATION_OUT => 'Serie enviada / relacionada',
                                default => 'Serie relacionada / nueva',
                            })
                            ->disabled(),

                        Forms\Components\Textarea::make('reason')
                            ->label('Motivo')
                            ->disabled()
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notas')
                            ->disabled()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('movement_type')
                    ->label('Tipo')
                    ->formatStateUsing(fn (?string $state): string => static::movementTypeLabel($state))
                    ->badge()
                    ->color(fn (?string $state): string => static::movementTypeColor($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('serial_number_before')
                    ->label('Serie original')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('serial_number_after')
                    ->label('Serie relacionada / nueva')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('product_label')
                    ->label('Producto')
                    ->state(fn (StockSerialSpecialMovement $record): string => static::productLabel($record))
                    ->wrap()
                    ->searchable(false)
                    ->sortable(false),

                Tables\Columns\TextColumn::make('source_label')
                    ->label('Origen')
                    ->state(fn (StockSerialSpecialMovement $record): string => static::locationLabel($record->sourceWarehouse?->name, $record->sourceLocation?->name))
                    ->wrap()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('destination_label')
                    ->label('Destino')
                    ->state(fn (StockSerialSpecialMovement $record): string => static::locationLabel($record->destinationWarehouse?->name, $record->destinationLocation?->name))
                    ->wrap()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('reason')
                    ->label('Motivo')
                    ->limit(60)
                    ->wrap()
                    ->searchable(),

                Tables\Columns\TextColumn::make('reference')
                    ->label('Referencia')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('createdByUser.name')
                    ->label('Usuario')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => static::statusLabel($state))
                    ->color(fn (?string $state): string => match ($state) {
                        'confirmed' => 'success',
                        'draft' => 'gray',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('movement_type')
                    ->label('Tipo')
                    ->options(StockSerialSpecialMovement::typeLabels()),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'draft' => 'Borrador',
                        'confirmed' => 'Confirmado',
                        'cancelled' => 'Cancelado',
                    ]),

                Tables\Filters\Filter::make('created_at')
                    ->label('Fecha')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('Desde'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Ver detalle'),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockSerialSpecialMovements::route('/'),
            'view' => Pages\ViewStockSerialSpecialMovement::route('/{record}'),
        ];
    }

    protected static function movementTypeLabel(?string $state): string
    {
        return StockSerialSpecialMovement::typeLabels()[$state ?: ''] ?? ($state ?: 'Sin tipo');
    }

    protected static function movementTypeColor(?string $state): string
    {
        return match ($state) {
            StockSerialSpecialMovement::TYPE_SERIAL_CORRECTION => 'warning',
            StockSerialSpecialMovement::TYPE_SCRAP_LOSS => 'danger',
            StockSerialSpecialMovement::TYPE_INTERNAL_RELOCATION => 'info',
            StockSerialSpecialMovement::TYPE_EXTERNAL_RELOCATION_OUT,
            StockSerialSpecialMovement::TYPE_EXTERNAL_RELOCATION_IN => 'gray',
            StockSerialSpecialMovement::TYPE_DUPLICATE_CONFLICT => 'danger',
            default => 'gray',
        };
    }

    protected static function statusLabel(?string $state): string
    {
        return match ($state) {
            'draft' => 'Borrador',
            'confirmed' => 'Confirmado',
            'cancelled' => 'Cancelado',
            default => $state ?: 'Sin estado',
        };
    }

    protected static function productLabel(StockSerialSpecialMovement $record): string
    {
        $product = $record->product;
        $variant = $record->variant;

        $productLabel = $product
            ? trim(((string) ($product->internal_reference ?: $product->sku ?: $product->id)) . ' - ' . ((string) $product->name))
            : '—';

        if ($variant) {
            $variantLabel = trim(((string) ($variant->internal_reference ?: $variant->sku ?: $variant->id)) . ' - ' . ((string) $variant->name));

            return $productLabel . ' / ' . $variantLabel;
        }

        return $productLabel;
    }

    protected static function locationLabel(?string $warehouse, ?string $location): string
    {
        $label = trim(implode(' / ', array_filter([
            $warehouse,
            $location,
        ])));

        return $label !== '' ? $label : '—';
    }

    protected static function currentCompanyId(): ?int
    {
        $tenant = Filament::getTenant();

        if (is_object($tenant) && method_exists($tenant, 'getKey')) {
            return (int) $tenant->getKey();
        }

        if (is_numeric($tenant)) {
            return (int) $tenant;
        }

        return null;
    }

    protected static function canManage(string $permission): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (property_exists($user, 'is_system_admin') && (bool) $user->is_system_admin) {
            return true;
        }

        if (method_exists($user, 'can')) {
            return $user->can($permission);
        }

        return true;
    }
}
