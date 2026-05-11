<?php

namespace App\Filament\Resources\StockAdjustmentResource\Pages;

use App\Filament\Resources\StockAdjustmentResource;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentLine;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;

class ListStockAdjustments extends ListRecords
{
    protected static string $resource = StockAdjustmentResource::class;



    public function getTabs(): array
    {
        return [
            'borradores' => Tab::make('Borradores')
                ->modifyQueryUsing(
                    fn (\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder => $query->where('status', 'draft')
                ),

            'hechos' => Tab::make('Hechos')
                ->modifyQueryUsing(
                    fn (\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder => $query->where('status', 'done')
                ),

            'cancelados' => Tab::make('Cancelados')
                ->modifyQueryUsing(
                    fn (\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder => $query->where('status', 'cancelled')
                ),

            'todos' => Tab::make('Todos')
                ->modifyQueryUsing(
                    fn (\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder => $query
                ),
        ];
    }


    public function getDefaultActiveTab(): string | int | null
    {
        return 'borradores';
    }


    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('downloadInventoryTemplate')
                ->label('Descargar plantilla CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn (): bool => static::userCanDownloadInventoryTemplate())
                ->modalHeading('Descargar plantilla CSV de inventario inicial')
                ->modalDescription('El archivo se generará al momento con los productos, variantes y existencias actuales de la ubicación seleccionada. Si hay muchos productos, puede tardar unos segundos o minutos. No cierres esta ventana ni recargues la página mientras inicia la descarga.')
                ->modalSubmitActionLabel('Generar y descargar CSV')
                ->form([
                    Forms\Components\Select::make('warehouse_id')
                        ->label('Almacén')
                        ->options(fn (): array => static::warehouseOptions())
                        ->searchable()
                        ->native(false)
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (Forms\Set $set): null => $set('location_id', null)),

                    Forms\Components\Select::make('location_id')
                        ->label('Ubicación')
                        ->options(fn (Forms\Get $get): array => static::locationOptions($get('warehouse_id')))
                        ->searchable()
                        ->native(false)
                        ->required()
                        ->helperText('Solo se muestran ubicaciones físicas internas del almacén seleccionado.'),
                ])
                ->action(function (array $data, $livewire): void {
                    Notification::make()
                        ->title('Generando plantilla CSV')
                        ->body('La descarga iniciará cuando el archivo esté listo. Si el catálogo es grande, espera unos momentos.')
                        ->info()
                        ->send();

                    $url = URL::temporarySignedRoute(
                        'inventory.initial-load-template.download',
                        now()->addMinutes(10),
                        [
                            'company_id' => static::currentCompanyId(),
                            'warehouse_id' => (int) $data['warehouse_id'],
                            'location_id' => (int) $data['location_id'],
                        ],
                    );

                    $livewire->redirect($url, navigate: false);
                }),

            Actions\Action::make('importInventoryTemplate')
                ->label('Importar CSV de conteo')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->visible(fn (): bool => static::userCanImportInventoryTemplate())
                ->modalHeading('Importar CSV de conteo físico')
                ->modalDescription('Sube el CSV generado desde la plantilla. Bexia validará el archivo y creará un ajuste en borrador. No se afectarán existencias hasta confirmar el ajuste.')
                ->modalSubmitActionLabel('Validar y crear ajuste')
                ->form([
                    Forms\Components\Select::make('warehouse_id')
                        ->label('Almacén')
                        ->options(fn (): array => static::warehouseOptions())
                        ->searchable()
                        ->native(false)
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (Forms\Set $set): null => $set('location_id', null)),

                    Forms\Components\Select::make('location_id')
                        ->label('Ubicación')
                        ->options(fn (Forms\Get $get): array => static::locationOptions($get('warehouse_id')))
                        ->searchable()
                        ->native(false)
                        ->required(),

                    Forms\Components\FileUpload::make('csv_file')
                        ->label('Archivo CSV')
                        ->disk('local')
                        ->directory('inventory-imports')
                        ->acceptedFileTypes([
                            'text/csv',
                            'text/plain',
                            'application/csv',
                            'application/vnd.ms-excel',
                        ])
                        ->maxSize(20480)
                        ->required()
                        ->helperText('Usa la plantilla CSV descargada desde Bexia. Solo se importan líneas con cantidad contada.'),
                ])
                ->action(function (array $data): void {
                    $result = \App\Support\InventoryInitialLoadImporter::import(
                        static::currentCompanyId(),
                        (int) $data['warehouse_id'],
                        (int) $data['location_id'],
                        $data['csv_file'] ?? null,
                    );

                    if (! ($result['ok'] ?? false)) {
                        $errors = array_slice($result['errors'] ?? ['No se pudo importar el CSV.'], 0, 12);

                        Notification::make()
                            ->title('No se pudo importar el CSV')
                            ->body(implode("\n", $errors))
                            ->danger()
                            ->persistent()
                            ->send();

                        throw new Halt();
                    }

                    $adjustment = $result['adjustment'];

                    Notification::make()
                        ->title('Ajuste creado en borrador')
                        ->body('Se importaron ' . ($result['lines'] ?? 0) . ' líneas. Revisa el ajuste antes de confirmarlo.')
                        ->success()
                        ->send();

                    $this->redirect(StockAdjustmentResource::getUrl('edit', [
                        'record' => $adjustment,
                    ]));
                }),

            Actions\Action::make('zeroLocation')
                ->label('Poner a cero sin seguimiento')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn (): bool => static::userCanZeroUntrackedStock())
                ->modalHeading('Poner ubicación a cero antes de conteo físico')
                ->modalDescription('Se creará un ajuste en borrador para dejar en cero solo las existencias de productos sin seguimiento. Los productos con lote, serie o seguimiento activo se omitirán y deberán revisarse en un proceso individual.')
                ->modalSubmitActionLabel('Crear ajuste a cero')
                ->form([
                    Forms\Components\Select::make('warehouse_id')
                        ->label('Almacén')
                        ->options(fn (): array => static::warehouseOptions())
                        ->searchable()
                        ->native(false)
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (Forms\Set $set): null => $set('location_id', null)),

                    Forms\Components\Select::make('location_id')
                        ->label('Ubicación')
                        ->options(fn (Forms\Get $get): array => static::locationOptions($get('warehouse_id')))
                        ->searchable()
                        ->native(false)
                        ->required()
                        ->helperText('Solo se afectarán productos sin seguimiento en esta ubicación.'),
                ])
                ->requiresConfirmation()
                ->modalIcon('heroicon-o-exclamation-triangle')
                ->action(function (array $data): void {
                    $warehouseId = (int) ($data['warehouse_id'] ?? 0);
                    $locationId = (int) ($data['location_id'] ?? 0);
                    $companyId = static::currentCompanyId();

                    if (! $warehouseId || ! $locationId) {
                        Notification::make()
                            ->title('Faltan datos')
                            ->body('Selecciona almacén y ubicación.')
                            ->danger()
                            ->send();

                        throw new Halt();
                    }

                    if (! \Illuminate\Support\Facades\Schema::hasTable('stock_quants')) {
                        Notification::make()
                            ->title('No existe la tabla de existencias')
                            ->body('Primero debe existir stock_quants.')
                            ->danger()
                            ->send();

                        throw new Halt();
                    }

                    $trackedCount = static::trackedQuantCount($companyId, $warehouseId, $locationId);

                    $quants = static::untrackedQuantQuery($companyId, $warehouseId, $locationId)
                        ->orderBy('stock_quants.product_id')
                        ->orderBy('stock_quants.product_variant_id')
                        ->get();

                    if ($quants->isEmpty()) {
                        $body = 'La ubicación seleccionada no tiene existencias sin seguimiento diferentes de cero.';

                        if ($trackedCount > 0) {
                            $body .= ' Se detectaron ' . $trackedCount . ' líneas con lote, serie o seguimiento activo; esas deben ajustarse individualmente.';
                        }

                        Notification::make()
                            ->title('No hay existencias sin seguimiento para ajustar')
                            ->body($body)
                            ->warning()
                            ->send();

                        throw new Halt();
                    }

                    $adjustment = null;

                    \Illuminate\Support\Facades\DB::transaction(function () use ($quants, $companyId, $warehouseId, $locationId, &$adjustment): void {
                        $adjustment = \App\Models\StockAdjustment::create([
                            'company_id' => $companyId,
                            'warehouse_id' => $warehouseId,
                            'location_id' => $locationId,
                            'adjustment_at' => now(),
                            'adjustment_date' => now()->toDateString(),
                            'status' => 'draft',
                            'reason' => 'Puesta a cero previa a conteo físico',
                            'notes' => 'Generado automáticamente. Solo afecta productos sin seguimiento. Productos con lote/serie/seguimiento deben ajustarse individualmente.',
                            'created_by' => auth()->id(),
                        ]);

                        foreach ($quants as $quant) {
                            $current = (float) $quant->quantity;

                            \App\Models\StockAdjustmentLine::create([
                                'stock_adjustment_id' => $adjustment->id,
                                'product_id' => $quant->product_id,
                                'product_variant_id' => $quant->product_variant_id,
                                'lot_id' => $quant->lot_id ?? null,
                                'current_quantity' => $current,
                                'counted_quantity' => 0,
                                'difference_quantity' => 0 - $current,
                                'unit_cost' => $quant->average_cost ?? null,
                                'notes' => 'Puesta a cero sin seguimiento generada automáticamente.',
                            ]);
                        }
                    });

                    $body = 'Se creó un ajuste en borrador con ' . $quants->count() . ' líneas sin seguimiento. Revísalo y confirma para afectar existencias.';

                    if ($trackedCount > 0) {
                        $body .= ' Se omitieron ' . $trackedCount . ' líneas con lote, serie o seguimiento activo.';
                    }

                    Notification::make()
                        ->title('Ajuste a cero creado')
                        ->body($body)
                        ->success()
                        ->send();

                    $this->redirect(StockAdjustmentResource::getUrl('edit', [
                        'record' => $adjustment,
                    ]));
                }),

            Actions\CreateAction::make()
                ->label('Nuevo ajuste'),
        ];
    }

    protected static function baseQuantQuery(?int $companyId, int $warehouseId, int $locationId): Builder
    {
        $query = DB::table('stock_quants')
            ->where('stock_quants.warehouse_id', $warehouseId)
            ->where('stock_quants.location_id', $locationId)
            ->where(function (Builder $query): void {
                $query
                    ->where('stock_quants.quantity', '<>', 0)
                    ->orWhere('stock_quants.reserved_quantity', '<>', 0);
            });

        if (Schema::hasColumn('stock_quants', 'company_id')) {
            $companyId
                ? $query->where('stock_quants.company_id', $companyId)
                : $query->whereNull('stock_quants.company_id');
        }

        return $query;
    }

    protected static function untrackedQuantQuery(?int $companyId, int $warehouseId, int $locationId): Builder
    {
        $query = static::baseQuantQuery($companyId, $warehouseId, $locationId)
            ->select('stock_quants.*');

        if (Schema::hasColumn('stock_quants', 'lot_id')) {
            $query->whereNull('stock_quants.lot_id');
        }

        if (Schema::hasTable('products')) {
            $query->leftJoin('products', 'products.id', '=', 'stock_quants.product_id');

            if (Schema::hasColumn('stock_quants', 'product_variant_id')) {
                $query->leftJoin('products as product_variants', 'product_variants.id', '=', 'stock_quants.product_variant_id');
            }

            static::applyNoTrackingFilter($query, 'products');
            static::applyNoTrackingFilter($query, 'product_variants');
        }

        return $query;
    }

    protected static function trackedQuantCount(?int $companyId, int $warehouseId, int $locationId): int
    {
        $total = static::baseQuantQuery($companyId, $warehouseId, $locationId)->count();

        $untracked = static::untrackedQuantQuery($companyId, $warehouseId, $locationId)->count();

        return max(0, (int) $total - (int) $untracked);
    }

    protected static function applyNoTrackingFilter(Builder $query, string $alias): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        $stringColumns = [
            'tracking',
            'tracking_type',
            'tracking_method',
            'inventory_tracking',
            'lot_serial_tracking',
            'tracking_mode',
        ];

        $untrackedValues = [
            '',
            'none',
            'no',
            'false',
            '0',
            'no_tracking',
            'untracked',
            'sin_seguimiento',
            'sin seguimiento',
            'ninguno',
        ];

        foreach ($stringColumns as $column) {
            if (! Schema::hasColumn('products', $column)) {
                continue;
            }

            $query->where(function (Builder $query) use ($alias, $column, $untrackedValues): void {
                $query
                    ->whereNull($alias . '.id')
                    ->orWhereNull($alias . '.' . $column)
                    ->orWhereRaw(
                        'LOWER(COALESCE(CAST(' . $alias . '.' . $column . ' AS TEXT), \'\')) IN (' . implode(',', array_fill(0, count($untrackedValues), '?')) . ')',
                        $untrackedValues
                    );
            });
        }

        $booleanColumns = [
            'has_tracking',
            'is_tracked',
            'track_inventory',
            'track_lots',
            'track_serials',
            'requires_lot',
            'requires_serial',
            'use_lots',
            'use_serials',
            'lot_tracking',
            'serial_tracking',
        ];

        foreach ($booleanColumns as $column) {
            if (! Schema::hasColumn('products', $column)) {
                continue;
            }

            $query->where(function (Builder $query) use ($alias, $column): void {
                $query
                    ->whereNull($alias . '.id')
                    ->orWhereNull($alias . '.' . $column)
                    ->orWhere($alias . '.' . $column, false);
            });
        }
    }

    protected static function currentCompanyId(): ?int
    {
        $tenant = Filament::getTenant();

        if ($tenant && method_exists($tenant, 'getKey')) {
            return (int) $tenant->getKey();
        }

        $user = auth()->user();

        if ($user && isset($user->company_id)) {
            return (int) $user->company_id;
        }

        return null;
    }


    protected static function userCanImportInventoryTemplate(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (
            method_exists($user, 'hasAnyRole')
            && $user->hasAnyRole([
                'super_admin',
                'Super Admin',
                'Super Administrador',
                'admin',
                'Administrador',
                'Admin Empresa',
                'Admin Grupo',
                'Inventarios',
            ])
        ) {
            return true;
        }

        return method_exists($user, 'can')
            ? $user->can('inventory.import_inventory_template')
            : false;
    }

    protected static function userCanDownloadInventoryTemplate(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (
            method_exists($user, 'hasAnyRole')
            && $user->hasAnyRole([
                'super_admin',
                'Super Admin',
                'Super Administrador',
                'admin',
                'Administrador',
                'Admin Empresa',
                'Admin Grupo',
                'Inventarios',
            ])
        ) {
            return true;
        }

        return method_exists($user, 'can')
            ? $user->can('inventory.download_inventory_template')
            : false;
    }


    protected static function userCanZeroUntrackedStock(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (
            method_exists($user, 'hasAnyRole')
            && $user->hasAnyRole([
                'super_admin',
                'Super Admin',
                'Super Administrador',
                'admin',
                'Administrador',
                'Admin Empresa',
                'Admin Grupo',
                'Inventarios',
            ])
        ) {
            return true;
        }

        return method_exists($user, 'can')
            ? $user->can('inventory.zero_untracked_stock')
            : false;
    }

    protected static function userCanAdjust(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (
            method_exists($user, 'hasAnyRole')
            && $user->hasAnyRole([
                'super_admin',
                'Super Admin',
                'Super Administrador',
                'admin',
                'Administrador',
                'Admin Empresa',
                'Admin Grupo',
                'Inventarios',
            ])
        ) {
            return true;
        }

        return method_exists($user, 'can')
            ? $user->can('inventory.adjust_stock')
            : false;
    }

    protected static function warehouseOptions(): array
    {
        if (! Schema::hasTable('warehouses')) {
            return [];
        }

        $query = DB::table('warehouses');

        if (Schema::hasColumn('warehouses', 'is_active')) {
            $query->where('is_active', true);
        }

        $companyId = static::currentCompanyId();

        if ($companyId && Schema::hasColumn('warehouses', 'company_id')) {
            $query->where('company_id', $companyId);
        } elseif (Schema::hasColumn('warehouses', 'company_id')) {
            $query->whereNull('company_id');
        }

        return $query
            ->orderBy(Schema::hasColumn('warehouses', 'name') ? 'name' : 'id')
            ->get(['id', 'code', 'name'])
            ->mapWithKeys(fn ($warehouse): array => [
                $warehouse->id => trim(($warehouse->code ? $warehouse->code . ' - ' : '') . $warehouse->name),
            ])
            ->all();
    }

    protected static function locationOptions($warehouseId): array
    {
        if (! $warehouseId || ! Schema::hasTable('stock_locations')) {
            return [];
        }

        $query = DB::table('stock_locations')
            ->leftJoin('stock_location_types', 'stock_location_types.id', '=', 'stock_locations.stock_location_type_id')
            ->where('stock_locations.warehouse_id', $warehouseId);

        if (Schema::hasColumn('stock_locations', 'is_active')) {
            $query->where('stock_locations.is_active', true);
        }

        $query->where(function (Builder $query): void {
            $query
                ->where('stock_location_types.is_internal', true)
                ->orWhereNull('stock_location_types.id');
        });

        $companyId = static::currentCompanyId();

        if ($companyId && Schema::hasColumn('stock_locations', 'company_id')) {
            $query->where('stock_locations.company_id', $companyId);
        } elseif (Schema::hasColumn('stock_locations', 'company_id')) {
            $query->whereNull('stock_locations.company_id');
        }

        return $query
            ->orderBy('stock_locations.name')
            ->get([
                'stock_locations.id',
                'stock_locations.code',
                'stock_locations.name',
            ])
            ->mapWithKeys(fn ($location): array => [
                $location->id => trim(($location->code ? $location->code . ' - ' : '') . $location->name),
            ])
            ->all();
    }
}
