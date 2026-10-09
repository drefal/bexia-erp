<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpenseAdvanceResource\Pages;
use App\Models\Employee;
use App\Models\ExpenseAdvance;
use App\Support\Expenses\ExpenseAccess;
use App\Models\ExpenseReport;
use App\Filament\Resources\ExpenseReportResource;
use App\Models\PettyCashFund;
use App\Support\Expenses\ExpenseAdvanceService;
use App\Support\Security\BexiaTenantPermission;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ExpenseAdvanceResource extends Resource
{
    protected static ?string $model =
        ExpenseAdvance::class;

    protected static ?string $navigationIcon =
        'heroicon-o-currency-dollar';

    protected static ?string $navigationGroup =
        'Gastos';

    protected static ?string $navigationLabel =
        'Anticipos';

    protected static ?string $modelLabel =
        'anticipo';

    protected static ?string $pluralModelLabel =
        'anticipos';

    protected static ?int $navigationSort = 15;

    protected static bool $isScopedToTenant = false;

    protected static ?string
        $tenantOwnershipRelationshipName = null;

    protected static function currentCompanyId(): ?int
    {
        $tenant = Filament::getTenant();

        return $tenant?->getKey()
            ? (int) $tenant->getKey()
            : null;
    }

    protected static function canExpense(
        string $permission
    ): bool {
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

        return BexiaTenantPermission::can($permission);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canExpense('expenses.admin')
            || static::canExpense('expenses.view')
            || static::canExpense('expenses.create')
            || static::canExpense('expenses.update');
    }

    public static function canViewAny(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny()
            && ExpenseAccess::canAccessEmployee(
                static::currentCompanyId(),
                (int) $record->employee_id
            );
    }

    public static function canCreate(): bool
    {
        return static::canExpense('expenses.admin')
            || static::canExpense('expenses.create');
    }

    public static function canEdit(Model $record): bool
    {
        /*
         * Un anticipo formal no se edita después de crear.
         * Si hay un error se rechaza/cancela y se genera otro,
         * conservando trazabilidad.
         */
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with([
                'employee',
                'authorizedByEmployee',
                'pettyCashFund.treasuryAccount',
                'createdBy',
            ]);

        $companyId = static::currentCompanyId();

        if ($companyId) {
            $query->where(
                'company_id',
                $companyId
            );

            ExpenseAccess::scopeEmployeeQuery(
                $query,
                $companyId
            );
        }

        return $query;
    }

    protected static function employeeOptions(): array
    {
        $companyId = static::currentCompanyId();

        if (! $companyId) {
            return [];
        }

        return ExpenseAccess::employeeOptions(
            $companyId
        );
    }

    protected static function fundOptions(): array
    {
        $companyId = static::currentCompanyId();

        if (! $companyId) {
            return [];
        }

        return PettyCashFund::query()
            ->with('treasuryAccount')
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->where('is_active', true)
            ->orderBy('number')
            ->get()
            ->mapWithKeys(
                function (PettyCashFund $fund): array {
                    $balance = (float) (
                        $fund->treasuryAccount
                            ?->current_balance
                        ?? $fund->operational_balance
                        ?? 0
                    );

                    return [
                        $fund->id =>
                            ($fund->number
                                ?: ('#' . $fund->id))
                            . ' · '
                            . $fund->name
                            . ' · Disponible $'
                            . number_format(
                                $balance,
                                2
                            ),
                    ];
                }
            )
            ->toArray();
    }

    protected static function pendingWarning(
        ?int $employeeId,
        ?ExpenseAdvance $record = null
    ): string {
        $companyId = static::currentCompanyId();

        if (! $companyId || ! $employeeId) {
            return 'Selecciona un empleado para revisar anticipos pendientes.';
        }

        return app(ExpenseAdvanceService::class)
            ->pendingMessage(
                $companyId,
                $employeeId,
                $record?->id
            )
            ?: 'El empleado no tiene anticipos pendientes.';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(
                'Solicitud de anticipo'
            )
                ->description(
                    'Captura la solicitud. Al crearla se enviará a aprobación. '
                    . 'El dinero solo saldrá de la caja chica después de aprobarse.'
                )
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make(
                        'petty_cash_fund_id'
                    )
                        ->label('Caja chica origen')
                        ->options(
                            fn (): array =>
                                static::fundOptions()
                        )
                        ->searchable()
                        ->preload()
                        ->required(),

                    Forms\Components\Select::make(
                        'employee_id'
                    )
                        ->label(
                            'Empleado que recibe el anticipo'
                        )
                        ->options(
                            fn (): array =>
                                static::employeeOptions()
                        )
                        ->searchable()
                        ->preload()
                        ->live()
                        ->required()
                        ->afterStateUpdated(
                            function (
                                $state,
                                Forms\Set $set
                            ): void {
                                if (! $state) {
                                    $set(
                                        'authorized_by_employee_id',
                                        null
                                    );

                                    return;
                                }

                                $employee =
                                    \App\Models\Employee::query()
                                        ->whereKey((int) $state)
                                        ->where(
                                            'company_id',
                                            static::currentCompanyId()
                                        )
                                        ->where('active', true)
                                        ->first();

                                if (! $employee) {
                                    $set(
                                        'authorized_by_employee_id',
                                        null
                                    );

                                    return;
                                }

                                $set(
                                    'authorized_by_employee_id',
                                    $employee->manager_employee_id
                                        ? (int) $employee
                                            ->manager_employee_id
                                        : null
                                );
                            }
                        ),

                    Forms\Components\Select::make(
                        'authorized_by_employee_id'
                    )
                        ->label('Empleado que autoriza')
                        ->options(
                            fn (): array =>
                                static::employeeOptions()
                        )
                        ->searchable()
                        ->preload()
                        ->live()
                        ->required()
                        ->disabled(
                            function (
                                Forms\Get $get
                            ): bool {
                                $employeeId =
                                    (int) (
                                        $get('employee_id')
                                        ?? 0
                                    );

                                if ($employeeId <= 0) {
                                    return false;
                                }

                                return \App\Models\Employee::query()
                                    ->whereKey($employeeId)
                                    ->where(
                                        'company_id',
                                        static::currentCompanyId()
                                    )
                                    ->where('active', true)
                                    ->whereNotNull(
                                        'manager_employee_id'
                                    )
                                    ->exists();
                            }
                        )
                        ->dehydrated()
                        ->helperText(
                            function (
                                Forms\Get $get
                            ): string {
                                $employeeId =
                                    (int) (
                                        $get('employee_id')
                                        ?? 0
                                    );

                                if ($employeeId <= 0) {
                                    return
                                        'Selecciona primero al empleado '
                                        . 'que recibirá el anticipo.';
                                }

                                $employee =
                                    \App\Models\Employee::query()
                                        ->with('manager')
                                        ->whereKey($employeeId)
                                        ->where(
                                            'company_id',
                                            static::currentCompanyId()
                                        )
                                        ->first();

                                if (
                                    $employee?->manager_employee_id
                                    && $employee?->manager
                                ) {
                                    return
                                        'Se tomó automáticamente al '
                                        . 'jefe directo configurado: '
                                        . $employee->manager->name
                                        . '.';
                                }

                                return
                                    'El empleado no tiene jefe directo '
                                    . 'configurado. Selecciona '
                                    . 'manualmente quién autoriza.';
                            }
                        ),

                    Forms\Components\Placeholder::make(
                        'pending_advances_warning'
                    )
                        ->label(
                            'Anticipos pendientes'
                        )
                        ->content(
                            fn (
                                Forms\Get $get,
                                ?ExpenseAdvance $record
                            ): string =>
                                static::pendingWarning(
                                    $get('employee_id')
                                        ? (int) $get(
                                            'employee_id'
                                        )
                                        : null,
                                    $record
                                )
                        )
                        ->columnSpanFull(),

                    Forms\Components\DatePicker::make(
                        'request_date'
                    )
                        ->label('Fecha de solicitud')
                        ->native(true)
                        ->default(
                            now()->toDateString()
                        )
                        ->required(),

                    Forms\Components\TextInput::make(
                        'due_days'
                    )
                        ->label('Días para comprobar')
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->maxValue(365)
                        ->default(
                            function (): int {
                                $companyId =
                                    static::currentCompanyId();

                                if (! $companyId) {
                                    return 5;
                                }

                                return (int) app(
                                    ExpenseAdvanceService::class
                                )->settingsForCompany(
                                    $companyId
                                )->due_days;
                            }
                        )
                        ->helperText(
                            'El valor de configuración es solo el predeterminado. '
                            . 'Puedes cambiarlo para este anticipo.'
                        )
                        ->required(),

                    Forms\Components\TextInput::make(
                        'amount'
                    )
                        ->label('Importe')
                        ->numeric()
                        ->prefix('$')
                        ->minValue(0.01)
                        ->step('0.01')
                        ->required(),

                    Forms\Components\Textarea::make(
                        'purpose'
                    )
                        ->label('Motivo / propósito')
                        ->rows(3)
                        ->required()
                        ->maxLength(3000)
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make(
                        'notes'
                    )
                        ->label('Notas')
                        ->rows(3)
                        ->maxLength(3000)
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make(
                'Control'
            )
                ->columns(3)
                ->visible(
                    fn (?ExpenseAdvance $record): bool =>
                        $record !== null
                )
                ->schema([
                    Forms\Components\Placeholder::make(
                        'number_display'
                    )
                        ->label('Folio')
                        ->content(
                            fn (?ExpenseAdvance $record):
                                string =>
                                    $record?->number
                                    ?: 'Pendiente'
                        ),

                    Forms\Components\Placeholder::make(
                        'status_display'
                    )
                        ->label('Estado')
                        ->content(
                            fn (?ExpenseAdvance $record):
                                string =>
                                    ExpenseAdvance::statusLabel(
                                        $record?->status
                                    )
                        ),

                    Forms\Components\Placeholder::make(
                        'due_days_display'
                    )
                        ->label('Días para comprobar')
                        ->content(
                            fn (?ExpenseAdvance $record):
                                string =>
                                    (string) (
                                        $record?->due_days
                                        ?? 5
                                    )
                        ),

                    Forms\Components\Placeholder::make(
                        'due_date_display'
                    )
                        ->label('Fecha límite')
                        ->content(
                            fn (?ExpenseAdvance $record):
                                string =>
                                    $record?->due_date
                                        ? $record->due_date
                                            ->format('d/m/Y')
                                        : '—'
                        ),

                    Forms\Components\Placeholder::make(
                        'due_status_display'
                    )
                        ->label('Vencimiento')
                        ->content(
                            fn (?ExpenseAdvance $record):
                                string =>
                                    $record
                                        ? app(
                                            ExpenseAdvanceService::class
                                        )->dueStatusLabel(
                                            $record
                                        )
                                        : '—'
                        ),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make(
                    'number'
                )
                    ->label('Folio')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make(
                    'request_date'
                )
                    ->label('Solicitud')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make(
                    'employee.name'
                )
                    ->label('Empleado')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make(
                    'pettyCashFund.number'
                )
                    ->label('Caja')
                    ->description(
                        fn (ExpenseAdvance $record):
                            ?string =>
                                $record
                                    ->pettyCashFund
                                    ?->name
                    ),

                Tables\Columns\TextColumn::make(
                    'amount'
                )
                    ->label('Importe')
                    ->money(
                        fn (ExpenseAdvance $record):
                            string =>
                                $record->currency_code
                                ?: 'MXN'
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make(
                    'approved_reconciled_amount'
                )
                    ->label('Comprobado')
                    ->state(
                        fn (ExpenseAdvance $record):
                            float =>
                                round(
                                    $record
                                        ->approvedReconciledAmount(),
                                    2
                                )
                    )
                    ->money(
                        fn (ExpenseAdvance $record):
                            string =>
                                $record->currency_code
                                ?: 'MXN'
                    )
                    ->color(
                        fn (ExpenseAdvance $record):
                            string =>
                                $record
                                    ->approvedReconciledAmount()
                                > 0
                                    ? 'success'
                                    : 'gray'
                    ),

                Tables\Columns\TextColumn::make(
                    'actual_returned_amount'
                )
                    ->label('Devuelto')
                    ->state(
                        fn (ExpenseAdvance $record): float =>
                            self::actualReturnedAmount(
                                $record
                            )
                    )
                    ->money(
                        fn (ExpenseAdvance $record):
                            string =>
                                $record->currency_code
                                ?: 'MXN'
                    )
                    ->color(
                        fn (ExpenseAdvance $record): string =>
                            self::actualReturnedAmount(
                                $record
                            ) > 0.005
                                ? 'success'
                                : 'gray'
                    ),

                Tables\Columns\TextColumn::make(
                    'actual_reimbursed_amount'
                )
                    ->label('Reembolsado')
                    ->state(
                        fn (ExpenseAdvance $record): float =>
                            self::actualReimbursedAmount(
                                $record
                            )
                    )
                    ->money(
                        fn (ExpenseAdvance $record):
                            string =>
                                $record->currency_code
                                ?: 'MXN'
                    )
                    ->color(
                        fn (ExpenseAdvance $record): string =>
                            self::actualReimbursedAmount(
                                $record
                            ) > 0.005
                                ? 'success'
                                : 'gray'
                    ),

                Tables\Columns\TextColumn::make(
                    'reconciliation_remaining_amount'
                )
                    ->label('Pendiente')
                    ->state(
                        fn (ExpenseAdvance $record): float =>
                            self::reconciliationPendingAmount(
                                $record
                            )
                    )
                    ->money(
                        fn (ExpenseAdvance $record):
                            string =>
                                $record->currency_code
                                ?: 'MXN'
                    )
                    ->color(
                        fn (ExpenseAdvance $record): string =>
                            self::reconciliationPendingAmount(
                                $record
                            ) <= 0.005
                                ? 'success'
                                : 'warning'
                    ),

                Tables\Columns\TextColumn::make(
                    'due_days'
                )
                    ->label('Días')
                    ->suffix(' días'),

                Tables\Columns\TextColumn::make(
                    'due_date'
                )
                    ->label('Fecha límite')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make(
                    'due_status'
                )
                    ->label('Vencimiento')
                    ->state(
                        fn (ExpenseAdvance $record):
                            string =>
                                app(
                                    ExpenseAdvanceService::class
                                )->dueStatusLabel(
                                    $record
                                )
                    )
                    ->badge()
                    ->color(
                        fn (string $state):
                            string =>
                                str_starts_with(
                                    $state,
                                    'Vencido'
                                )
                                    ? 'danger'
                                    : (
                                        $state === 'Vence hoy'
                                            ? 'warning'
                                            : 'success'
                                    )
                    ),

                Tables\Columns\TextColumn::make(
                    'status'
                )
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state):
                            string =>
                                ExpenseAdvance::statusLabel(
                                    $state
                                )
                    )
                    ->color(
                        fn (?string $state):
                            string => match ($state) {
                                ExpenseAdvance::STATUS_DRAFT =>
                                    'gray',
                                ExpenseAdvance::STATUS_PENDING_APPROVAL =>
                                    'warning',
                                ExpenseAdvance::STATUS_APPROVED =>
                                    'info',
                                ExpenseAdvance::STATUS_PENDING_RECONCILIATION =>
                                    'warning',
                                ExpenseAdvance::STATUS_PENDING_RETURN =>
                                    'warning',
                                ExpenseAdvance::STATUS_PENDING_REIMBURSEMENT =>
                                    'warning',
                                ExpenseAdvance::STATUS_CLOSED =>
                                    'success',
                                ExpenseAdvance::STATUS_REJECTED,
                                ExpenseAdvance::STATUS_CANCELLED =>
                                    'danger',
                                default => 'gray',
                            }
                    ),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make(
                    'status'
                )
                    ->label('Estado')
                    ->options([
                        ExpenseAdvance::STATUS_DRAFT =>
                            'Borrador',
                        ExpenseAdvance::STATUS_PENDING_APPROVAL =>
                            'Pendiente de aprobación',
                        ExpenseAdvance::STATUS_PENDING_RECONCILIATION =>
                            'Pendiente de comprobar',
                        ExpenseAdvance::STATUS_PENDING_RETURN =>
                            'Pendiente de devolución',
                        ExpenseAdvance::STATUS_PENDING_REIMBURSEMENT =>
                            'Pendiente de reembolso',
                        ExpenseAdvance::STATUS_CLOSED =>
                            'Cerrado',
                        ExpenseAdvance::STATUS_REJECTED =>
                            'Rechazado',
                        ExpenseAdvance::STATUS_CANCELLED =>
                            'Cancelado',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(
                        fn (ExpenseAdvance $record):
                            bool =>
                                static::canEdit($record)
                    ),
            ])
            ->bulkActions([]);
    }

    public static function infolist(
        Infolist $infolist
    ): Infolist {
        return $infolist->schema([
            Infolists\Components\Section::make(
                'Anticipo'
            )
                ->columns(2)
                ->schema([
                    Infolists\Components\TextEntry::make(
                        'number'
                    )
                        ->label('Folio'),

                    Infolists\Components\TextEntry::make(
                        'status'
                    )
                        ->label('Estado')
                        ->badge()
                        ->formatStateUsing(
                            fn (?string $state):
                                string =>
                                    ExpenseAdvance::statusLabel(
                                        $state
                                    )
                        ),

                    Infolists\Components\TextEntry::make(
                        'employee.name'
                    )
                        ->label('Empleado que recibe'),

                    Infolists\Components\TextEntry::make(
                        'authorizedByEmployee.name'
                    )
                        ->label('Empleado que autoriza')
                        ->placeholder('Sin asignar'),

                    Infolists\Components\TextEntry::make(
                        'pettyCashFund.name'
                    )
                        ->label('Caja chica'),

                    Infolists\Components\TextEntry::make(
                        'amount'
                    )
                        ->label('Importe')
                        ->money(
                            fn (ExpenseAdvance $record):
                                string =>
                                    $record->currency_code
                                    ?: 'MXN'
                        ),

                    Infolists\Components\TextEntry::make(
                        'request_date'
                    )
                        ->label('Fecha solicitud')
                        ->date('d/m/Y'),

                    Infolists\Components\TextEntry::make(
                        'due_days'
                    )
                        ->label('Días para comprobar')
                        ->suffix(' días'),

                    Infolists\Components\TextEntry::make(
                        'due_date'
                    )
                        ->label('Fecha límite')
                        ->date('d/m/Y'),

                    Infolists\Components\TextEntry::make(
                        'purpose'
                    )
                        ->label('Motivo / propósito')
                        ->columnSpanFull(),

                    Infolists\Components\TextEntry::make(
                        'notes'
                    )
                        ->label('Notas')
                        ->placeholder('—')
                        ->columnSpanFull(),
                ]),

            Infolists\Components\Section::make(
                'Resumen de comprobación'
            )
                ->description(
                    'Seguimiento del anticipo contra '
                    . 'las comprobaciones relacionadas.'
                )
                ->columns(6)
                ->schema([
                    Infolists\Components\TextEntry::make(
                        'reconciliation_advance_amount'
                    )
                        ->label('Anticipo')
                        ->state(
                            fn (ExpenseAdvance $record): float =>
                                round((float) $record->amount, 2)
                        )
                        ->money(
                            fn (ExpenseAdvance $record): string =>
                                $record->currency_code ?: 'MXN'
                        ),

                    Infolists\Components\TextEntry::make(
                        'reconciliation_approved_amount'
                    )
                        ->label('Comprobado aprobado')
                        ->state(
                            fn (ExpenseAdvance $record): float =>
                                round(
                                    $record->approvedReconciledAmount(),
                                    2
                                )
                        )
                        ->money(
                            fn (ExpenseAdvance $record): string =>
                                $record->currency_code ?: 'MXN'
                        ),

                    Infolists\Components\TextEntry::make(
                        'reconciliation_pending_amount'
                    )
                        ->label('En aprobación')
                        ->state(
                            fn (ExpenseAdvance $record): float =>
                                round(
                                    $record->pendingReconciledAmount(),
                                    2
                                )
                        )
                        ->money(
                            fn (ExpenseAdvance $record): string =>
                                $record->currency_code ?: 'MXN'
                        ),

                    Infolists\Components\TextEntry::make(
                        'reconciliation_draft_amount'
                    )
                        ->label('Borrador')
                        ->state(
                            fn (ExpenseAdvance $record): float =>
                                round(
                                    $record->draftReconciledAmount(),
                                    2
                                )
                        )
                        ->money(
                            fn (ExpenseAdvance $record): string =>
                                $record->currency_code ?: 'MXN'
                        ),

                    Infolists\Components\TextEntry::make(
                        'reconciliation_remaining_amount'
                    )
                        ->label('Pendiente')
                        ->state(
                            fn (ExpenseAdvance $record): float =>
                                self::reconciliationPendingAmount(
                                    $record
                                )
                        )
                        ->money(
                            fn (ExpenseAdvance $record): string =>
                                $record->currency_code ?: 'MXN'
                        )
                        ->color(
                            fn (ExpenseAdvance $record): string =>
                                self::reconciliationPendingAmount(
                                    $record
                                ) <= 0.005
                                    ? 'success'
                                    : 'warning'
                        ),

                    Infolists\Components\TextEntry::make(
                        'reconciliation_actual_returned_display'
                    )
                        ->label('Devuelto')
                        ->state(
                            fn (ExpenseAdvance $record): float =>
                                self::actualReturnedAmount(
                                    $record
                                )
                        )
                        ->money(
                            fn (ExpenseAdvance $record): string =>
                                $record->currency_code ?: 'MXN'
                        )
                        ->color(
                            fn (ExpenseAdvance $record): string =>
                                self::actualReturnedAmount(
                                    $record
                                ) > 0.005
                                    ? 'success'
                                    : 'gray'
                        ),

                    Infolists\Components\TextEntry::make(
                        'reconciliation_actual_reimbursed_display'
                    )
                        ->label('Reembolsado')
                        ->state(
                            fn (ExpenseAdvance $record): float =>
                                self::actualReimbursedAmount(
                                    $record
                                )
                        )
                        ->money(
                            fn (ExpenseAdvance $record): string =>
                                $record->currency_code ?: 'MXN'
                        )
                        ->color(
                            fn (ExpenseAdvance $record): string =>
                                self::actualReimbursedAmount(
                                    $record
                                ) > 0.005
                                    ? 'success'
                                    : 'gray'
                        ),

                    Infolists\Components\TextEntry::make(
                        'reconciliation_return_due_display'
                    )
                        ->label('Devolución pendiente')
                        ->state(
                            fn (ExpenseAdvance $record): float =>
                                round(
                                    (float) $record
                                        ->return_due_amount,
                                    2
                                )
                        )
                        ->money(
                            fn (ExpenseAdvance $record): string =>
                                $record->currency_code ?: 'MXN'
                        )
                        ->color(
                            fn (ExpenseAdvance $record): string =>
                                (float) $record
                                    ->return_due_amount > 0
                                    ? 'warning'
                                    : 'gray'
                        ),

                    Infolists\Components\TextEntry::make(
                        'reconciliation_reimbursement_due_display'
                    )
                        ->label('Reembolso pendiente')
                        ->state(
                            fn (ExpenseAdvance $record): float =>
                                round(
                                    (float) $record
                                        ->reimbursement_due_amount,
                                    2
                                )
                        )
                        ->money(
                            fn (ExpenseAdvance $record): string =>
                                $record->currency_code ?: 'MXN'
                        )
                        ->color(
                            fn (ExpenseAdvance $record): string =>
                                (float) $record
                                    ->reimbursement_due_amount > 0
                                    ? 'danger'
                                    : 'gray'
                        ),

                    Infolists\Components\TextEntry::make(
                        'reconciliation_progress'
                    )
                        ->label('Avance')
                        ->state(
                            function (
                                ExpenseAdvance $record
                            ): string {
                                if (
                                    (string) $record->status
                                    === ExpenseAdvance::STATUS_CLOSED
                                ) {
                                    return '100.0%';
                                }

                                $amount = (float) $record->amount;

                                if ($amount <= 0) {
                                    return '0.0%';
                                }

                                $approved =
                                    $record->approvedReconciledAmount();

                                $percentage =
                                    ($approved / $amount) * 100;

                                return number_format(
                                    max(
                                        0,
                                        min(100, $percentage)
                                    ),
                                    1
                                ) . '%';
                            }
                        )
                        ->badge()
                        ->color(
                            function (
                                ExpenseAdvance $record
                            ): string {
                                if (
                                    (string) $record->status
                                    === ExpenseAdvance::STATUS_CLOSED
                                ) {
                                    return 'success';
                                }

                                $amount = (float) $record->amount;

                                if ($amount <= 0) {
                                    return 'gray';
                                }

                                $approved =
                                    $record->approvedReconciledAmount();

                                if (
                                    $approved
                                    >= $amount - 0.005
                                ) {
                                    return 'success';
                                }

                                if ($approved > 0) {
                                    return 'warning';
                                }

                                return 'gray';
                            }
                        ),
                ]),

            Infolists\Components\Section::make(
                'Movimientos del anticipo'
            )
                ->description(
                    'Movimientos financieros reales '
                    . 'generados por el anticipo.'
                )
                ->schema([
                    Infolists\Components\ViewEntry::make(
                        'advance_financial_movements'
                    )
                        ->label('')
                        ->state(
                            fn (ExpenseAdvance $record): array =>
                                self::advanceMovementRows(
                                    $record
                                )
                        )
                        ->view(
                            'filament.infolists.expense-advance-movements'
                        )
                        ->columnSpanFull(),
                ]),

            Infolists\Components\Section::make(
                'Comprobaciones relacionadas'
            )
                ->description(
                    'Puedes realizar varias comprobaciones '
                    . 'parciales para un mismo anticipo.'
                )
                ->schema([
                    Infolists\Components\RepeatableEntry::make(
                        'expenseReports'
                    )
                        ->label('')
                        ->schema([
                            Infolists\Components\TextEntry::make(
                                'number'
                            )
                                ->label('Folio')
                                ->placeholder('Sin folio')
                                ->url(
                                    fn (
                                        ExpenseReport $record
                                    ): string =>
                                        ExpenseReportResource::getUrl(
                                            'view',
                                            [
                                                'record' =>
                                                    $record,
                                            ],
                                            panel: 'admin',
                                            tenant:
                                                Filament::getTenant()
                                        )
                                ),

                            Infolists\Components\TextEntry::make(
                                'status'
                            )
                                ->label('Estado')
                                ->badge()
                                ->formatStateUsing(
                                    fn (
                                        ?string $state
                                    ): string =>
                                        match ($state) {
                                            'draft' =>
                                                'Borrador',
                                            'pending_approval' =>
                                                'Pendiente de aprobación',
                                            'approved' =>
                                                'Aprobada',
                                            'rejected' =>
                                                'Rechazada',
                                            'paid' =>
                                                'Pagada',
                                            'closed' =>
                                                'Cerrada',
                                            'cancelled' =>
                                                'Cancelada',
                                            default =>
                                                $state ?: '—',
                                        }
                                )
                                ->color(
                                    fn (
                                        ?string $state
                                    ): string =>
                                        match ($state) {
                                            'approved',
                                            'paid',
                                            'closed' =>
                                                'success',
                                            'pending_approval' =>
                                                'warning',
                                            'rejected',
                                            'cancelled' =>
                                                'danger',
                                            default =>
                                                'gray',
                                        }
                                ),

                            Infolists\Components\TextEntry::make(
                                'report_date'
                            )
                                ->label('Fecha')
                                ->date('d/m/Y'),

                            Infolists\Components\TextEntry::make(
                                'total_amount'
                            )
                                ->label('Importe')
                                ->money(
                                    fn (
                                        ExpenseReport $record
                                    ): string =>
                                        $record->currency_code ?: 'MXN'
                                ),
                        ])
                        ->columns(4),
                ])
                ->visible(
                    fn (ExpenseAdvance $record): bool =>
                        $record->expenseReports()->exists()
                ),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' =>
                Pages\ListExpenseAdvances::route('/'),
            'create' =>
                Pages\CreateExpenseAdvance::route(
                    '/create'
                ),
            'view' =>
                Pages\ViewExpenseAdvance::route(
                    '/{record}'
                ),
            'edit' =>
                Pages\EditExpenseAdvance::route(
                    '/{record}/edit'
                ),
        ];
    }

    private static function actualReturnedAmount(
        ExpenseAdvance $record
    ): float {
        if (! $record->return_treasury_movement_id) {
            return 0.0;
        }

        return round(
            (float) \App\Models\TreasuryMovement::query()
                ->whereKey(
                    $record->return_treasury_movement_id
                )
                ->where(
                    'source_type',
                    'expense_advance_return'
                )
                ->where(
                    'source_id',
                    $record->id
                )
                ->where(
                    'status',
                    'posted'
                )
                ->value('amount'),
            2
        );
    }

    private static function actualReimbursedAmount(
        ExpenseAdvance $record
    ): float {
        if (
            $record
                ->reimbursement_treasury_movement_id
        ) {
            return round(
                (float)
                    \App\Models\TreasuryMovement::query()
                        ->whereKey(
                            $record
                                ->reimbursement_treasury_movement_id
                        )
                        ->where(
                            'status',
                            'posted'
                        )
                        ->value('amount'),
                2
            );
        }

        return 0.0;
    }

    private static function advanceMovementRows(
        ExpenseAdvance $record
    ): array {
        $rows = [];

        if ($record->treasury_movement_id) {
            $movement =
                \App\Models\TreasuryMovement::query()
                    ->find(
                        $record->treasury_movement_id
                    );

            if ($movement) {
                $date =
                    $movement->posted_at
                        ?->format('d/m/Y H:i')
                    ?? $movement->movement_date
                        ?->format('d/m/Y')
                    ?? '—';

                $rows[] = [
                    'concept' =>
                        'Entrega de anticipo',

                    'date' =>
                        $date,

                    'amount' =>
                        '$'
                        . number_format(
                            (float) $movement->amount,
                            2
                        )
                        . ' '
                        . (
                            $movement->currency_code
                            ?: $record->currency_code
                            ?: 'MXN'
                        ),

                    'direction' =>
                        'Salida',

                    'movement' =>
                        '#'
                        . $movement->id,

                    'status' =>
                        (string) $movement->status
                        === 'posted'
                            ? 'Aplicado'
                            : ucfirst(
                                (string) $movement->status
                            ),
                ];
            }
        }

        if ($record->return_treasury_movement_id) {
            $movement =
                \App\Models\TreasuryMovement::query()
                    ->whereKey(
                        $record
                            ->return_treasury_movement_id
                    )
                    ->where(
                        'source_type',
                        'expense_advance_return'
                    )
                    ->where(
                        'source_id',
                        $record->id
                    )
                    ->first();

            if ($movement) {
                $date =
                    $movement->posted_at
                        ?->format('d/m/Y H:i')
                    ?? $movement->movement_date
                        ?->format('d/m/Y')
                    ?? '—';

                $rows[] = [
                    'concept' =>
                        'Devolución',

                    'date' =>
                        $date,

                    'amount' =>
                        '$'
                        . number_format(
                            (float) $movement->amount,
                            2
                        )
                        . ' '
                        . (
                            $movement->currency_code
                            ?: $record->currency_code
                            ?: 'MXN'
                        ),

                    'direction' =>
                        'Entrada',

                    'movement' =>
                        '#'
                        . $movement->id,

                    'status' =>
                        (string) $movement->status
                        === 'posted'
                            ? 'Aplicado'
                            : ucfirst(
                                (string) $movement->status
                            ),
                ];
            }
        }

        $reimbursements = collect();

        if (
            $record
                ->reimbursement_treasury_movement_id
        ) {
            $movement =
                \App\Models\TreasuryMovement::query()
                    ->whereKey(
                        $record
                            ->reimbursement_treasury_movement_id
                    )
                    ->first();

            if ($movement) {
                $reimbursements->push(
                    $movement
                );
            }
        }

        foreach ($reimbursements as $movement) {
            $date =
                $movement->posted_at
                    ?->format('d/m/Y H:i')
                ?? $movement->movement_date
                    ?->format('d/m/Y')
                ?? '—';

            $rows[] = [
                'concept' =>
                    'Reembolso',

                'date' =>
                    $date,

                'amount' =>
                    '$'
                    . number_format(
                        (float) $movement->amount,
                        2
                    )
                    . ' '
                    . (
                        $movement->currency_code
                        ?: $record->currency_code
                        ?: 'MXN'
                    ),

                'direction' =>
                    'Salida',

                'movement' =>
                    '#'
                    . $movement->id,

                'status' =>
                    (string) $movement->status
                    === 'posted'
                        ? 'Aplicado'
                        : ucfirst(
                            (string) $movement->status
                        ),
            ];
        }

        return $rows;
    }

    private static function reconciliationPendingAmount(
        ExpenseAdvance $record
    ): float {
        return match ((string) $record->status) {
            ExpenseAdvance::STATUS_CLOSED =>
                0.0,

            ExpenseAdvance::STATUS_PENDING_RETURN =>
                max(
                    0,
                    round(
                        (float) $record->return_due_amount,
                        2
                    )
                ),

            ExpenseAdvance::STATUS_PENDING_REIMBURSEMENT =>
                max(
                    0,
                    round(
                        (float) $record
                            ->reimbursement_due_amount,
                        2
                    )
                ),

            default =>
                max(
                    0,
                    round(
                        (float) $record->amount
                        - $record
                            ->approvedReconciledAmount(),
                        2
                    )
                ),
        };
    }

}
