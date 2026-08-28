<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeePayrollPurchaseResource\Pages;
use App\Models\Employee;
use App\Models\EmployeePayrollPurchase;
use App\Models\Product;
use App\Support\EmployeePayrollPurchaseService;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EmployeePayrollPurchaseResource extends Resource
{
    protected static ?string $model = EmployeePayrollPurchase::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationGroup = 'Nómina';
    protected static ?string $navigationLabel = 'Compras vía nómina';
    protected static ?string $modelLabel = 'compra vía nómina';
    protected static ?string $pluralModelLabel = 'compras vía nómina';
    protected static ?int $navigationSort = 25;

    protected static bool $isScopedToTenant = false;
    protected static ?string $tenantOwnershipRelationshipName = null;

    protected static function canManage(string $permission): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ((bool) ($user->is_system_admin ?? false)) {
            return true;
        }

        if (($user->email ?? null) === 'admin@bexiaerp.com') {
            return true;
        }

        return $user->can($permission) || $user->can('company.update');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canManage('nomina.descuentos.ver');
    }

    public static function canViewAny(): bool
    {
        return static::canManage('nomina.descuentos.ver');
    }

    public static function canView(Model $record): bool
    {
        return static::canManage('nomina.descuentos.ver');
    }

    public static function canCreate(): bool
    {
        return static::canManage('nomina.descuentos.crear');
    }

    public static function canEdit(Model $record): bool
    {
        return static::canManage('nomina.descuentos.editar')
            && (string) $record->status === 'draft';
    }

    public static function canDelete(Model $record): bool
    {
        return static::canManage('nomina.descuentos.eliminar')
            && (string) $record->status === 'draft';
    }

    protected static function tenantId(): ?int
    {
        $id = Filament::getTenant()?->getKey();

        return $id ? (int) $id : null;
    }

    protected static function employeeOptions(?int $companyId): array
    {
        if (! $companyId) {
            return [];
        }

        return Employee::query()
            ->select(['id', 'name', 'employee_number'])
            ->where('company_id', $companyId)
            ->where('active', true)
            ->orderBy('name')
            ->limit(200)
            ->get()
            ->mapWithKeys(fn (Employee $employee) => [
                $employee->id => trim(
                    $employee->name
                    . ($employee->employee_number ? ' · ' . $employee->employee_number : '')
                ),
            ])
            ->all();
    }

    protected static function employeeLabel($value): ?string
    {
        if (! $value) {
            return null;
        }

        $employee = Employee::query()->find($value);

        if (! $employee) {
            return null;
        }

        return trim(
            $employee->name
            . ($employee->employee_number ? ' · ' . $employee->employee_number : '')
        );
    }

    protected static function productInitialOptions(): array
    {
        $companyId = static::tenantId();

        if (! $companyId) {
            return [];
        }

        return Product::query()
            ->select(['id', 'sku', 'internal_reference', 'name', 'variant_name'])
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('can_be_sold', true)
            ->where('sale_price', '>', 0)
            // BEXIA_V5833D_HIDE_ODOO_HIST_FROM_PAYROLL_PURCHASE_SELECTOR
            // ODOO-HIST-* son productos auxiliares creados para conservar
            // referencias historicas de Odoo. Deben seguir existiendo,
            // pero no deben ofrecerse para nuevas compras de empleados.
            ->where(function ($query): void {
                $query->whereNull('sku')
                    ->orWhereRaw("sku NOT ILIKE ?", ['ODOO-HIST-%']);
            })
            ->orderBy('name')
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (Product $product) => [
                $product->id => static::productLabel($product),
            ])
            ->all();
    }

    protected static function productSearch(string $search): array
    {
        $companyId = static::tenantId();
        $search = trim($search);

        if (! $companyId) {
            return [];
        }

        if ($search === '') {
            return static::productInitialOptions();
        }

        return Product::query()
            ->select(['id', 'sku', 'internal_reference', 'name', 'variant_name'])
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('can_be_sold', true)
            ->where('sale_price', '>', 0)
            // BEXIA_V5833D_HIDE_ODOO_HIST_FROM_PAYROLL_PURCHASE_SELECTOR
            // ODOO-HIST-* son productos auxiliares creados para conservar
            // referencias historicas de Odoo. Deben seguir existiendo,
            // pero no deben ofrecerse para nuevas compras de empleados.
            ->where(function ($query): void {
                $query->whereNull('sku')
                    ->orWhereRaw("sku NOT ILIKE ?", ['ODOO-HIST-%']);
            })
            ->where(function (Builder $query) use ($search): void {
                $query
                    ->where('sku', 'ilike', $search . '%')
                    ->orWhere('internal_reference', 'ilike', $search . '%')
                    ->orWhere('barcode', 'ilike', $search . '%')
                    ->orWhere('name', 'ilike', '%' . $search . '%')
                    ->orWhere('variant_name', 'ilike', '%' . $search . '%');
            })
            ->orderBy('name')
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (Product $product) => [
                $product->id => static::productLabel($product),
            ])
            ->all();
    }

    protected static function productLabel(Product|int|string|null $product): ?string
    {
        if (! $product) {
            return null;
        }

        if (! $product instanceof Product) {
            $product = Product::query()->find($product);
        }

        if (! $product) {
            return null;
        }

        $name = trim((string) $product->name);
        $variant = trim((string) $product->variant_name);
        $reference = trim((string) ($product->internal_reference ?: $product->sku));

        if ($variant !== '') {
            $name .= ' / ' . $variant;
        }

        if ($reference !== '') {
            $name = $reference . ' · ' . $name;
        }

        return $name;
    }

    protected static function fillProductLine(
        $productId,
        Forms\Set $set,
        Forms\Get $get
    ): void {
        $product = $productId ? Product::query()->find($productId) : null;

        if (! $product) {
            return;
        }

        $gross = EmployeePayrollPurchaseService::suggestedGrossPrice($product);

        $set('company_id', $product->company_id);
        $set('product_sku', $product->sku);
        $set('product_reference', $product->internal_reference);
        $set('product_name', $product->name);
        $set('variant_name', $product->variant_name);
        $set('tax_rate', (float) ($product->sale_tax_rate ?? 0));
        $set('unit_price_with_tax', $gross);

        static::recalculateLine($set, $get, $gross);
    }

    protected static function recalculateLine(
        Forms\Set $set,
        Forms\Get $get,
        mixed $grossOverride = null
    ): void {
        $quantity = max(0, (float) ($get('quantity') ?: 0));
        $taxRate = max(0, (float) ($get('tax_rate') ?: 0));
        $gross = max(
            0,
            (float) (
                $grossOverride !== null
                    ? $grossOverride
                    : ($get('unit_price_with_tax') ?: 0)
            )
        );

        $factor = 1 + ($taxRate / 100);
        $base = $factor > 0 ? round($gross / $factor, 4) : round($gross, 4);
        $subtotal = round($base * $quantity, 2);
        $total = round($gross * $quantity, 2);
        $tax = round($total - $subtotal, 2);

        $set('unit_price_without_tax', $base);
        $set('line_subtotal', $subtotal);
        $set('line_tax', $tax);
        $set('line_total', $total);
    }

    protected static function draftTotal(Forms\Get $get): float
    {
        $lines = $get('lines') ?: [];

        return round(array_sum(array_map(
            fn ($line): float => (float) ($line['line_total'] ?? 0),
            is_array($lines) ? $lines : []
        )), 2);
    }

    public static function form(Form $form): Form
    {
        $tenantId = static::tenantId();

        return $form->schema([
            Forms\Components\Section::make('Empleado')
                ->extraAttributes([
                    'class' => 'bexia-payroll-purchase-section bexia-payroll-purchase-section-employee',
                ])
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('company_id')
                        ->label('Empresa')
                        ->relationship('company', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->default($tenantId)
                        ->disabled(filled($tenantId))
                        ->dehydrated()
                        ->live(),

                    Forms\Components\Select::make('employee_id')
                        ->label('Empleado')
                        ->extraAttributes([
                            'class' => 'bexia-payroll-purchase-select bexia-payroll-purchase-employee-select',
                        ])
                        ->options(
                            fn (Forms\Get $get): array =>
                                static::employeeOptions(
                                    (int) ($get('company_id') ?: $tenantId)
                                )
                        )
                        ->searchable()
                        ->preload()
                        ->getOptionLabelUsing(
                            fn ($value): ?string => static::employeeLabel($value)
                        )
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, Forms\Set $set): void {
                            $employee = $state ? Employee::query()->find($state) : null;
                            $set('branch_id', $employee?->branch_id);
                        }),

                    Forms\Components\Hidden::make('branch_id'),

                    Forms\Components\TextInput::make('number')
                        ->label('Folio')
                        ->disabled()
                        ->dehydrated(false)
                        ->placeholder('Se genera al guardar'),

                    Forms\Components\DatePicker::make('purchase_date')
                        ->label('Fecha de compra')
                        ->native(false)
                        ->default(now())
                        ->required(),

                    Forms\Components\Select::make('status')
                        ->label('Estado')
                        ->options(EmployeePayrollPurchase::statusOptions())
                        ->default('draft')
                        ->disabled()
                        ->dehydrated(),
                ]),

            Forms\Components\Section::make('Productos')
                ->extraAttributes([
                    'class' => 'bexia-payroll-purchase-section bexia-payroll-purchase-section-products',
                ])
                ->description(
                    'Selecciona productos del catálogo. El precio sugerido incluye IVA y se puede ajustar antes de confirmar. '
                    . 'Esta versión no genera movimientos de inventario.'
                )
                ->schema([
                    Forms\Components\Repeater::make('lines')
                        ->relationship()
                        ->extraAttributes([
                            'class' => 'bexia-payroll-purchase-repeater',
                        ])
                        ->label('')
                        ->minItems(1)
                        ->defaultItems(1)
                        ->addActionLabel('Agregar producto')
                        ->columns(12)
                        ->live()
                        ->schema([
                            Forms\Components\Select::make('product_id')
                                ->label('Producto')
                                ->extraAttributes([
                                    'class' => 'bexia-payroll-purchase-select bexia-payroll-purchase-product-select',
                                ])
                                ->options(fn (): array => static::productInitialOptions())
                                ->searchable()
                                ->preload()
                                ->getSearchResultsUsing(
                                    fn (string $search): array => static::productSearch($search)
                                )
                                ->getOptionLabelUsing(
                                    fn ($value): ?string => static::productLabel($value)
                                )
                                ->required()
                                ->live()
                                ->columnSpan(6)
                                ->afterStateUpdated(
                                    fn ($state, Forms\Set $set, Forms\Get $get) =>
                                        static::fillProductLine($state, $set, $get)
                                ),

                            Forms\Components\TextInput::make('quantity')
                                ->label('Cantidad')
                                ->numeric()
                                ->minValue(0.0001)
                                ->step('0.0001')
                                ->default(1)
                                ->required()
                                ->live(onBlur: true)
                                ->columnSpan(2)
                                ->afterStateUpdated(
                                    fn ($state, Forms\Set $set, Forms\Get $get) =>
                                        static::recalculateLine($set, $get)
                                ),

                            Forms\Components\TextInput::make('unit_price_with_tax')
                                ->label('Precio c/IVA')
                                ->numeric()
                                ->minValue(0.01)
                                ->prefix('$')
                                ->required()
                                ->live(onBlur: true)
                                ->columnSpan(2)
                                ->afterStateUpdated(
                                    fn ($state, Forms\Set $set, Forms\Get $get) =>
                                        static::recalculateLine($set, $get, $state)
                                ),

                            Forms\Components\TextInput::make('line_total')
                                ->label('Total')
                                ->numeric()
                                ->prefix('$')
                                ->disabled()
                                ->dehydrated()
                                ->columnSpan(2),

                            Forms\Components\Hidden::make('company_id')->default($tenantId),
                            Forms\Components\Hidden::make('product_sku'),
                            Forms\Components\Hidden::make('product_reference'),
                            Forms\Components\Hidden::make('product_name'),
                            Forms\Components\Hidden::make('variant_name'),
                            Forms\Components\Hidden::make('tax_rate'),
                            Forms\Components\Hidden::make('unit_price_without_tax'),
                            Forms\Components\Hidden::make('line_subtotal'),
                            Forms\Components\Hidden::make('line_tax'),
                        ]),
                ]),

            Forms\Components\Section::make('Financiamiento')
                ->extraAttributes([
                    'class' => 'bexia-payroll-purchase-section bexia-payroll-purchase-section-financing',
                ])
                ->columns(4)
                ->schema([
                    Forms\Components\Select::make('frequency')
                        ->label('Periodicidad')
                        ->options(EmployeePayrollPurchase::frequencyOptions())
                        ->default('weekly')
                        ->required()
                        ->live(),

                    Forms\Components\TextInput::make('installments_count')
                        ->label('Número de pagos')
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->maxValue(104)
                        ->default(1)
                        ->required()
                        ->live(),

                    Forms\Components\DatePicker::make('first_deduction_date')
                        ->label('Primer descuento')
                        ->native(false)
                        ->default(now())
                        ->required(),

                    Forms\Components\Placeholder::make('draft_total')
                        ->label('Total estimado')
                        ->content(
                            fn (Forms\Get $get): string =>
                                '$ ' . number_format(static::draftTotal($get), 2)
                        ),

                    Forms\Components\Placeholder::make('draft_installment')
                        ->label('Pago estimado')
                        ->content(function (Forms\Get $get): string {
                            $count = max(1, (int) ($get('installments_count') ?: 1));
                            $amount = round(static::draftTotal($get) / $count, 2);

                            return '$ ' . number_format($amount, 2);
                        }),

                    Forms\Components\Textarea::make('notes')
                        ->label('Notas')
                        ->rows(3)
                        ->columnSpan(3),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('Folio')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('employee.name')
                    ->label('Empleado')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('purchase_date')
                    ->label('Compra')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('frequency')
                    ->label('Periodicidad')
                    ->formatStateUsing(
                        fn (?string $state): string =>
                            EmployeePayrollPurchase::frequencyOptions()[$state] ?? ($state ?: '-')
                    )
                    ->badge(),

                Tables\Columns\TextColumn::make('installments_count')
                    ->label('Pagos')
                    ->numeric(),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('MXN'),

                Tables\Columns\TextColumn::make('next_due')
                    ->label('Próximo descuento')
                    ->state(
                        fn (EmployeePayrollPurchase $record) =>
                            $record->installments()->where('status', 'pending')->min('due_date')
                    )
                    ->date('d/m/Y'),

                Tables\Columns\TextColumn::make('deduction.outstanding_amount')
                    ->label('Saldo')
                    ->money('MXN'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->formatStateUsing(
                        fn (?string $state): string =>
                            EmployeePayrollPurchase::statusOptions()[$state] ?? ($state ?: '-')
                    )
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'confirmed' => 'info',
                        'paid' => 'success',
                        'cancelled' => 'gray',
                        default => 'warning',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options(EmployeePayrollPurchase::statusOptions()),

                Tables\Filters\SelectFilter::make('frequency')
                    ->label('Periodicidad')
                    ->options(EmployeePayrollPurchase::frequencyOptions()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(
                        fn (EmployeePayrollPurchase $record): bool =>
                            (string) $record->status === 'draft'
                    ),

                Tables\Actions\Action::make('confirm')
                    ->label('Confirmar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Confirmar compra vía nómina')
                    ->modalDescription(
                        'Se congelarán precios, se generará el calendario de cuotas y se creará el descuento de nómina.'
                    )
                    ->visible(
                        fn (EmployeePayrollPurchase $record): bool =>
                            (string) $record->status === 'draft'
                            && static::canManage('nomina.descuentos.crear')
                    )
                    ->action(function (EmployeePayrollPurchase $record): void {
                        EmployeePayrollPurchaseService::confirm($record, auth()->id());

                        Notification::make()
                            ->title('Compra confirmada')
                            ->body('Calendario y descuento de nómina generados.')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('cancel')
                    ->label('Cancelar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(
                        fn (EmployeePayrollPurchase $record): bool =>
                            (string) $record->status === 'confirmed'
                            && static::canManage('nomina.descuentos.editar')
                    )
                    ->action(function (EmployeePayrollPurchase $record): void {
                        try {
                            EmployeePayrollPurchaseService::cancel($record, auth()->id());

                            Notification::make()
                                ->title('Compra cancelada')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('No se pudo cancelar')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\DeleteAction::make()
                    ->visible(
                        fn (EmployeePayrollPurchase $record): bool =>
                            (string) $record->status === 'draft'
                    ),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        $tenantId = static::tenantId();

        return parent::getEloquentQuery()
            ->with(['employee', 'deduction'])
            ->when(
                $tenantId,
                fn (Builder $query) => $query->where('company_id', $tenantId)
            );
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployeePayrollPurchases::route('/'),
            'create' => Pages\CreateEmployeePayrollPurchase::route('/create'),
            'edit' => Pages\EditEmployeePayrollPurchase::route('/{record}/edit'),
        ];
    }
}
