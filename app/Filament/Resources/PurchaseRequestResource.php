<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseRequestResource\Pages;
use App\Models\PurchaseRequest;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;

class PurchaseRequestResource extends Resource
{
    protected static ?string $model = PurchaseRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Compras';

    protected static ?string $navigationLabel = 'Solicitudes de compra';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'solicitud de compra';

    protected static ?string $pluralModelLabel = 'solicitudes de compra';
protected static bool $isScopedToTenant = false;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->withCount('lines');

        $companyId = static::currentCompanyId();

        if ($companyId && Schema::hasColumn('purchase_requests', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        return $query;
    }
    protected static function purchaseTaxLabel($rate): string
    {
        $key = static::normalizeTaxRateOptionKey($rate);
        $options = static::purchaseTaxOptions();

        if (isset($options[$key])) {
            return $options[$key];
        }

        $numeric = is_numeric($rate) ? (float) $rate : 0.0;

        if ($numeric == 0.0) {
            return 'Exento (0%)';
        }

        $short = rtrim(rtrim(number_format($numeric, 4, '.', ''), '0'), '.');

        return 'IVA ' . $short . '% (' . number_format($numeric, 2) . '%)';
    }


    protected static function purchaseTaxOptions(): array
    {
        $fallback = [
            static::normalizeTaxRateOptionKey(0) => 'Exento (0%)',
            static::normalizeTaxRateOptionKey(8) => 'IVA 8%',
            static::normalizeTaxRateOptionKey(16) => 'IVA 16%',
        ];

        $tables = [
            'taxes',
            'tax_rates',
            'tax_configs',
            'product_taxes',
            'invoice_taxes',
        ];

        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $rateColumn = null;

            foreach (['rate', 'percentage', 'tax_rate', 'percent', 'value'] as $column) {
                if (Schema::hasColumn($table, $column)) {
                    $rateColumn = $column;
                    break;
                }
            }

            if (! $rateColumn) {
                continue;
            }

            $nameColumn = null;

            foreach (['name', 'label', 'description', 'code'] as $column) {
                if (Schema::hasColumn($table, $column)) {
                    $nameColumn = $column;
                    break;
                }
            }

            $query = DB::table($table);

            if (Schema::hasColumn($table, 'is_active')) {
                $query->where('is_active', true);
            }

            if (Schema::hasColumn($table, 'active')) {
                $query->where('active', true);
            }

            if ($nameColumn) {
                $query->orderBy($nameColumn);
            } else {
                $query->orderBy($rateColumn);
            }

            $options = [];

            foreach ($query->limit(200)->get() as $tax) {
                $rate = (float) ($tax->{$rateColumn} ?? 0);
                $key = static::normalizeTaxRateOptionKey($rate);

                $name = $nameColumn
                    ? trim((string) ($tax->{$nameColumn} ?? ''))
                    : '';

                if ($name === '') {
                    $name = $rate == 0
                        ? 'Exento'
                        : 'IVA ' . rtrim(rtrim(number_format($rate, 4, '.', ''), '0'), '.') . '%';
                }

                $options[$key] = $name . ' (' . number_format($rate, 2) . '%)';
            }

            if (! empty($options)) {
                return $options;
            }
        }

        return $fallback;
    }

    protected static function readOnlyStatusOptions(?PurchaseRequest $record = null): array
    {
        $state = $record?->status ?: 'draft';

        return [
            $state => static::statusOptions()[$state] ?? match ($state) {
                'draft' => 'Borrador',
                'review' => 'En aprobación',
                'approved' => 'Aprobada',
                'rejected' => 'Rechazada',
                'cancelled' => 'Cancelada',
                'converted' => 'Convertida a orden de compra',
                default => ucfirst((string) $state),
            },
        ];
    }


public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Solicitud de compra')
                    ->extraAttributes(['class' => 'bexia-purchase-request-main-section'])
                    ->description('Borrador editable. La ubicación es general para toda la solicitud.')
                    ->schema([
                        Forms\Components\ViewField::make('purchase_request_status_notice')
                            ->label('')
                            ->view('filament.purchases.status-notice')
                            ->viewData(fn (?PurchaseRequest $record): array => [
                                'documentType' => 'purchase_request',
                                'documentId' => $record?->getKey(),
                            ])
                            ->dehydrated(false)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('number')
                            ->label('Folio')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\Select::make('supplier_id')
                            ->label('Proveedor')
                            ->options(fn (): array => static::supplierOptions())
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->placeholder('Sin proveedor')
                            ->reactive()
                            ->afterStateHydrated(function ($state, Forms\Set $set, ?PurchaseRequest $record): void {
                                if ($state) {
                                    $set('supplier_name', static::supplierLabel($state));
                                    return;
                                }

                                if ($record && $record->supplier_name) {
                                    $supplierId = static::supplierIdByName($record->supplier_name);

                                    if ($supplierId) {
                                        $set('supplier_id', $supplierId);
                                        $set('supplier_name', static::supplierLabel($supplierId));
                                    }
                                }
                            })
                            ->afterStateUpdated(fn ($state, Forms\Set $set): mixed => $set('supplier_name', $state ? static::supplierLabel($state) : 'Sin proveedor'))
                            ->helperText('Solo muestra contactos activos marcados como proveedor.'),

                        Forms\Components\Hidden::make('supplier_name')
                            ->dehydrated(true),




                        Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->options(fn (?PurchaseRequest $record): array => static::readOnlyStatusOptions($record))
                            ->default('draft')
                            ->disabled()
                            ->dehydrated(true)
                            ->native(false),

                        Forms\Components\Select::make('warehouse_id')
                            ->label('Almacén destino')
                            ->options(fn (): array => static::warehouseOptions())
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(function ($state, Set $set): void {
                                $set('warehouse_label', static::warehouseLabel($state));
                                $set('location_id', null);
                                $set('location_label', null);
                            }),

                        Forms\Components\Hidden::make('warehouse_label'),

                        Forms\Components\Select::make('location_id')
                            ->label('Ubicación / recepción')
                            ->options(fn (Get $get): array => static::locationOptions($get('warehouse_id')))
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->required()
                            ->afterStateUpdated(fn ($state, Set $set): mixed => $set('location_label', static::locationLabel($state))),

                        Forms\Components\Hidden::make('location_label'),

                        Forms\Components\DateTimePicker::make('requested_at')
                            ->label('Fecha')
                            ->seconds(false)
                            ->default(now()),

                        Forms\Components\TextInput::make('source')
                            ->label('Origen')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'suggested_purchase_list' => 'Lista sugerida de compra',
                                default => $state ?: 'Manual',
                            }),

                        Forms\Components\TextInput::make('budget_amount')
                            ->label('Presupuesto usado')
                            ->prefix('$')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notas / términos')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(3),






                Forms\Components\Section::make('Productos')
                    ->extraAttributes(['class' => 'bexia-purchase-request-products-section'])
                    ->description('Agrega o edita productos.')
                    ->schema([
                        Forms\Components\View::make('filament.components.purchase-request-lines-inline-field')
                            ->viewData(fn (?PurchaseRequest $record): array => [
                                'recordId' => $record?->id,
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Forms\Components\Section::make('Historial de revisión y aprobación')
                    ->extraAttributes(['class' => 'bexia-purchase-request-history-section'])
                    ->description('Registra creación, envíos a revisión, aprobaciones, cancelaciones y cambios de estado.')
                    ->schema([
                        Forms\Components\Placeholder::make('status_history')
                            ->extraAttributes(['class' => 'bexia-purchase-request-status-history-field'])
                            ->label('')
                            ->content(fn (?PurchaseRequest $record): HtmlString => static::statusHistoryHtml($record))
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->columnSpanFull(),
            ]);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\Navigation\BexiaMenuRuntime::shouldRegister(
            'resources.purchaserequestresource',
            fn (): bool => static::bexiaBaseShouldRegisterNavigation(),
        );
    }

    protected static function bexiaBaseShouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('purchases.view')
            );
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('purchases.view')
            );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('Folio')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('related_purchase_order_number')
                    ->label('OC')
                    ->state(fn ($record): string => \App\Support\PurchaseDocumentLinks::orderNumberForRequest((int) $record->getKey()) ?: '—')
                    ->badge()
                    ->color(fn (string $state): string => $state === '—' ? 'gray' : 'success')
                    ->url(fn ($record): ?string => \App\Support\PurchaseDocumentLinks::orderForRequest((int) $record->getKey())
                        ? \App\Support\PurchaseDocumentLinks::orderUrlFromRequest((int) $record->getKey(), (object) $record->toArray())
                        : null
                    )
                    ->tooltip(fn ($record): string => match ((string) (\App\Support\PurchaseDocumentLinks::orderStatusForRequest((int) $record->getKey()) ?? '')) {
                        'draft' => 'OC en borrador',
                        'review' => 'OC pendiente de revisión',
                        'confirmed' => 'OC confirmada',
                        'received' => 'OC recibida',
                        'cancelled' => 'OC cancelada',
                        default => \App\Support\PurchaseDocumentLinks::orderForRequest((int) $record->getKey()) ? 'Abrir orden de compra' : 'Sin orden de compra',
                    })
                    ->sortable(false),


                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => static::statusOptions()[$state] ?? ($state ?: '—'))
                    ->color(fn (?string $state): string => match ($state) {
                        'draft' => 'gray',
                        'review' => 'warning',
                        'approved' => 'success',
                        'cancelled' => 'danger',
                        'converted' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('supplier_name')
                    ->label('Proveedor')
                    ->searchable()
                    ->placeholder('Sin proveedor sugerido'),

                Tables\Columns\TextColumn::make('location_label')
                    ->label('Ubicación')
                    ->placeholder('—')
                    ->searchable(),

                Tables\Columns\TextColumn::make('lines_count')
                    ->label('Líneas')
                    ->counts('lines')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_with_tax')
                    ->label('Total')
                    ->money('MXN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('requested_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('duplicate_purchase_request')
                    ->label('Duplicar')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Duplicar solicitud de compra')
                    ->modalDescription('Se creará una nueva solicitud en borrador con los mismos productos, cantidades, costos e impuestos.')
                    ->url(fn ($record): string => route('purchases.requests.duplicate', ['purchaseRequest' => $record->getKey()])),

                Tables\Actions\Action::make('open_purchase_request_smart')
                    ->label('Abrir')
                    ->icon('heroicon-o-folder-open')
                    ->color('gray')
                    ->url(fn ($record): string => in_array((string) ($record->status ?? ''), ['draft', 'borrador'], true)
                        ? url('/admin/' . (int) ($record->company_id ?? 0) . '/purchase-requests/' . $record->getKey() . '/edit')
                        : url('/admin/' . (int) ($record->company_id ?? 0) . '/purchase-requests/' . $record->getKey())
                    ),
                Tables\Actions\Action::make('create_purchase_order')
                    ->label('Crear orden de compra')
                    ->icon('heroicon-o-shopping-cart')
                    ->color('success')
                    ->visible(fn ($record): bool => in_array((string) ($record->status ?? ''), ['approved', 'aprobada'], true))
                    ->url(fn ($record): string => route('purchases.requests.create-order', ['purchaseRequest' => $record->getKey()])),
                Tables\Actions\Action::make('print')
                    ->label('Imprimir')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn ($record): string => route('purchases.requests.print', ['purchaseRequest' => $record->getKey()]))
                    ->openUrlInNewTab(),
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('review')
                    ->label('Enviar a aprobación')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('warning')
                    ->visible(fn (PurchaseRequest $record): bool => $record->status === 'draft' && static::hasApplicableApprovalWorkflow($record))
                    ->action(fn (PurchaseRequest $record): bool => $record->update(['status' => 'review'])),

                Tables\Actions\Action::make('approve')
                    ->label('Aprobar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (PurchaseRequest $record): bool => in_array($record->status, ['draft', 'review'], true) && (! static::hasApplicableApprovalWorkflow($record) || $record->status === 'review'))
                    ->action(fn (PurchaseRequest $record): bool => $record->update(['status' => 'approved'])),

                Tables\Actions\Action::make('cancel')
                    ->label('Cancelar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (PurchaseRequest $record): bool => ! in_array($record->status, ['cancelled', 'converted'], true))
                    ->action(fn (PurchaseRequest $record): bool => $record->update(['status' => 'cancelled'])),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options(static::statusOptions()),
            ])
            ->defaultSort('created_at', 'desc');
    }
    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchaseRequests::route('/'),
            'create' => Pages\CreatePurchaseRequest::route('/create'),
            'view' => Pages\ViewPurchaseRequest::route('/{record}'),
            'edit' => Pages\EditPurchaseRequest::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return static::userCanManage();
    }

    public static function canEdit(Model $record): bool
    {
        return static::userCanManage() && ! in_array($record->status, ['cancelled', 'converted'], true);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

public static function normalizePurchaseRequestLineData(array $data): array
    {
        $quantity = (float) ($data['requested_quantity'] ?? 0);
        $unitWithoutTax = (float) ($data['unit_cost_without_tax'] ?? 0);
        $taxRate = (float) ($data['tax_rate'] ?? 0);

        $unitWithTax = $unitWithoutTax * (1 + ($taxRate / 100));
        $lineWithoutTax = $quantity * $unitWithoutTax;
        $lineWithTax = $quantity * $unitWithTax;

        $data['available_quantity'] = (float) ($data['available_quantity'] ?? 0);
        $data['suggested_quantity'] = (float) ($data['suggested_quantity'] ?? 0);
        $data['pending_quantity'] = (float) ($data['pending_quantity'] ?? 0);

        $data['unit_cost_with_tax'] = $unitWithTax;
        $data['line_total_without_tax'] = $lineWithoutTax;
        $data['line_tax'] = max(0, $lineWithTax - $lineWithoutTax);
        $data['line_total_with_tax'] = $lineWithTax;

        $data['variant_label'] = $data['variant_label'] ?? '—';
        $data['priority_label'] = $data['priority_label'] ?? 'Normal';
        $data['cost_source'] = $data['cost_source'] ?? 'Manual';

        if (empty($data['product_label']) && ! empty($data['product_id'])) {
            $data['product_label'] = static::productLabel($data['product_id']);
        }

        if (empty($data['variant_label']) && ! empty($data['product_variant_id'])) {
            $data['variant_label'] = static::productLabel($data['product_variant_id'], true);
        }

        return $data;
    }


    public static function recalculateTotals(PurchaseRequest $record): void
    {
        $record->load('lines');

        $totalWithoutTax = 0.0;
        $totalTax = 0.0;
        $totalWithTax = 0.0;

        foreach ($record->lines as $line) {
            $quantity = (float) ($line->requested_quantity ?? 0);
            $unitWithoutTax = (float) ($line->unit_cost_without_tax ?? 0);
            $taxRate = (float) ($line->tax_rate ?? 0);

            $unitWithTax = $unitWithoutTax * (1 + ($taxRate / 100));
            $lineWithoutTax = $quantity * $unitWithoutTax;
            $lineWithTax = $quantity * $unitWithTax;
            $lineTax = max(0, $lineWithTax - $lineWithoutTax);

            $line->forceFill([
                'warehouse_id' => $record->warehouse_id,
                'location_id' => $record->location_id,
                'warehouse_label' => $record->warehouse_label,
                'location_label' => $record->location_label,
                'product_label' => $line->product_label ?: static::productLabel($line->product_id),
                'variant_label' => $line->variant_label ?: ($line->product_variant_id ? static::productLabel($line->product_variant_id, true) : '—'),
                'unit_cost_with_tax' => $unitWithTax,
                'line_total_without_tax' => $lineWithoutTax,
                'line_tax' => $lineTax,
                'line_total_with_tax' => $lineWithTax,
            ])->save();

            $totalWithoutTax += $lineWithoutTax;
            $totalTax += $lineTax;
            $totalWithTax += $lineWithTax;
        }

        $record->forceFill([
            'warehouse_label' => static::warehouseLabel($record->warehouse_id),
            'location_label' => static::locationLabel($record->location_id),
            'total_without_tax' => $totalWithoutTax,
            'total_tax' => $totalTax,
            'total_with_tax' => $totalWithTax,
        ])->save();
    }


    protected static function statusHistoryHtml(?PurchaseRequest $record): HtmlString
    {
        if (! $record || ! $record->exists) {
            return new HtmlString('<div class="bexia-purchase-request-history-empty">Guarda la solicitud para ver historial.</div>');
        }

        if (! Schema::hasTable('purchase_request_status_logs')) {
            return new HtmlString('<div class="bexia-purchase-request-history-unavailable">El historial aún no está disponible.</div>');
        }

        $rows = DB::table('purchase_request_status_logs')
            ->where('purchase_request_id', $record->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        if ($rows->isEmpty()) {
            return new HtmlString('<div class="bexia-purchase-request-history-empty">Sin historial registrado.</div>');
        }

        $labels = static::statusOptions();

        $html = '<div class="bexia-purchase-request-status-history-wrap" role="region" aria-label="Historial de revisión y aprobación" tabindex="0">';
        $html .= '<table class="bexia-purchase-request-status-history-table">';
        $html .= '<thead>';
        $html .= '<tr class="bexia-purchase-request-status-history-header-row">';
        $html .= '<th class="bexia-purchase-request-status-history-th">Fecha</th>';
        $html .= '<th class="bexia-purchase-request-status-history-th">Usuario</th>';
        $html .= '<th class="bexia-purchase-request-status-history-th">Anterior</th>';
        $html .= '<th class="bexia-purchase-request-status-history-th">Nuevo</th>';
        $html .= '<th class="bexia-purchase-request-status-history-th">Detalle</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';

        foreach ($rows as $row) {
            $date = $row->created_at
                ? \Illuminate\Support\Carbon::parse($row->created_at)->format('d/m/Y H:i')
                : '—';

            $from = $row->from_status
                ? ($labels[$row->from_status] ?? $row->from_status)
                : '—';

            $to = $row->to_status
                ? ($labels[$row->to_status] ?? $row->to_status)
                : '—';

            $user = $row->user_name ?: 'Sistema';
            $notes = $row->notes ?: '—';

            $html .= '<tr>';
            $html .= '<td class="bexia-purchase-request-status-history-td">' . e($date) . '</td>';
            $html .= '<td class="bexia-purchase-request-status-history-td">' . e($user) . '</td>';
            $html .= '<td class="bexia-purchase-request-status-history-td">' . e($from) . '</td>';
            $html .= '<td class="bexia-purchase-request-status-history-td bexia-purchase-request-status-history-td--strong">' . e($to) . '</td>';
            $html .= '<td class="bexia-purchase-request-status-history-td">' . e($notes) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>';

        return new HtmlString($html);
    }

    protected static function statusOptions(): array
    {
        return [
            'draft' => 'Borrador',
            'review' => 'Pendiente de revisión',
            'approved' => 'Aprobada',
            'cancelled' => 'Cancelada',
            'converted' => 'Convertida a orden de compra',
        ];
    }

    protected static function totalsHtml(?PurchaseRequest $record): HtmlString
    {
        if (! $record || ! $record->exists) {
            return new HtmlString('<div class="bexia-purchase-request-totals-empty">Guarda para calcular totales.</div>');
        }

        $record->refresh();

        $html = '<div class="bexia-purchase-request-totals-wrap">';
        $html .= '<table class="bexia-purchase-request-totals-table">';

        $html .= '<tr><td class="bexia-purchase-request-totals-label">Importe sin impuestos:</td>';
        $html .= '<td class="bexia-purchase-request-totals-value">$ ' . number_format((float) $record->total_without_tax, 2) . '</td></tr>';

        $html .= '<tr><td class="bexia-purchase-request-totals-label">IVA:</td>';
        $html .= '<td class="bexia-purchase-request-totals-value">$ ' . number_format((float) $record->total_tax, 2) . '</td></tr>';

        $html .= '<tr><td class="bexia-purchase-request-totals-total-label">Total:</td>';
        $html .= '<td class="bexia-purchase-request-totals-total-value">$ ' . number_format((float) $record->total_with_tax, 2) . '</td></tr>';

        $html .= '</table></div>';

        return new HtmlString($html);
    }

    protected static function lineUnitCostWithTaxFromState(Get $get): float
    {
        $unitWithoutTax = (float) ($get('unit_cost_without_tax') ?? 0);
        $taxRate = (float) ($get('tax_rate') ?? 0);

        return $unitWithoutTax * (1 + ($taxRate / 100));
    }

    protected static function lineTotalWithTaxFromState(Get $get): float
    {
        $quantity = (float) ($get('requested_quantity') ?? 0);

        return $quantity * static::lineUnitCostWithTaxFromState($get);
    }


    protected static function supplierOptions(): array
    {
        if (! Schema::hasTable('contacts')) {
            return [];
        }

        $query = DB::table('contacts')
            ->where('is_supplier', true);

        if (Schema::hasColumn('contacts', 'is_active')) {
            $query->where('is_active', true);
        }

        $companyId = static::currentCompanyId();

        if ($companyId && Schema::hasColumn('contacts', 'company_id')) {
            $query->where(function ($query) use ($companyId): void {
                $query->where('company_id', $companyId)->orWhereNull('company_id');
            });
        }

        return $query
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'name', 'commercial_name'])
            ->mapWithKeys(fn ($contact): array => [
                $contact->id => trim((string) ($contact->commercial_name ?: $contact->name ?: ('Proveedor #' . $contact->id))),
            ])
            ->all();
    }

    protected static function supplierLabel($supplierId): string
    {
        if (! $supplierId || ! Schema::hasTable('contacts')) {
            return 'Sin proveedor';
        }

        $contact = DB::table('contacts')
            ->where('id', $supplierId)
            ->first(['id', 'name', 'commercial_name']);

        if (! $contact) {
            return 'Proveedor #' . $supplierId;
        }

        return trim((string) ($contact->commercial_name ?: $contact->name ?: ('Proveedor #' . $supplierId)));
    }

    protected static function supplierIdByName(?string $supplierName): ?int
    {
        $supplierName = trim((string) $supplierName);

        if ($supplierName === '' || in_array($supplierName, ['Sin proveedor', 'Sin proveedor sugerido'], true) || ! Schema::hasTable('contacts')) {
            return null;
        }

        $id = DB::table('contacts')
            ->where('is_supplier', true)
            ->where(function ($query) use ($supplierName): void {
                $query
                    ->where('name', $supplierName)
                    ->orWhere('commercial_name', $supplierName);
            })
            ->value('id');

        return $id ? (int) $id : null;
    }

    protected static function productOptions(): array
    {
        if (! Schema::hasTable('products')) {
            return [];
        }

        $query = DB::table('products');

        if (Schema::hasColumn('products', 'parent_product_id')) {
            $query->whereNull('parent_product_id');
        }

        if (Schema::hasColumn('products', 'is_active')) {
            $query->where('is_active', true);
        }

        $companyId = static::currentCompanyId();

        if ($companyId && Schema::hasColumn('products', 'company_id')) {
            $query->where(function ($query) use ($companyId): void {
                $query->where('company_id', $companyId)->orWhereNull('company_id');
            });
        }

        return $query
            ->orderBy('name')
            ->limit(500)
            ->get()
            ->mapWithKeys(fn ($product): array => [
                $product->id => static::productLabelFromRow($product),
            ])
            ->all();
    }

    protected static function variantOptions($productId): array
    {
        if (! $productId || ! Schema::hasTable('products') || ! Schema::hasColumn('products', 'parent_product_id')) {
            return [];
        }

        return DB::table('products')
            ->where('parent_product_id', $productId)
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn ($product): array => [
                $product->id => static::productLabelFromRow($product, true),
            ])
            ->all();
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
            $query->where(function ($query) use ($companyId): void {
                $query->where('company_id', $companyId)->orWhereNull('company_id');
            });
        }

        return $query
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn ($warehouse): array => [
                $warehouse->id => static::warehouseLabelFromRow($warehouse),
            ])
            ->all();
    }

    protected static function locationOptions($warehouseId = null): array
    {
        if (! Schema::hasTable('stock_locations')) {
            return [];
        }

        $query = DB::table('stock_locations');

        if ($warehouseId && Schema::hasColumn('stock_locations', 'warehouse_id')) {
            $query->where('warehouse_id', $warehouseId);
        }

        if (Schema::hasColumn('stock_locations', 'is_active')) {
            $query->where('is_active', true);
        }

        $companyId = static::currentCompanyId();

        if ($companyId && Schema::hasColumn('stock_locations', 'company_id')) {
            $query->where(function ($query) use ($companyId): void {
                $query->where('company_id', $companyId)->orWhereNull('company_id');
            });
        }

        return $query
            ->orderBy('name')
            ->limit(500)
            ->get()
            ->mapWithKeys(fn ($location): array => [
                $location->id => static::locationLabelFromRow($location),
            ])
            ->all();
    }

    protected static function productLabel($productId, bool $variant = false): string
    {
        if (! $productId || ! Schema::hasTable('products')) {
            return '—';
        }

        $row = DB::table('products')->where('id', $productId)->first();

        return $row ? static::productLabelFromRow($row, $variant) : 'Producto #' . $productId;
    }

    protected static function productLabelFromRow(object $product, bool $variant = false): string
    {
        $reference = '';

        foreach (['internal_reference', 'sku', 'barcode', 'code'] as $column) {
            if (Schema::hasColumn('products', $column)) {
                $value = trim((string) ($product->{$column} ?? ''));

                if ($value !== '') {
                    $reference = $value;
                    break;
                }
            }
        }

        if ($variant) {
            $group = Schema::hasColumn('products', 'variant_group') ? trim((string) ($product->variant_group ?? '')) : '';
            $value = Schema::hasColumn('products', 'variant_value') ? trim((string) ($product->variant_value ?? '')) : '';
            $variantText = $group && $value ? $group . ': ' . $value : ($value ?: (string) ($product->name ?? ''));

            return trim(($reference ? $reference . ' - ' : '') . ($variantText ?: 'Variante #' . $product->id));
        }

        $name = Schema::hasColumn('products', 'name') ? trim((string) ($product->name ?? '')) : '';

        return trim(($reference ? $reference . ' - ' : '') . ($name ?: 'Producto #' . $product->id));
    }

    protected static function warehouseLabel($warehouseId): string
    {
        if (! $warehouseId || ! Schema::hasTable('warehouses')) {
            return '—';
        }

        $row = DB::table('warehouses')->where('id', $warehouseId)->first();

        return $row ? static::warehouseLabelFromRow($row) : 'Almacén #' . $warehouseId;
    }

    protected static function warehouseLabelFromRow(object $warehouse): string
    {
        $code = Schema::hasColumn('warehouses', 'code') ? trim((string) ($warehouse->code ?? '')) : '';
        $name = Schema::hasColumn('warehouses', 'name') ? trim((string) ($warehouse->name ?? '')) : '';

        return trim(($code ? $code . ' - ' : '') . ($name ?: ('Almacén #' . $warehouse->id)));
    }

    protected static function locationLabel($locationId): string
    {
        if (! $locationId || ! Schema::hasTable('stock_locations')) {
            return '—';
        }

        $row = DB::table('stock_locations')->where('id', $locationId)->first();

        return $row ? static::locationLabelFromRow($row) : 'Ubicación #' . $locationId;
    }

    protected static function locationLabelFromRow(object $location): string
    {
        $code = Schema::hasColumn('stock_locations', 'code') ? trim((string) ($location->code ?? '')) : '';
        $name = Schema::hasColumn('stock_locations', 'name') ? trim((string) ($location->name ?? '')) : '';

        return trim(($code ? $code . ' - ' : '') . ($name ?: ('Ubicación #' . $location->id)));
    }

    protected static function productCostWithoutTax($productId): float
    {
        if (! $productId || ! Schema::hasTable('products')) {
            return 0;
        }

        $row = DB::table('products')->where('id', $productId)->first();

        if (! $row) {
            return 0;
        }

        foreach (['average_cost_without_tax', 'standard_cost', 'purchase_price', 'cost'] as $column) {
            if (Schema::hasColumn('products', $column)) {
                $value = $row->{$column} ?? null;

                if ($value !== null && (float) $value > 0) {
                    return (float) $value;
                }
            }
        }

        return 0;
    }
    protected static function normalizeTaxRateOptionKey($rate): string
    {
        $rate = is_numeric($rate) ? (float) $rate : 0.0;

        return rtrim(rtrim(number_format($rate, 4, '.', ''), '0'), '.') ?: '0';
    }



    protected static function productPurchaseTaxRate($productId): float
    {
        if (! $productId || ! Schema::hasTable('products')) {
            return 16;
        }

        if (! Schema::hasColumn('products', 'purchase_tax_rate')) {
            return 16;
        }

        $value = DB::table('products')->where('id', $productId)->value('purchase_tax_rate');

        return $value !== null ? (float) $value : 16;
    }


    public static function hasApplicableApprovalWorkflow(?PurchaseRequest $record): bool
    {
        if (! $record || ! $record->exists || ! Schema::hasTable('approval_workflows')) {
            return false;
        }

        $amount = (float) ($record->total_with_tax ?? 0);
        $companyId = $record->company_id ?: static::currentCompanyId();
        $warehouseId = $record->warehouse_id ?: null;
        $requesterUserId = $record->requested_by_user_id ?: auth()->id();

        $query = DB::table('approval_workflows')
            ->where('document_type', 'purchase_request')
            ->where('is_active', true)
            ->where(function ($query) use ($companyId): void {
                if ($companyId) {
                    $query->where('company_id', $companyId)->orWhereNull('company_id');
                } else {
                    $query->whereNull('company_id');
                }
            })
            ->where(function ($query) use ($amount): void {
                $query->whereNull('amount_min')->orWhere('amount_min', '<=', $amount);
            })
            ->where(function ($query) use ($amount): void {
                $query->whereNull('amount_max')->orWhere('amount_max', '>=', $amount);
            })
            ->where(function ($query) use ($warehouseId): void {
                if ($warehouseId) {
                    $query->whereNull('applies_to_warehouse_id')->orWhere('applies_to_warehouse_id', $warehouseId);
                } else {
                    $query->whereNull('applies_to_warehouse_id');
                }
            })
            ->orderBy('priority')
            ->limit(50);

        $workflows = $query->get();

        foreach ($workflows as $workflow) {
            if ($workflow->applies_to_user_id && (int) $workflow->applies_to_user_id !== (int) $requesterUserId) {
                continue;
            }

            if ($workflow->applies_to_role_name && ! static::requesterHasRole($requesterUserId, (string) $workflow->applies_to_role_name)) {
                continue;
            }

            return true;
        }

        return false;
    }

    protected static function requesterHasRole($userId, string $roleName): bool
    {
        if (! $userId || $roleName === '') {
            return false;
        }

        $userModel = config('auth.providers.users.model', \App\Models\User::class);

        if (class_exists($userModel)) {
            $user = $userModel::query()->find($userId);

            if ($user && method_exists($user, 'hasRole')) {
                return $user->hasRole($roleName);
            }
        }

        if (
            Schema::hasTable('roles')
            && Schema::hasTable('model_has_roles')
        ) {
            return DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('model_has_roles.model_id', $userId)
                ->where('roles.name', $roleName)
                ->exists();
        }

        return false;
    }

    protected static function userCanView(): bool
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
                'Compras',
                'Inventarios',
                'Reportes',
            ])
        ) {
            return true;
        }

        return method_exists($user, 'can')
            ? $user->can('purchases.view_purchase_requests') || $user->can('purchases.view')
            : false;
    }

    protected static function userCanManage(): bool
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
                'Compras',
            ])
        ) {
            return true;
        }

        return method_exists($user, 'can')
            ? $user->can('purchases.manage_purchase_requests')
            : false;
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
}
