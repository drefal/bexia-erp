<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockAdjustmentAuditResource\Pages;
use App\Models\StockAdjustmentAudit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

// BEXIA_V57210L_INVENTORY_MENU_PERMISSION
class StockAdjustmentAuditResource extends Resource
{
    protected static ?string $model = StockAdjustmentAudit::class;

    // BEXIA_V5729Y_TENANT_COMPANY_RELATIONSHIP
    protected static ?string $tenantOwnershipRelationshipName = 'company';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Inventario';

    protected static ?string $navigationLabel = 'Auditoría de ajustes';

    protected static ?string $modelLabel = 'auditoría de ajuste';

    protected static ?string $pluralModelLabel = 'auditoría de ajustes';

    protected static ?int $navigationSort = 74;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $companyId = static::currentCompanyId();

        if ($companyId && Schema::hasColumn('stock_adjustment_audits', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Detalle')
                ->schema([
                    Forms\Components\TextInput::make('event')
                        ->label('Evento')
                        ->formatStateUsing(fn ($state): string => static::auditEventLabel($state))
                        ->disabled(),

                    Forms\Components\Textarea::make('description')
                        ->label('Descripción')
                        ->formatStateUsing(fn ($state): string => static::auditDescriptionLabel($state))
                        ->disabled()
                        ->columnSpanFull(),

                    Forms\Components\KeyValue::make('before_data')
                        ->label('Antes')
                        ->keyLabel('Campo')
                        ->valueLabel('Valor')
                        ->formatStateUsing(fn ($state): array => static::auditDataLabels($state))
                        ->disabled()
                        ->columnSpanFull(),

                    Forms\Components\KeyValue::make('after_data')
                        ->label('Después')
                        ->keyLabel('Campo')
                        ->valueLabel('Valor')
                        ->formatStateUsing(fn ($state): array => static::auditDataLabels($state))
                        ->disabled()
                        ->columnSpanFull(),

                    Forms\Components\KeyValue::make('metadata')
                        ->label('Metadatos')
                        ->keyLabel('Campo')
                        ->valueLabel('Valor')
                        ->formatStateUsing(fn ($state): array => static::auditDataLabels($state))
                        ->disabled()
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('event')
                    ->label('Evento')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => static::auditEventLabel($state))
                    ->color(fn ($state): string => static::auditEventColor($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Descripción')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('stock_adjustment_id')
                    ->label('Ajuste')
                    ->formatStateUsing(fn ($state): string => static::auditAdjustmentLabel($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('stock_adjustment_line_id')
                    ->label('Línea')
                    ->formatStateUsing(fn ($state): string => static::auditLineLabel($state))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('user_id')
                    ->label('Usuario')
                    ->formatStateUsing(fn ($state): string => static::auditUserLabel($state))
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event')
                    ->label('Evento')
                    ->options([
                        'created' => 'Creado',
                        'updated' => 'Actualizado',
                        'confirmed' => 'Confirmado',
                        'cancelled' => 'Cancelado',
                        'line_created' => 'Línea creada',
                        'line_updated' => 'Línea actualizada',
                        'line_deleted' => 'Línea eliminada',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Ver'),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockAdjustmentAudits::route('/'),
            'view' => Pages\ViewStockAdjustmentAudit::route('/{record}'),
        ];
    }

    protected static function currentCompanyId(): ?int
    {
        foreach (['company_id', 'current_company_id', 'active_company_id', 'tenant_company_id'] as $key) {
            if (session($key)) {
                return (int) session($key);
            }
        }

        return null;
    }

    protected static function eventLabel(?string $event): string
    {
        return match ($event) {
            'created' => 'Creado',
            'updated' => 'Actualizado',
            'confirmed' => 'Confirmado',
            'cancelled' => 'Cancelado',
            'line_created' => 'Línea creada',
            'line_updated' => 'Línea actualizada',
            'line_deleted' => 'Línea eliminada',
            default => $event ?: 'Evento',
        };
    }

    // BEXIA_V57210A_AUDITORIA_AJUSTES_TEXTOS_HUMANOS
    protected static function auditEventLabel(mixed $event): string
    {
        return match ((string) ($event ?? '')) {
            'created' => 'Creado',
            'updated' => 'Actualizado',
            'line_created' => 'Línea creada',
            'line_updated' => 'Línea actualizada',
            'line_deleted' => 'Línea eliminada',
            'confirmed' => 'Confirmado',
            'cancelled' => 'Cancelado',
            'deleted' => 'Eliminado',
            default => trim((string) ($event ?? '')) !== ''
                ? str((string) $event)->replace('_', ' ')->title()->toString()
                : 'Sin evento',
        };
    }

    protected static function auditEventColor(mixed $event): string
    {
        return match ((string) ($event ?? '')) {
            'confirmed' => 'success',
            'cancelled', 'deleted', 'line_deleted' => 'danger',
            'created', 'line_created' => 'info',
            'updated', 'line_updated' => 'warning',
            default => 'gray',
        };
    }

    protected static function auditDescriptionLabel(mixed $description): string
    {
        $description = trim((string) ($description ?? ''));

        return match ($description) {
            'Stock adjustment created.' => 'Ajuste de inventario creado.',
            'Stock adjustment updated.' => 'Ajuste de inventario actualizado.',
            'Stock adjustment confirmed.' => 'Ajuste de inventario confirmado.',
            'Stock adjustment cancelled.' => 'Ajuste de inventario cancelado.',
            'Stock adjustment line created.' => 'Línea de ajuste creada.',
            'Stock adjustment line updated.' => 'Línea de ajuste actualizada.',
            'Stock adjustment line deleted.' => 'Línea de ajuste eliminada.',
            default => $description !== '' ? $description : 'Sin descripción',
        };
    }

    protected static function auditAdjustmentLabel(mixed $id): string
    {
        $id = (int) ($id ?? 0);

        if ($id <= 0) {
            return 'Sin ajuste';
        }

        try {
            $row = \Illuminate\Support\Facades\DB::table('stock_adjustments')
                ->where('id', $id)
                ->select('id', 'reference', 'reason', 'status')
                ->first();

            if ($row) {
                $reference = trim((string) ($row->reference ?? ''));

                if ($reference !== '') {
                    return $reference . ' (ID ' . $id . ')';
                }
            }
        } catch (\Throwable) {
            //
        }

        return 'Ajuste ID ' . $id;
    }

    protected static function auditLineLabel(mixed $id): string
    {
        $id = (int) ($id ?? 0);

        if ($id <= 0) {
            return 'Sin línea';
        }

        try {
            $line = \Illuminate\Support\Facades\DB::table('stock_adjustment_lines')
                ->where('id', $id)
                ->first();

            if (! $line) {
                return 'Línea ID ' . $id;
            }

            $productId = null;

            if (property_exists($line, 'product_variant_id') && (int) ($line->product_variant_id ?? 0) > 0) {
                $productId = (int) $line->product_variant_id;
            } elseif (property_exists($line, 'product_id') && (int) ($line->product_id ?? 0) > 0) {
                $productId = (int) $line->product_id;
            }

            $productLabel = '';

            if ($productId) {
                $product = \Illuminate\Support\Facades\DB::table('products')
                    ->where('id', $productId)
                    ->select('id', 'name', 'internal_reference', 'sku')
                    ->first();

                if ($product) {
                    $parts = array_filter([
                        trim((string) ($product->internal_reference ?? '')),
                        trim((string) ($product->name ?? '')),
                    ]);

                    $productLabel = implode(' - ', $parts);
                }
            }

            $quantity = property_exists($line, 'quantity')
                ? rtrim(rtrim(number_format((float) $line->quantity, 4, '.', ''), '0'), '.')
                : '';

            $pieces = [];

            if ($productLabel !== '') {
                $pieces[] = $productLabel;
            }

            if ($quantity !== '') {
                $pieces[] = 'Cant. ' . $quantity;
            }

            $pieces[] = 'Línea ID ' . $id;

            return implode(' / ', $pieces);
        } catch (\Throwable) {
            return 'Línea ID ' . $id;
        }
    }

    protected static function auditUserLabel(mixed $id): string
    {
        $id = (int) ($id ?? 0);

        if ($id <= 0) {
            return 'Sistema';
        }

        try {
            $user = \Illuminate\Support\Facades\DB::table('users')
                ->where('id', $id)
                ->select('id', 'name', 'email')
                ->first();

            if ($user) {
                $name = trim((string) ($user->name ?? ''));
                $email = trim((string) ($user->email ?? ''));

                if ($name !== '' && $email !== '') {
                    return $name . ' <' . $email . '>';
                }

                if ($name !== '') {
                    return $name;
                }

                if ($email !== '') {
                    return $email;
                }
            }
        } catch (\Throwable) {
            //
        }

        return 'Usuario ID ' . $id;
    }

    protected static function auditDataLabels(mixed $state): array
    {
        if (is_string($state)) {
            $decoded = json_decode($state, true);
            $state = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }

        if (! is_array($state)) {
            return [];
        }

        $result = [];

        foreach ($state as $key => $value) {
            $label = static::auditKeyLabel((string) $key);
            $result[$label] = static::auditValueLabel((string) $key, $value);
        }

        return $result;
    }

    protected static function auditKeyLabel(string $key): string
    {
        return match ($key) {
            'id' => 'ID',
            'reference' => 'Referencia',
            'status' => 'Estado',
            'reason' => 'Motivo',
            'notes' => 'Notas',
            'created_at' => 'Fecha creación',
            'updated_at' => 'Fecha actualización',
            'confirmed_at' => 'Fecha confirmación',
            'confirmed_by' => 'Confirmado por',
            'cancelled_at' => 'Fecha cancelación',
            'cancelled_by' => 'Cancelado por',
            'stock_adjustment_id' => 'Ajuste',
            'stock_adjustment_line_id' => 'Línea',
            'user_id' => 'Usuario',
            'product_id' => 'Producto',
            'product_variant_id' => 'Variante',
            'quantity' => 'Cantidad',
            'before_quantity' => 'Cantidad anterior',
            'after_quantity' => 'Cantidad posterior',
            'difference_quantity' => 'Diferencia',
            'warehouse_id' => 'Almacén',
            'stock_location_id' => 'Ubicación',
            default => str($key)->replace('_', ' ')->title()->toString(),
        };
    }

    protected static function auditValueLabel(string $key, mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'Sin valor';
        }

        if (in_array($key, ['confirmed_by', 'cancelled_by', 'user_id'], true)) {
            return static::auditUserLabel($value);
        }

        if ($key === 'stock_adjustment_id') {
            return static::auditAdjustmentLabel($value);
        }

        if ($key === 'stock_adjustment_line_id') {
            return static::auditLineLabel($value);
        }

        if (in_array($key, ['created_at', 'updated_at', 'confirmed_at', 'cancelled_at'], true)) {
            return static::auditDateLabel($value);
        }

        if ($key === 'status') {
            return static::auditStatusLabel($value);
        }

        if ($key === 'product_id' || $key === 'product_variant_id') {
            return static::auditProductLabel($value);
        }

        if (is_bool($value)) {
            return $value ? 'Sí' : 'No';
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return (string) $value;
    }

    protected static function auditStatusLabel(mixed $status): string
    {
        return match ((string) ($status ?? '')) {
            'draft' => 'Borrador',
            'done' => 'Hecho',
            'confirmed' => 'Confirmado',
            'cancelled' => 'Cancelado',
            'pending' => 'Pendiente',
            default => trim((string) ($status ?? '')) !== ''
                ? str((string) $status)->replace('_', ' ')->title()->toString()
                : 'Sin estado',
        };
    }

    protected static function auditDateLabel(mixed $value): string
    {
        try {
            return \Carbon\Carbon::parse((string) $value)
                ->timezone(config('app.timezone', 'America/Mexico_City'))
                ->format('d/m/Y H:i');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    protected static function auditProductLabel(mixed $id): string
    {
        $id = (int) ($id ?? 0);

        if ($id <= 0) {
            return 'Sin producto';
        }

        try {
            $product = \Illuminate\Support\Facades\DB::table('products')
                ->where('id', $id)
                ->select('id', 'name', 'internal_reference', 'sku')
                ->first();

            if ($product) {
                $parts = array_filter([
                    trim((string) ($product->internal_reference ?? '')),
                    trim((string) ($product->name ?? '')),
                ]);

                if (! empty($parts)) {
                    return implode(' - ', $parts) . ' (ID ' . $id . ')';
                }
            }
        } catch (\Throwable) {
            //
        }

        return 'Producto ID ' . $id;
    }

public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\Security\BexiaTenantPermission::can('inventory.menu.view');
    }

public static function canAccess(): bool
    {
        return \App\Support\Security\BexiaTenantPermission::can('inventory.menu.view');
    }

public static function canViewAny(): bool
    {
        return \App\Support\Security\BexiaTenantPermission::can('inventory.menu.view');
    }

}
