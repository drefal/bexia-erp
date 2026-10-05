<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpenseReportResource\Pages;
use App\Models\Employee;
use App\Models\ExpenseCategory;
use App\Models\ExpenseReport;
use App\Models\PettyCashFund;
use App\Support\Expenses\ExpenseReceiptService;
use App\Support\Expenses\ExpenseTaxCalculator;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ExpenseReportResource extends Resource
{
    protected static ?string $model = ExpenseReport::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationGroup = 'Gastos';

    protected static ?string $navigationLabel = 'Comprobaciones';

    protected static ?string $modelLabel = 'comprobación';

    protected static ?string $pluralModelLabel = 'comprobaciones';

    protected static ?int $navigationSort = 20;

    protected static bool $isScopedToTenant = false;

    protected static ?string $tenantOwnershipRelationshipName = null;

    protected static function currentCompanyId(): ?int
    {
        $tenant = Filament::getTenant();

        return $tenant?->getKey()
            ? (int) $tenant->getKey()
            : null;
    }

    protected static function bexiaCanExpensePermission(string $permission): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ((bool) ($user->is_system_admin ?? false)) {
            return true;
        }

        return $user->can($permission);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::bexiaCanExpensePermission('treasury.view')
            || static::bexiaCanExpensePermission('treasury.update')
            || static::bexiaCanExpensePermission('treasury.create');
    }

    public static function canViewAny(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return static::bexiaCanExpensePermission('treasury.create')
            || static::bexiaCanExpensePermission('treasury.update');
    }

    public static function canEdit(Model $record): bool
    {
        return static::bexiaCanExpensePermission('treasury.update')
            && (string) $record->status === 'draft';
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = static::getModel()::query()
            ->with([
                'employee',
                'pettyCashFund',
                'lines.spentByEmployee',
            ]);

        $companyId = static::currentCompanyId();

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        return $query->latest('id');
    }

    protected static function employeeOptions(): array
    {
        $companyId = static::currentCompanyId();

        if (! $companyId) {
            return [];
        }

        return Employee::query()
            ->where('company_id', $companyId)
            ->where('active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    protected static function categoryOptions(): array
    {
        $companyId = static::currentCompanyId();

        if (! $companyId) {
            return [];
        }

        return ExpenseCategory::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    protected static function fundOptions(): array
    {
        $companyId = static::currentCompanyId();

        if (! $companyId) {
            return [];
        }

        return PettyCashFund::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('status', 'active')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(function (PettyCashFund $fund): array {
                return [
                    $fund->id =>
                        ($fund->number ? $fund->number . ' · ' : '')
                        . $fund->name
                        . ' · Disponible $'
                        . number_format((float) $fund->operational_balance, 2),
                ];
            })
            ->toArray();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Comprobación')
                    ->description(
                        'El responsable del reporte y la persona que realizó cada gasto pueden ser distintos.'
                    )
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->label('Tipo')
                            ->options([
                                ExpenseReport::TYPE_PETTY_CASH => 'Caja chica',
                                ExpenseReport::TYPE_REIMBURSEMENT => 'Reembolso',
                            ])
                            ->default(ExpenseReport::TYPE_PETTY_CASH)
                            ->required()
                            ->live(),

                        Forms\Components\DatePicker::make('report_date')
                            ->label('Fecha de comprobación')
                            ->default(now()->toDateString())
                            ->native(false)
                            ->required(),

                        Forms\Components\Select::make('petty_cash_fund_id')
                            ->label('Caja chica')
                            ->options(fn (): array => static::fundOptions())
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required(
                                fn (Forms\Get $get): bool =>
                                    $get('type') === ExpenseReport::TYPE_PETTY_CASH
                            )
                            ->visible(
                                fn (Forms\Get $get): bool =>
                                    $get('type') === ExpenseReport::TYPE_PETTY_CASH
                            )
                            ->afterStateUpdated(function ($state, Forms\Set $set): void {
                                if (! $state) {
                                    return;
                                }

                                $fund = PettyCashFund::query()->find($state);

                                if ($fund) {
                                    $set('employee_id', $fund->employee_id);
                                }
                            })
                            ->helperText(
                                'Al seleccionar una caja se propone como responsable a su custodio.'
                            ),

                        Forms\Components\Select::make('employee_id')
                            ->label('Responsable del reporte')
                            ->options(fn (): array => static::employeeOptions())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText(
                                'Es quien presenta o administra esta comprobación. '
                                . 'No necesariamente realizó todos los gastos.'
                            ),

                        Forms\Components\Textarea::make('purpose')
                            ->label('Motivo / propósito')
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notas generales')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Detalle de gastos')
                    ->description(
                        'Cada renglón identifica a la persona que realmente hizo el gasto. '
                        . 'Puede ser diferente al responsable de la caja o a quien captura.'
                    )
                    ->schema([
                        Forms\Components\Repeater::make('lines')
                            ->relationship('lines')
                            ->label('')
                            ->defaultItems(1)
                            ->minItems(1)
                            ->addActionLabel('Agregar otro gasto')
                            ->reorderable(false)
                            ->collapsible()
                            ->itemLabel(
                                fn (array $state): ?string =>
                                    ($state['description'] ?? null)
                                        ? ($state['description']
                                            . (
                                                isset($state['total_amount'])
                                                ? ' · $' . number_format(
                                                    (float) $state['total_amount'],
                                                    2
                                                )
                                                : ''
                                            ))
                                        : 'Nuevo gasto'
                            )
                            ->mutateRelationshipDataBeforeFillUsing(
                                fn (array $data): array =>
                                    ExpenseReceiptService::prepareLineForFill($data)
                            )
                            ->mutateRelationshipDataBeforeCreateUsing(
                                fn (array $data): array =>
                                    ExpenseReceiptService::prepareLineForSave($data)
                            )
                            ->mutateRelationshipDataBeforeSaveUsing(
                                fn (array $data): array =>
                                    ExpenseReceiptService::prepareLineForSave($data)
                            )
                            ->schema([
                                Forms\Components\Select::make('spent_by_employee_id')
                                    ->label('Quién realizó el gasto')
                                    ->options(fn (): array => static::employeeOptions())
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->helperText(
                                        'Puede seleccionar cualquier empleado activo de la empresa.'
                                    )
                                    ->columnSpanFull(),

                                Forms\Components\Select::make('expense_category_id')
                                    ->label('Categoría')
                                    ->options(fn (): array => static::categoryOptions())
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->placeholder('Sin categoría'),

                                Forms\Components\DatePicker::make('expense_date')
                                    ->label('Fecha del gasto')
                                    ->default(now()->toDateString())
                                    ->native(false)
                                    ->required(),

                                Forms\Components\TextInput::make('supplier_name')
                                    ->label('Proveedor')
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('supplier_rfc')
                                    ->label('RFC proveedor')
                                    ->maxLength(20),

                                Forms\Components\TextInput::make('description')
                                    ->label('Concepto / descripción')
                                    ->required()
                                    ->maxLength(500)
                                    ->columnSpan(2),

                                Forms\Components\Select::make('iva_mode')
                                    ->label('Impuesto IVA')
                                    ->options(
                                        ExpenseTaxCalculator::options()
                                    )
                                    ->default(
                                        ExpenseTaxCalculator::MODE_16
                                    )
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(
                                        function (
                                            $state,
                                            Forms\Get $get,
                                            Forms\Set $set
                                        ): void {
                                            if (
                                                ! ExpenseTaxCalculator::isAutomatic(
                                                    $state
                                                )
                                            ) {
                                                return;
                                            }

                                            $total = $get('total_amount');

                                            if (
                                                $total !== null
                                                && $total !== ''
                                                && (float) $total > 0
                                            ) {
                                                $result =
                                                    ExpenseTaxCalculator::fromTotal(
                                                        $total,
                                                        $state
                                                    );
                                            } else {
                                                $result =
                                                    ExpenseTaxCalculator::fromSubtotal(
                                                        $get('subtotal'),
                                                        $state
                                                    );
                                            }

                                            $set(
                                                'subtotal',
                                                $result['subtotal']
                                            );
                                            $set(
                                                'tax_amount',
                                                $result['tax_amount']
                                            );
                                            $set(
                                                'total_amount',
                                                $result['total_amount']
                                            );
                                        }
                                    )
                                    ->helperText(
                                        'Selecciona la tasa para calcular automáticamente subtotal, IVA y total.'
                                    ),

                                Forms\Components\TextInput::make('subtotal')
                                    ->label('Subtotal sin IVA')
                                    ->numeric()
                                    ->prefix('$')
                                    ->default(0)
                                    ->minValue(0)
                                    ->required()
                                    ->live(debounce: 500)
                                    ->afterStateUpdated(
                                        function (
                                            $state,
                                            Forms\Get $get,
                                            Forms\Set $set
                                        ): void {
                                            $mode = $get('iva_mode');

                                            if (
                                                ExpenseTaxCalculator::isAutomatic(
                                                    $mode
                                                )
                                            ) {
                                                $result =
                                                    ExpenseTaxCalculator::fromSubtotal(
                                                        $state,
                                                        $mode
                                                    );

                                                $set(
                                                    'tax_amount',
                                                    $result['tax_amount']
                                                );
                                                $set(
                                                    'total_amount',
                                                    $result['total_amount']
                                                );

                                                return;
                                            }

                                            $set(
                                                'total_amount',
                                                ExpenseTaxCalculator::manualTotal(
                                                    $state,
                                                    $get('tax_amount')
                                                )
                                            );
                                        }
                                    ),

                                Forms\Components\TextInput::make('tax_amount')
                                    ->label('IVA / impuestos')
                                    ->numeric()
                                    ->prefix('$')
                                    ->default(0)
                                    ->minValue(0)
                                    ->required()
                                    ->readOnly(
                                        fn (Forms\Get $get): bool =>
                                            ExpenseTaxCalculator::isAutomatic(
                                                $get('iva_mode')
                                            )
                                    )
                                    ->live(debounce: 500)
                                    ->afterStateUpdated(
                                        function (
                                            $state,
                                            Forms\Get $get,
                                            Forms\Set $set
                                        ): void {
                                            if (
                                                ExpenseTaxCalculator::isAutomatic(
                                                    $get('iva_mode')
                                                )
                                            ) {
                                                return;
                                            }

                                            $set(
                                                'total_amount',
                                                ExpenseTaxCalculator::manualTotal(
                                                    $get('subtotal'),
                                                    $state
                                                )
                                            );
                                        }
                                    ),

                                Forms\Components\TextInput::make('total_amount')
                                    ->label('Total con IVA')
                                    ->numeric()
                                    ->prefix('$')
                                    ->minValue(0.01)
                                    ->required()
                                    ->live(debounce: 500)
                                    ->afterStateUpdated(
                                        function (
                                            $state,
                                            Forms\Get $get,
                                            Forms\Set $set
                                        ): void {
                                            $mode = $get('iva_mode');

                                            if (
                                                ! ExpenseTaxCalculator::isAutomatic(
                                                    $mode
                                                )
                                            ) {
                                                return;
                                            }

                                            $result =
                                                ExpenseTaxCalculator::fromTotal(
                                                    $state,
                                                    $mode
                                                );

                                            $set(
                                                'subtotal',
                                                $result['subtotal']
                                            );
                                            $set(
                                                'tax_amount',
                                                $result['tax_amount']
                                            );
                                        }
                                    )
                                    ->helperText(
                                        'Con una tasa automática, puedes capturar solo el total y Bexia obtiene subtotal e IVA.'
                                    ),

                                Forms\Components\Hidden::make('sat_payment_form'),

                                Forms\Components\Hidden::make('sat_payment_method'),

                                Forms\Components\Select::make('payment_method')
                                    ->label('Forma de pago')
                                    ->options([
                                        'cash' => 'Efectivo',
                                        'card' => 'Tarjeta',
                                        'transfer' => 'Transferencia',
                                        'personal' => 'Pago personal',
                                        'other' => 'Otro',
                                    ])
                                    ->searchable()
                                    ->required(),

                                Forms\Components\Toggle::make('has_receipt')
                                    ->label('Tiene comprobante')
                                    ->default(false)
                                    ->live(),

                                Forms\Components\FileUpload::make('receipt_xml_path')
                                    ->label('XML CFDI')
                                    ->disk('local')
                                    ->directory(
                                        fn (): string =>
                                            'expense-receipts/'
                                            . (static::currentCompanyId() ?? 'sin-empresa')
                                            . '/xml'
                                    )
                                    ->visibility('private')
                                    ->acceptedFileTypes([
                                        'application/xml',
                                        'text/xml',
                                    ])
                                    ->maxSize(10240)
                                    ->downloadable()
                                    ->openable()
                                    ->visible(
                                        fn (Forms\Get $get): bool =>
                                            (bool) $get('has_receipt')
                                    )
                                    ->helperText(
                                        'Si es CFDI, Bexia intentará obtener automáticamente el UUID.'
                                    )
                                    ->afterStateUpdated(
                                        function ($state, Forms\Set $set): void {
                                            $cfdi = ExpenseReceiptService::extractCfdiDataFromUpload(
                                                $state
                                            );

                                            if (! $cfdi) {
                                                return;
                                            }

                                            if (! empty($cfdi['supplier_name'])) {
                                                $set(
                                                    'supplier_name',
                                                    $cfdi['supplier_name']
                                                );
                                            }

                                            if (! empty($cfdi['supplier_rfc'])) {
                                                $set(
                                                    'supplier_rfc',
                                                    $cfdi['supplier_rfc']
                                                );
                                            }

                                            if (! empty($cfdi['expense_date'])) {
                                                $set(
                                                    'expense_date',
                                                    $cfdi['expense_date']
                                                );
                                            }

                                            if (! empty($cfdi['cfdi_uuid'])) {
                                                $set(
                                                    'cfdi_uuid',
                                                    $cfdi['cfdi_uuid']
                                                );
                                            }

                                            if (! empty($cfdi['payment_method'])) {
                                                $set(
                                                    'payment_method',
                                                    $cfdi['payment_method']
                                                );
                                            }

                                            $set(
                                                'sat_payment_form',
                                                $cfdi['sat_payment_form'] ?? null
                                            );

                                            $set(
                                                'sat_payment_method',
                                                $cfdi['sat_payment_method'] ?? null
                                            );

                                            /*
                                             * En XML los importes exactos tienen prioridad.
                                             * Primero ponemos manual para evitar que los
                                             * listeners de IVA los recalculen.
                                             */
                                            $set(
                                                'iva_mode',
                                                ExpenseTaxCalculator::MODE_MANUAL
                                            );

                                            if ($cfdi['subtotal'] !== null) {
                                                $set(
                                                    'subtotal',
                                                    $cfdi['subtotal']
                                                );
                                            }

                                            if ($cfdi['tax_amount'] !== null) {
                                                $set(
                                                    'tax_amount',
                                                    $cfdi['tax_amount']
                                                );
                                            }

                                            if ($cfdi['total_amount'] !== null) {
                                                $set(
                                                    'total_amount',
                                                    $cfdi['total_amount']
                                                );
                                            }

                                            /*
                                             * Si la factura tiene una sola tasa simple,
                                             * mostramos cuál fue detectada, pero después
                                             * de cargar los importes exactos.
                                             */
                                            $detectedMode =
                                                $cfdi['iva_mode'] ?? 'manual';

                                            $set(
                                                'iva_mode',
                                                $detectedMode
                                            );
                                        }
                                    ),

                                Forms\Components\FileUpload::make('receipt_pdf_path')
                                    ->label('PDF comprobante')
                                    ->disk('local')
                                    ->directory(
                                        fn (): string =>
                                            'expense-receipts/'
                                            . (static::currentCompanyId() ?? 'sin-empresa')
                                            . '/pdf'
                                    )
                                    ->visibility('private')
                                    ->acceptedFileTypes([
                                        'application/pdf',
                                    ])
                                    ->maxSize(10240)
                                    ->downloadable()
                                    ->openable()
                                    ->visible(
                                        fn (Forms\Get $get): bool =>
                                            (bool) $get('has_receipt')
                                    )
                                    ->helperText(
                                        'Si solo hay PDF, Bexia intentará localizar un UUID visible dentro del documento.'
                                    )
                                    ->afterStateUpdated(
                                        function ($state, Forms\Set $set): void {
                                            $uuid = ExpenseReceiptService::extractUuidFromUpload(
                                                $state
                                            );

                                            if ($uuid) {
                                                $set('cfdi_uuid', $uuid);
                                            }
                                        }
                                    ),

                                Forms\Components\TextInput::make('cfdi_uuid')
                                    ->label('UUID CFDI')
                                    ->visible(
                                        fn (Forms\Get $get): bool =>
                                            (bool) $get('has_receipt')
                                    )
                                    ->helperText(
                                        'Se llena automáticamente cuando Bexia puede leerlo; también puede capturarse manualmente.'
                                    )
                                    ->maxLength(36)
                                    ->columnSpan(2),

                                Forms\Components\Textarea::make('receipt_exception_reason')
                                    ->label('Motivo por el que no tiene comprobante')
                                    ->rows(2)
                                    ->visible(
                                        fn (Forms\Get $get): bool =>
                                            ! (bool) $get('has_receipt')
                                    )
                                    ->columnSpan(2),

                                Forms\Components\Textarea::make('notes')
                                    ->label('Notas del gasto')
                                    ->rows(2)
                                    ->columnSpan(2),
                            ])
                            ->columns(4)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Totales')
                    ->columns(3)
                    ->schema([
                        Forms\Components\Placeholder::make('subtotal_display')
                            ->label('Subtotal')
                            ->content(
                                fn (?ExpenseReport $record): string =>
                                    '$' . number_format(
                                        (float) ($record?->subtotal ?? 0),
                                        2
                                    )
                            ),

                        Forms\Components\Placeholder::make('tax_display')
                            ->label('IVA / impuestos')
                            ->content(
                                fn (?ExpenseReport $record): string =>
                                    '$' . number_format(
                                        (float) ($record?->tax_amount ?? 0),
                                        2
                                    )
                            ),

                        Forms\Components\Placeholder::make('total_display')
                            ->label('Total')
                            ->content(
                                fn (?ExpenseReport $record): string =>
                                    '$' . number_format(
                                        (float) ($record?->total_amount ?? 0),
                                        2
                                    )
                            ),
                    ]),

                Forms\Components\Section::make('Aplicación financiera')
                    ->description(
                        'Resultado financiero de la comprobación una vez aprobada.'
                    )
                    ->columns(5)
                    ->visible(
                        fn (?ExpenseReport $record): bool =>
                            $record !== null
                            && in_array(
                                (string) $record->status,
                                [
                                    'approved',
                                    'paid',
                                    'closed',
                                ],
                                true
                            )
                    )
                    ->schema([
                        Forms\Components\Placeholder::make(
                            'financial_application_status'
                        )
                            ->label('Estado')
                            ->content(
                                fn (?ExpenseReport $record): string =>
                                    $record?->treasury_movement_id
                                        ? 'Aplicada'
                                        : 'Pendiente de aplicación'
                            ),

                        Forms\Components\Placeholder::make(
                            'financial_treasury_movement'
                        )
                            ->label('Movimiento Tesorería')
                            ->content(
                                fn (?ExpenseReport $record): string =>
                                    $record?->treasury_movement_id
                                        ? '#'
                                            . $record->treasury_movement_id
                                        : '—'
                            ),

                        Forms\Components\Placeholder::make(
                            'financial_balance_before'
                        )
                            ->label('Saldo anterior')
                            ->content(
                                function (
                                    ?ExpenseReport $record
                                ): string {
                                    $value = data_get(
                                        $record?->metadata,
                                        'petty_cash_posting.balance_before'
                                    );

                                    return $value !== null
                                        ? '$'
                                            . number_format(
                                                (float) $value,
                                                2
                                            )
                                        : '—';
                                }
                            ),

                        Forms\Components\Placeholder::make(
                            'financial_expense_amount'
                        )
                            ->label('Gasto aplicado')
                            ->content(
                                function (
                                    ?ExpenseReport $record
                                ): string {
                                    $value = data_get(
                                        $record?->metadata,
                                        'petty_cash_posting.amount',
                                        $record?->total_amount
                                    );

                                    return $value !== null
                                        ? '$'
                                            . number_format(
                                                (float) $value,
                                                2
                                            )
                                        : '—';
                                }
                            ),

                        Forms\Components\Placeholder::make(
                            'financial_balance_after'
                        )
                            ->label('Saldo restante')
                            ->content(
                                function (
                                    ?ExpenseReport $record
                                ): string {
                                    $value = data_get(
                                        $record?->metadata,
                                        'petty_cash_posting.balance_after'
                                    );

                                    return $value !== null
                                        ? '$'
                                            . number_format(
                                                (float) $value,
                                                2
                                            )
                                        : '—';
                                }
                            ),

                        Forms\Components\Placeholder::make(
                            'financial_posted_at'
                        )
                            ->label('Fecha de aplicación')
                            ->content(
                                fn (?ExpenseReport $record): string =>
                                    (string) (
                                        data_get(
                                            $record?->metadata,
                                            'petty_cash_posting.posted_at'
                                        )
                                        ?? '—'
                                    )
                            )
                            ->columnSpanFull(),
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
                    ->placeholder('Sin folio'),

                Tables\Columns\TextColumn::make('report_date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string => match ($state) {
                            ExpenseReport::TYPE_PETTY_CASH => 'Caja chica',
                            ExpenseReport::TYPE_REIMBURSEMENT => 'Reembolso',
                            default => $state ?: '—',
                        }
                    ),

                Tables\Columns\TextColumn::make('employee.name')
                    ->label('Responsable')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('pettyCashFund.name')
                    ->label('Caja')
                    ->placeholder('—')
                    ->wrap(),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('MXN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string => match ($state) {
                            'draft' => 'Borrador',
                            'submitted' => 'Enviada',
                            'pending_approval' => 'Pendiente de aprobación',
                            'approved' => 'Aprobada',
                            'rejected' => 'Rechazada',
                            'paid' => 'Pagada',
                            'closed' => 'Cerrada',
                            'cancelled' => 'Cancelada',
                            default => $state ?: '—',
                        }
                    )
                    ->color(
                        fn (?string $state): string => match ($state) {
                            'draft' => 'gray',
                            'submitted', 'pending_approval' => 'warning',
                            'approved' => 'success',
                            'rejected', 'cancelled' => 'danger',
                            'paid', 'closed' => 'primary',
                            default => 'gray',
                        }
                    ),

                Tables\Columns\TextColumn::make('lines_count')
                    ->counts('lines')
                    ->label('Gastos'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        ExpenseReport::TYPE_PETTY_CASH => 'Caja chica',
                        ExpenseReport::TYPE_REIMBURSEMENT => 'Reembolso',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'draft' => 'Borrador',
                        'submitted' => 'Enviada',
                        'pending_approval' => 'Pendiente de aprobación',
                        'approved' => 'Aprobada',
                        'rejected' => 'Rechazada',
                        'paid' => 'Pagada',
                        'closed' => 'Cerrada',
                        'cancelled' => 'Cancelada',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Ver'),

                Tables\Actions\EditAction::make()
                    ->visible(
                        fn (ExpenseReport $record): bool =>
                            static::canEdit($record)
                    ),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExpenseReports::route('/'),
            'create' => Pages\CreateExpenseReport::route('/create'),
            'view' => Pages\ViewExpenseReport::route('/{record}'),
            'edit' => Pages\EditExpenseReport::route('/{record}/edit'),
        ];
    }
}
