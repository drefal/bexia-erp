<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccountReceivableResource\Pages;
use App\Models\AccountReceivable;
use Filament\Facades\Filament;
use Filament\Forms\Form;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AccountReceivableResource extends Resource
{
    protected static ?string $model = AccountReceivable::class;

    protected static ?string $tenantOwnershipRelationshipName = 'company';

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationGroup = 'Cuentas por cobrar';

    protected static ?string $navigationLabel = 'Cuentas por cobrar';

    protected static ?string $modelLabel = 'cuenta por cobrar';

    protected static ?string $pluralModelLabel = 'cuentas por cobrar';

    protected static ?int $navigationSort = 10;

    public static function statusLabel(?string $state): string
    {
        return match ($state) {
            'draft' => 'Borrador',
            'open' => 'Pendiente de cobro',
            'partial' => 'Cobro parcial',
            'paid' => 'Cobrada',
            'cancelled' => 'Cancelada',
            default => filled($state) ? (string) $state : 'Sin estado',
        };
    }

    public static function statusColor(?string $state): string
    {
        return match ($state) {
            'draft' => 'gray',
            'open' => 'warning',
            'partial' => 'info',
            'paid' => 'success',
            'cancelled' => 'danger',
            default => 'gray',
        };
    }

    public static function accountingStatusLabel(?string $state): string
    {
        return match ((string) $state) {
            'posted' => 'Contabilizado',
            'not_posted' => 'Pendiente',
            'posting_error' => 'Error al contabilizar',
            'error' => 'Error',
            'cancelled' => 'Cancelado',
            default => filled($state) ? ucfirst(str_replace('_', ' ', (string) $state)) : 'Pendiente',
        };
    }


    public static function accountingStatusColor(?string $state): string
    {
        return match ((string) $state) {
            'posted' => 'success',
            'not_posted' => 'warning',
            'posting_error', 'error' => 'danger',
            'cancelled' => 'gray',
            default => 'gray',
        };
    }



    /*
     * BEXIA_ARCV_RESOURCE_RESPONSIVE_V5_79_45C
     * Visual-only responsive marker.
     */

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->persistFiltersInSession()
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->extraHeaderAttributes(['class' => 'bexia-arcv-col-folio bexia-arcv-col-primary'])
                    ->extraCellAttributes(['class' => 'bexia-arcv-col-folio bexia-arcv-col-primary'])
                    ->label('Folio')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer_name')
                    ->extraHeaderAttributes(['class' => 'bexia-arcv-col-cust bexia-arcv-col-wide'])
                    ->extraCellAttributes(['class' => 'bexia-arcv-col-cust bexia-arcv-col-wide'])
                    ->label('Cliente')
                    ->searchable()
                    ->limit(42),

                Tables\Columns\TextColumn::make('customer_reference')
                    ->extraHeaderAttributes(['class' => 'bexia-arcv-col-custref bexia-arcv-col-wrap'])
                    ->extraCellAttributes(['class' => 'bexia-arcv-col-custref bexia-arcv-col-wrap'])
                    ->label('Referencia')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->extraHeaderAttributes(['class' => 'bexia-arcv-col-state'])
                    ->extraCellAttributes(['class' => 'bexia-arcv-col-state'])
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => static::statusLabel($state))
                    ->color(fn (?string $state): string => static::statusColor($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('issue_date')
                    ->extraHeaderAttributes(['class' => 'bexia-arcv-col-issue'])
                    ->extraCellAttributes(['class' => 'bexia-arcv-col-issue'])
                    ->label('Fecha')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('due_date')
                    ->extraHeaderAttributes(['class' => 'bexia-arcv-col-due'])
                    ->extraCellAttributes(['class' => 'bexia-arcv-col-due'])
                    ->label('Vence')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total')
                    ->extraHeaderAttributes(['class' => 'bexia-arcv-col-gross bexia-arcv-col-money'])
                    ->extraCellAttributes(['class' => 'bexia-arcv-col-gross bexia-arcv-col-money'])
                    ->label('Total')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state, AccountReceivable $record): string => '$' . number_format((float) $state, 2) . ' ' . $record->currency)
                    ->sortable(),

                Tables\Columns\TextColumn::make('collected_total')
                    ->extraHeaderAttributes(['class' => 'bexia-arcv-col-coll bexia-arcv-col-money'])
                    ->extraCellAttributes(['class' => 'bexia-arcv-col-coll bexia-arcv-col-money'])
                    ->label('Cobrado')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state, AccountReceivable $record): string => '$' . number_format((float) $state, 2) . ' ' . $record->currency)
                    ->sortable(),

                Tables\Columns\TextColumn::make('balance_total')
                    ->extraHeaderAttributes(['class' => 'bexia-arcv-col-bal bexia-arcv-col-money'])
                    ->extraCellAttributes(['class' => 'bexia-arcv-col-bal bexia-arcv-col-money'])
                    ->label('Saldo')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state, AccountReceivable $record): string => '$' . number_format((float) $state, 2) . ' ' . $record->currency)
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'open' => 'Pendiente de cobro',
                        'partial' => 'Cobro parcial',
                        'paid' => 'Cobrada',
                        'cancelled' => 'Cancelada',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Ver'),
            ])
            ->bulkActions([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Información general')
                    ->extraAttributes(['class' => 'bexia-arcv-section bexia-arcv-section-main'])
                    ->columns(3)
                    ->schema([
                        TextEntry::make('number')->label('Folio')
                            ->extraAttributes(['class' => 'bexia-arcv-item bexia-arcv-item-folio']),

                        TextEntry::make('status')
                            ->extraAttributes(['class' => 'bexia-arcv-item bexia-arcv-item-state'])
                            ->label('Estado')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => static::statusLabel($state))
                            ->color(fn (?string $state): string => static::statusColor($state)),

                        TextEntry::make('customer_name')->label('Cliente')
                            ->extraAttributes(['class' => 'bexia-arcv-item bexia-arcv-item-cust']),
                        TextEntry::make('customer_reference')->label('Referencia cliente')->placeholder('Sin referencia')
                            ->extraAttributes(['class' => 'bexia-arcv-item bexia-arcv-item-custref']),
                        TextEntry::make('sale_order_id')->label('Venta')->placeholder('Sin venta')
                            ->extraAttributes(['class' => 'bexia-arcv-item bexia-arcv-item-sale']),
                        TextEntry::make('invoice_id')->label('Factura')->placeholder('Sin factura')
                            ->extraAttributes(['class' => 'bexia-arcv-item bexia-arcv-item-inv']),
                        TextEntry::make('issue_date')->label('Fecha')->date()
                            ->extraAttributes(['class' => 'bexia-arcv-item bexia-arcv-item-issue']),
                        TextEntry::make('due_date')->label('Vencimiento')->date()
                            ->extraAttributes(['class' => 'bexia-arcv-item bexia-arcv-item-due']),
                        TextEntry::make('currency')->label('Moneda')
                            ->extraAttributes(['class' => 'bexia-arcv-item bexia-arcv-item-curr']),
                    ]),

                Section::make('Importes')
                    ->extraAttributes(['class' => 'bexia-arcv-section bexia-arcv-section-nums'])
                    ->columns(4)
                    ->schema([
                        TextEntry::make('subtotal')
                            ->extraAttributes(['class' => 'bexia-arcv-item bexia-arcv-item-sub'])
                            ->label('Subtotal')
                            ->formatStateUsing(fn ($state, AccountReceivable $record): string => '$' . number_format((float) $state, 2) . ' ' . $record->currency),
                        TextEntry::make('tax_total')
                            ->extraAttributes(['class' => 'bexia-arcv-item bexia-arcv-item-tax'])
                            ->label('Impuestos')
                            ->formatStateUsing(fn ($state, AccountReceivable $record): string => '$' . number_format((float) $state, 2) . ' ' . $record->currency),
                        TextEntry::make('total')
                            ->extraAttributes(['class' => 'bexia-arcv-item bexia-arcv-item-gross'])
                            ->label('Total')
                            ->formatStateUsing(fn ($state, AccountReceivable $record): string => '$' . number_format((float) $state, 2) . ' ' . $record->currency),
                        TextEntry::make('collected_total')
                            ->extraAttributes(['class' => 'bexia-arcv-item bexia-arcv-item-coll'])
                            ->label('Cobrado')
                            ->formatStateUsing(fn ($state, AccountReceivable $record): string => '$' . number_format((float) $state, 2) . ' ' . $record->currency),
                        TextEntry::make('balance_total')
                            ->extraAttributes(['class' => 'bexia-arcv-item bexia-arcv-item-bal'])
                            ->label('Saldo')
                            ->formatStateUsing(fn ($state, AccountReceivable $record): string => '$' . number_format((float) $state, 2) . ' ' . $record->currency),
                    ]),

                Section::make('Conexión contable')
                    ->extraAttributes([
                        'class' =>
                            'bexia-arcv-section bexia-arcv-section-acctg',
                    ])
                    ->description(
                        'El documento CxC y sus cobros se contabilizan por separado. '
                        . 'La primera fila corresponde al documento y la segunda '
                        . 'muestra la contabilización real de los cobros.'
                    )
                    ->columns(3)
                    ->schema([
                        /*
                         * DOCUMENTO CxC
                         */
                        TextEntry::make(
                            'accounting_status'
                        )
                            ->extraAttributes([
                                'class' =>
                                    'bexia-arcv-item bexia-arcv-item-acctgstate',
                            ])
                            ->label(
                                'Documento CxC · Estado'
                            )
                            ->badge()
                            ->formatStateUsing(
                                fn (?string $state): string =>
                                    static::
                                        accountingStatusLabel(
                                            $state
                                        )
                            )
                            ->color(
                                fn (?string $state): string =>
                                    static::
                                        accountingStatusColor(
                                            $state
                                        )
                            ),

                        TextEntry::make(
                            'accounting_entry_id'
                        )
                            ->label(
                                'Documento CxC · Póliza'
                            )
                            ->placeholder(
                                'Sin póliza del documento'
                            )
                            ->extraAttributes([
                                'class' =>
                                    'bexia-arcv-item bexia-arcv-item-pol',
                            ]),

                        TextEntry::make(
                            'accounting_posted_at'
                        )
                            ->label(
                                'Documento CxC · Contabilizado'
                            )
                            ->dateTime(
                                'd/m/Y H:i'
                            )
                            ->placeholder(
                                'Pendiente'
                            )
                            ->extraAttributes([
                                'class' =>
                                    'bexia-arcv-item bexia-arcv-item-acctgdt',
                            ]),

                        /*
                         * COBROS
                         */
                        TextEntry::make(
                            'payment_accounting_status'
                        )
                            ->label(
                                'Cobros · Estado'
                            )
                            ->state(
                                fn (
                                    AccountReceivable $record
                                ): string =>
                                    static::
                                        paymentAccountingSummary(
                                            $record
                                        )[
                                            'status_label'
                                        ]
                            )
                            ->badge()
                            ->color(
                                fn (
                                    AccountReceivable $record
                                ): string =>
                                    static::
                                        paymentAccountingSummary(
                                            $record
                                        )[
                                            'status_color'
                                        ]
                            ),

                        TextEntry::make(
                            'payment_accounting_entries'
                        )
                            ->label(
                                'Cobros · Póliza(s)'
                            )
                            ->state(
                                fn (
                                    AccountReceivable $record
                                ): string =>
                                    static::
                                        paymentAccountingSummary(
                                            $record
                                        )[
                                            'entries'
                                        ]
                            )
                            ->placeholder(
                                'Sin póliza de cobro'
                            ),

                        TextEntry::make(
                            'payment_accounting_amount'
                        )
                            ->label(
                                'Cobros · Importe contabilizado'
                            )
                            ->state(
                                fn (
                                    AccountReceivable $record
                                ): string =>
                                    '$'
                                    . number_format(
                                        (float)
                                            static::
                                                paymentAccountingSummary(
                                                    $record
                                                )[
                                                    'accounted_amount'
                                                ],
                                        2
                                    )
                                    . ' '
                                    . (
                                        $record->currency
                                        ?: 'MXN'
                                    )
                            ),

                        TextEntry::make(
                            'payment_accounting_accounts'
                        )
                            ->label(
                                'Cobros · Caja / Banco'
                            )
                            ->state(
                                fn (
                                    AccountReceivable $record
                                ): string =>
                                    static::
                                        paymentAccountingSummary(
                                            $record
                                        )[
                                            'accounts'
                                        ]
                            )
                            ->placeholder(
                                'Sin cuenta'
                            ),

                        TextEntry::make(
                            'payment_accounting_count'
                        )
                            ->label(
                                'Cobros contabilizados'
                            )
                            ->state(
                                fn (
                                    AccountReceivable $record
                                ): string =>
                                    static::
                                        paymentAccountingSummary(
                                            $record
                                        )[
                                            'accounted_count'
                                        ]
                                    . ' de '
                                    . static::
                                        paymentAccountingSummary(
                                            $record
                                        )[
                                            'posted_count'
                                        ]
                            ),

                        TextEntry::make(
                            'payment_accounting_posted_at'
                        )
                            ->label(
                                'Última contabilización de cobro'
                            )
                            ->state(
                                function (
                                    AccountReceivable $record
                                ): string {
                                    $value =
                                        static::
                                            paymentAccountingSummary(
                                                $record
                                            )[
                                                'last_posted_at'
                                            ];

                                    if (
                                        blank(
                                            $value
                                        )
                                    ) {
                                        return 'Pendiente';
                                    }

                                    return
                                        \Illuminate\Support\Carbon::
                                            parse(
                                                $value
                                            )
                                            ->format(
                                                'd/m/Y H:i'
                                            );
                                }
                            ),

                        TextEntry::make(
                            'accounting_error_message'
                        )
                            ->label(
                                'Error contable del documento'
                            )
                            ->columnSpanFull()
                            ->placeholder(
                                'Sin error'
                            )
                            ->extraAttributes([
                                'class' =>
                                    'bexia-arcv-item bexia-arcv-item-acctgerr',
                            ]),
                    ]),
            ]);
    }

    /*
     * BEXIA_CXC_PAYMENT_ACCOUNTING_SUMMARY_V5_83_4C5G14C1
     *
     * SOLO LECTURA.
     *
     * account_receivables.accounting_* representa
     * la contabilidad DEL DOCUMENTO CxC.
     *
     * account_receivable_payments.accounting_entry_id
     * representa la contabilidad DE CADA COBRO.
     */
    protected static function paymentAccountingSummary(
        AccountReceivable $record
    ): array {
        static $cache = [];

        $cacheKey =
            (int) $record->getKey()
            . '|'
            . (string) (
                $record->updated_at
                ?? ''
            );

        if (
            array_key_exists(
                $cacheKey,
                $cache
            )
        ) {
            return $cache[
                $cacheKey
            ];
        }

        $rows =
            DB::table(
                'account_receivable_payments as p'
            )
                ->leftJoin(
                    'accounting_entries as ae',
                    'ae.id',
                    '=',
                    'p.accounting_entry_id'
                )
                ->leftJoin(
                    'treasury_accounts as ta',
                    'ta.id',
                    '=',
                    'p.treasury_account_id'
                )
                ->where(
                    'p.company_id',
                    (int) $record->company_id
                )
                ->where(
                    'p.account_receivable_id',
                    (int) $record->getKey()
                )
                ->where(
                    'p.status',
                    'posted'
                )
                ->orderBy(
                    'p.id'
                )
                ->get([
                    'p.id as payment_id',
                    'p.amount as payment_amount',
                    'p.posted_at as payment_posted_at',
                    'p.accounting_entry_id',
                    'p.treasury_account_id',
                    'ae.entry_number',
                    'ae.status as accounting_entry_status',
                    'ae.posted_at as accounting_entry_posted_at',
                    'ta.name as treasury_account_name',
                ]);

        $postedCount =
            $rows->count();

        $accounted =
            $rows
                ->filter(
                    fn ($row): bool =>
                        ! empty(
                            $row->accounting_entry_id
                        )
                        && (
                            (string) (
                                $row->
                                    accounting_entry_status
                                ?? ''
                            )
                            === 'posted'
                        )
                )
                ->values();

        $accountedCount =
            $accounted->count();

        if ($postedCount === 0) {
            $statusLabel =
                'Sin cobros';

            $statusColor =
                'gray';
        } elseif (
            $accountedCount
            === $postedCount
        ) {
            $statusLabel =
                'Contabilizado';

            $statusColor =
                'success';
        } elseif (
            $accountedCount > 0
        ) {
            $statusLabel =
                'Parcialmente contabilizado';

            $statusColor =
                'warning';
        } else {
            $statusLabel =
                'Pendiente';

            $statusColor =
                'warning';
        }

        $accountedAmount =
            round(
                (float) $accounted->sum(
                    fn ($row): float =>
                        (float) (
                            $row->
                                payment_amount
                            ?? 0
                        )
                ),
                2
            );

        $entries =
            $accounted
                ->map(
                    function ($row): string {
                        $number =
                            trim(
                                (string) (
                                    $row->
                                        entry_number
                                    ?? ''
                                )
                            );

                        if ($number !== '') {
                            return $number;
                        }

                        $id =
                            (int) (
                                $row->
                                    accounting_entry_id
                                ?? 0
                            );

                        return
                            $id > 0
                                ? 'Póliza #'
                                    . $id
                                : '';
                    }
                )
                ->filter()
                ->unique()
                ->values()
                ->implode(', ');

        $accounts =
            $accounted
                ->map(
                    fn ($row): string =>
                        trim(
                            (string) (
                                $row->
                                    treasury_account_name
                                ?? ''
                            )
                        )
                )
                ->filter()
                ->unique()
                ->values()
                ->implode(', ');

        $lastPostedAt =
            $accounted
                ->map(
                    fn ($row) =>
                        $row->
                            accounting_entry_posted_at
                        ?: $row->
                            payment_posted_at
                )
                ->filter()
                ->sortDesc()
                ->first();

        $result = [
            'status_label' =>
                $statusLabel,

            'status_color' =>
                $statusColor,

            'posted_count' =>
                $postedCount,

            'accounted_count' =>
                $accountedCount,

            'accounted_amount' =>
                $accountedAmount,

            'entries' =>
                $entries,

            'accounts' =>
                $accounts,

            'last_posted_at' =>
                $lastPostedAt,
        ];

        $cache[$cacheKey] =
            $result;

        return $result;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $tenant = Filament::getTenant();

        if ($tenant && method_exists($tenant, 'getKey')) {
            $query->where('company_id', $tenant->getKey());
        }

        return $query;
    }

    protected static function userCanPermission(string $permission): bool
    {
        $user = auth()->user();

        return $user && method_exists($user, 'can') && $user->can($permission);
    }

    public static function canViewAny(): bool
    {
        return static::userCanPermission('account_receivables.view');
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return static::userCanPermission('account_receivables.create');
    }

    /*
     * BEXIA_V582_B28I2A_LEGACY_AR_READONLY
     *
     * account_receivables no posee columnas is_legacy/locked.
     * Para CxC historicas Odoo usamos identidad compuesta:
     *
     * source_type = odoo_account_move
     * metadata.route = AR
     * metadata.migration_batch_id = ODOO_GL7_*
     */
    public static function legacyValuesAreReadOnly(
        mixed $sourceType,
        mixed $metadata,
    ): bool {
        if (
            strtolower(trim((string) $sourceType))
            !== 'odoo_account_move'
        ) {
            return false;
        }

        if (is_array($metadata)) {
            $values = $metadata;
        } elseif (is_string($metadata)) {
            $decoded = json_decode($metadata, true);
            $values = is_array($decoded) ? $decoded : [];
        } else {
            $values = [];
        }

        $route = strtoupper(
            trim((string) ($values['route'] ?? ''))
        );

        $batch = trim(
            (string) ($values['migration_batch_id'] ?? '')
        );

        return $route === 'AR'
            && str_starts_with($batch, 'ODOO_GL7_');
    }

    public static function isLegacyReadOnly(
        AccountReceivable $record
    ): bool {
        return static::legacyValuesAreReadOnly(
            $record->source_type ?? null,
            $record->metadata ?? null,
        );
    }

    public static function canEdit(Model $record): bool
    {
        if (
            $record instanceof AccountReceivable
            && static::isLegacyReadOnly($record)
        ) {
            return false;
        }

        return static::userCanPermission('account_receivables.update');
    }

    public static function canDelete(Model $record): bool
    {
        if (
            $record instanceof AccountReceivable
            && static::isLegacyReadOnly($record)
        ) {
            return false;
        }

        return static::userCanPermission('account_receivables.cancel');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccountReceivables::route('/'),
            'view' => Pages\ViewAccountReceivable::route('/{record}'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\Navigation\BexiaMenuRuntime::shouldRegister(
            'resources.accountreceivableresource',
            fn (): bool => method_exists(static::class, 'canViewAny')
                ? static::canViewAny()
                : (method_exists(static::class, 'canAccess') ? static::canAccess() : true),
        );
    }

}
