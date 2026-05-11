<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvoiceResource\Pages;
use App\Filament\Resources\InvoiceResource\RelationManagers\LinesRelationManager;
use App\Filament\Resources\InvoiceResource\RelationManagers\CfdiAuditsRelationManager;
use App\Filament\Resources\InvoiceResource\RelationManagers\PaymentsRelationManager;
use App\Models\Invoice;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $tenantOwnershipRelationshipName = 'company';

    protected static ?string $tenantRelationshipName = 'invoices';

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Portal de facturación';

    protected static ?string $navigationLabel = 'Facturas';

    protected static ?string $modelLabel = 'factura';

    protected static ?string $pluralModelLabel = 'facturas';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return true;
    }

    public static function canEdit(Model $record): bool
    {
        return ! in_array((string) ($record->status ?? ''), ['cancelled'], true);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['company'])
            ->withCount('lines');

        $tenant = Filament::getTenant();

        if ($tenant && isset($tenant->id)) {
            $tenantId = (int) $tenant->id;

            $query->where(function (Builder $q) use ($tenantId): void {
                $q->where('company_id', $tenantId)
                    ->orWhereNull('company_id');
            });
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Cabecera')
                ->description('Selecciona el cliente y el uso CFDI. El regimen fiscal y los datos fiscales se toman desde Contactos.')
                ->columns(12)
                ->schema([
                    Forms\Components\Placeholder::make('number_display')
                        ->label('Folio')
                        ->content(fn (?Invoice $record): string => $record?->number ?: 'Automatico')
                        ->columnSpan(2),

                    Forms\Components\Select::make('status')
                        ->label('Estado')
                        ->options(static::statusOptions())
                        ->default('draft')
                        ->disabled()
                        ->dehydrated(true)
                        ->columnSpan(2),

                    Forms\Components\DatePicker::make('invoice_date')
                        ->label('Fecha')
                        ->default(now())
                        ->disabled(fn (?Invoice $record): bool => static::isLocked($record))
                        ->columnSpan(2),

                    Forms\Components\Select::make('contact_id')
                        ->label('Cliente')
                        ->searchable()
                        ->preload()
                        ->options(fn (): array => static::initialCustomerOptions())
                        ->getSearchResultsUsing(fn (string $search): array => static::customerSearchOptions($search))
                        ->getOptionLabelUsing(fn ($value): ?string => static::customerLabel((int) $value))
                        ->reactive()
                        ->afterStateUpdated(function ($state, Forms\Set $set): void {
                            static::fillCustomerFields((int) ($state ?? 0), $set);
                        })
                        ->disabled(fn (?Invoice $record): bool => ! static::canChangeCustomer($record))
                        ->columnSpan(4),

                    Forms\Components\TextInput::make('currency_code')
                        ->label('Moneda')
                        ->default('MXN')
                        ->maxLength(10)
                        ->disabled(fn (?Invoice $record): bool => static::isLocked($record))
                        ->columnSpan(1),

                    Forms\Components\Placeholder::make('customer_tax_regime_display')
                        ->label('Regimen fiscal')
                        ->content(fn (Forms\Get $get, ?Invoice $record): string => static::taxRegimeLabel(
                            (string) ($get('customer_tax_regime_code') ?: $record?->customer_tax_regime_code ?: '')
                        ) ?: 'N/D')
                        ->columnSpan(3),

                    Forms\Components\Select::make('customer_cfdi_use_code')
                        ->label('Uso CFDI')
                        ->options(fn (Forms\Get $get, ?Invoice $record): array => static::cfdiUseOptionsForRegime(
                            (string) ($get('customer_tax_regime_code') ?: $record?->customer_tax_regime_code ?: '')
                        ))
                        ->searchable()
                        ->reactive()
                        ->disabled(fn (?Invoice $record): bool => static::isLocked($record))
                        ->dehydrated(true)
                        ->helperText('Opciones filtradas segun el regimen fiscal del cliente.')
                        ->columnSpan(3),

                    Forms\Components\Select::make('source_type')
                        ->label('Origen')
                        ->default('manual')
                        ->options([
                            'manual' => 'Manual',
                            'pos_order' => 'Ticket PDV',
                            'sales_order' => 'Venta',
                            'other' => 'Otro',
                        ])
                        ->disabled(fn (?Invoice $record): bool => $record && (string) $record->source_type !== 'manual')
                        ->dehydrated(true)
                        ->columnSpan(2),

                    Forms\Components\TextInput::make('source_number')
                        ->label('Referencia')
                        ->maxLength(120)
                        ->disabled(fn (?Invoice $record): bool => static::isLocked($record))
                        ->columnSpan(4),

                    Forms\Components\Placeholder::make('customer_change_warning_live')
                        ->label('Aviso')
                        ->content('Cambiaste el cliente de la factura. Al guardar, se registrara el cambio y se usara la informacion fiscal del nuevo cliente.')
                        ->visible(fn (Forms\Get $get, ?Invoice $record): bool => $record
                            && ! static::isLocked($record)
                            && (int) ($record->contact_id ?? 0) !== (int) ($get('contact_id') ?? 0))
                        ->columnSpanFull(),

                    Forms\Components\Placeholder::make('customer_change_warning_saved')
                        ->label('Aviso de cambio de cliente')
                        ->content(fn (?Invoice $record): string => static::customerChangedMessage($record))
                        ->visible(fn (?Invoice $record): bool => static::customerChangedMessage($record) !== '')
                        ->columnSpanFull(),

                    Forms\Components\Section::make('Informacion fiscal del cliente')
                        ->description('Solo lectura. Si algun dato esta incorrecto, corrigelo en Contactos y vuelve a seleccionar el cliente.')
                        ->columns(1)
                        ->columnSpanFull()
                        ->schema([
                            Forms\Components\Placeholder::make('customer_fiscal_summary')
                                ->label('')
                                ->content(fn (Forms\Get $get, ?Invoice $record): \Illuminate\Support\HtmlString => static::customerFiscalSummaryHtml($record, $get)),
                        ]),

                    Forms\Components\Textarea::make('cancel_reason')
                        ->label('Motivo de cancelacion')
                        ->rows(2)
                        ->visible(fn (?Invoice $record): bool => (string) ($record?->status ?? '') === 'cancelled')
                        ->columnSpanFull(),

                    Forms\Components\Hidden::make('company_id')
                        ->default(fn (): int => static::tenantCompanyId())
                        ->dehydrated(true),

                    Forms\Components\Hidden::make('customer_name')
                        ->dehydrated(true),

                    Forms\Components\Hidden::make('customer_fiscal_name')
                        ->dehydrated(true),

                    Forms\Components\Hidden::make('customer_rfc')
                        ->dehydrated(true),

                    Forms\Components\Hidden::make('customer_postal_code')
                        ->dehydrated(true),

                    Forms\Components\Hidden::make('customer_whatsapp_phone')
                        ->dehydrated(true),

                    Forms\Components\Hidden::make('customer_tax_regime_code')
                        ->dehydrated(true),

                    Forms\Components\Hidden::make('payment_form_code')
                        ->dehydrated(true),

                    Forms\Components\Hidden::make('payment_method_code')
                        ->dehydrated(true),

                    Forms\Components\Hidden::make('payment_terms')
                        ->dehydrated(true),

                    Forms\Components\Hidden::make('due_date')
                        ->dehydrated(true),

                    Forms\Components\Hidden::make('paid_total')
                        ->dehydrated(true),

                    Forms\Components\Hidden::make('subtotal')
                        ->dehydrated(true),

                    Forms\Components\Hidden::make('discount_total')
                        ->dehydrated(true),

                    Forms\Components\Hidden::make('tax_total')
                        ->dehydrated(true),

                    Forms\Components\Hidden::make('total')
                        ->dehydrated(true),

                    Forms\Components\Hidden::make('balance_total')
                        ->dehydrated(true),
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

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => static::statusLabel($state))
                    ->color(fn ($state): string => match ((string) $state) {
                        'draft' => 'gray',
                        'issued' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),


                Tables\Columns\TextColumn::make('cfdi_status')
                    ->label('Estado CFDI')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => static::cfdiStatusLabel($state))
                    ->color(fn ($state): string => static::cfdiStatusColor($state))
                    ->placeholder('Pendiente')
                    ->sortable(),

                Tables\Columns\TextColumn::make('source_type')
                    ->label('Origen')
                    ->formatStateUsing(fn ($state): string => static::sourceLabel($state))
                    ->badge()
                    ->color(fn ($state): string => match ((string) $state) {
                        'manual' => 'gray',
                        'pos_order' => 'warning',
                        'sales_order' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('source_number')
                    ->label('Referencia')
                    ->searchable()
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer_fiscal_name')
                    ->label('Cliente')
                    ->searchable()
                    ->placeholder('Público general')
                    ->limit(40),

                Tables\Columns\TextColumn::make('lines_count')
                    ->label('Líneas')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->alignRight()
                    ->formatStateUsing(fn ($state, $record): string => trim((string) ($record->currency_code ?? 'MXN')) . ' ' . number_format((float) $state, 2))
                    ->sortable(),

                Tables\Columns\TextColumn::make('paid_total')
                    ->label('Pagado')
                    ->alignRight()
                    ->formatStateUsing(fn ($state): string => '$' . number_format((float) $state, 2))
                    ->sortable(),

                Tables\Columns\TextColumn::make('balance_total')
                    ->label('Saldo')
                    ->alignRight()
                    ->formatStateUsing(fn ($state): string => '$' . number_format((float) $state, 2))
                    ->sortable(),

                Tables\Columns\TextColumn::make('invoice_date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options(static::statusOptions()),

                Tables\Filters\SelectFilter::make('source_type')
                    ->label('Origen')
                    ->options([
                        'manual' => 'Manual',
                        'pos_order' => 'Ticket PDV',
                        'sales_order' => 'Venta',
                    ]),
            ])
            ->actions([

                Tables\Actions\Action::make('validate_cfdi_row')
                    ->label('Validar CFDI')
                    ->icon('heroicon-o-shield-check')
                    ->color('info')
                    ->visible(fn ($record): bool => ! in_array((string) ($record->cfdi_status ?? ''), ['stamped', 'cancelled'], true))
                    ->action(function ($record): void {
                        static::recalculateInvoice($record);
                        $record->refresh();

                        $result = app(\App\Support\Billing\InvoiceCfdiValidator::class)->validate($record, auth()->user());

                        \Filament\Notifications\Notification::make()
                            ->title($result['success'] ? 'Factura lista para timbrar' : 'Factura con errores CFDI')
                            ->body($result['message'])
                            ->color($result['success'] ? 'success' : 'danger')
                            ->send();

                        $record->refresh();
                    }),

                Tables\Actions\Action::make('issue_invoice')
                    ->label('Facturar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Invoice $record): bool => (string) $record->status === 'draft')
                    ->requiresConfirmation()
                    ->modalHeading('Marcar factura como facturada')
                    ->modalDescription('La factura quedará bloqueada para edición. No timbra CFDI todavía.')
                    ->action(function (Invoice $record): void {
                        static::issueInvoice($record);
                    }),

                Tables\Actions\Action::make('cancel_invoice')
                    ->label('Cancelar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Invoice $record): bool => (string) $record->status !== 'cancelled')
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Motivo')
                            ->rows(3),
                    ])
                    ->requiresConfirmation()
                    ->action(function (Invoice $record, array $data): void {
                        static::cancelInvoice($record, (string) ($data['reason'] ?? ''));
                    }),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            LinesRelationManager::class,
            CfdiAuditsRelationManager::class,
            PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'create' => Pages\CreateInvoice::route('/create'),
            'view' => Pages\ViewInvoice::route('/{record}'),
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
        ];
    }

    public static function statusOptions(): array
    {
        return [
            'draft' => 'Borrador',
            'issued' => 'Facturado',
            'cancelled' => 'Cancelado',
        ];
    }

    public static function statusLabel(?string $status): string
    {
        return static::statusOptions()[(string) $status] ?? ($status ?: 'Sin estado');
    }

    public static function sourceLabel(?string $sourceType): string
    {
        return match ((string) $sourceType) {
            'manual' => 'Manual',
            'pos_order' => 'Ticket PDV',
            'sales_order' => 'Venta',
            'other' => 'Otro',
            default => $sourceType ?: 'Manual',
        };
    }

    public static function isLocked(?Invoice $record): bool
    {
        if (! $record) {
            return false;
        }

        return in_array((string) $record->status, ['issued', 'cancelled'], true);
    }

    public static function canModifyLines(?Invoice $record): bool
    {
        if (! $record) {
            return false;
        }

        if (in_array((string) $record->status, ['issued', 'cancelled'], true)) {
            return false;
        }

        return static::currentUserCanModifyInvoice();
    }

    public static function tenantCompanyId(): int
    {
        if (Filament::getTenant()) {
            return (int) Filament::getTenant()->getKey();
        }

        return (int) (request()->route('tenant') ?? auth()->user()?->company_id ?? 0);
    }

    public static function companyRow(?int $companyId = null): ?object
    {
        $companyId = $companyId ?: static::tenantCompanyId();

        if ($companyId <= 0 || ! Schema::hasTable('companies')) {
            return null;
        }

        return DB::table('companies')->where('id', $companyId)->first();
    }

    public static function fillCustomerFields(int $contactId, Forms\Set $set): void
    {
        $contact = static::contactById($contactId);

        if (! $contact) {
            $set('customer_name', null);
            $set('customer_fiscal_name', null);
            $set('customer_rfc', null);
            $set('customer_postal_code', null);
            $set('customer_whatsapp_phone', null);
            $set('customer_tax_regime_code', null);
            $set('customer_cfdi_use_code', null);
            $set('payment_form_code', null);
            $set('payment_method_code', null);
            $set('payment_terms', null);

            return;
        }

        $regime = (string) (($contact->sat_tax_regime_code ?? '') ?: ($contact->tax_regime ?? ''));
        $preferredUse = (string) (($contact->customer_cfdi_use_code ?? '') ?: ($contact->sat_cfdi_use_code ?? '') ?: ($contact->cfdi_use_code ?? ''));
        $validUse = static::validCfdiUseForRegime($regime, $preferredUse);

        $set('customer_name', static::contactDisplayName($contact));
        $set('customer_fiscal_name', (string) (($contact->fiscal_name ?? '') ?: ($contact->name ?? '') ?: ($contact->commercial_name ?? '')));
        $set('customer_rfc', (string) ($contact->rfc ?? ''));
        $set('customer_postal_code', (string) (($contact->fiscal_zip ?? '') ?: ($contact->fiscal_postal_code ?? '') ?: ($contact->postal_code ?? '')));
        $set('customer_whatsapp_phone', static::contactWhatsappPhone($contact));
        $set('customer_tax_regime_code', $regime);
        $set('customer_cfdi_use_code', $validUse);
        $set('payment_form_code', (string) (($contact->customer_payment_form_code ?? '') ?: ($contact->payment_form_code ?? '')));
        $set('payment_method_code', (string) (($contact->customer_payment_method_code ?? '') ?: ($contact->payment_method_code ?? '')));
        $set('payment_terms', (string) (($contact->customer_payment_terms_text ?? '') ?: ($contact->sales_payment_terms ?? '')));
    }

    public static function initialCustomerOptions(): array
    {
        return static::customerSearchOptions('', 50);
    }

    public static function customerSearchOptions(string $search, int $limit = 80): array
    {
        if (! Schema::hasTable('contacts')) {
            return [];
        }

        $companyId = static::tenantCompanyId();
        $search = trim($search);

        $query = DB::table('contacts');

        if (Schema::hasColumn('contacts', 'company_id') && $companyId > 0) {
            $query->where('company_id', $companyId);
        }

        if (Schema::hasColumn('contacts', 'is_customer')) {
            $query->where('is_customer', true);
        }

        if (Schema::hasColumn('contacts', 'is_active')) {
            $query->where('is_active', true);
        }

        if (Schema::hasColumn('contacts', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        if ($search !== '') {
            $like = '%' . mb_strtolower($search) . '%';

            $query->where(function ($q) use ($like) {
                foreach (['name', 'commercial_name', 'fiscal_name', 'rfc', 'email', 'phone', 'mobile'] as $column) {
                    if (Schema::hasColumn('contacts', $column)) {
                        $q->orWhereRaw("LOWER(COALESCE({$column}, '')) LIKE ?", [$like]);
                    }
                }
            });
        }

        $orderColumn = Schema::hasColumn('contacts', 'commercial_name') ? 'commercial_name' : 'name';

        return $query
            ->orderBy($orderColumn)
            ->limit($limit)
            ->get()
            ->mapWithKeys(fn ($contact): array => [
                (int) $contact->id => static::contactDisplayName($contact),
            ])
            ->all();
    }

    public static function contactById(int $contactId): ?object
    {
        if ($contactId <= 0 || ! Schema::hasTable('contacts')) {
            return null;
        }

        return DB::table('contacts')->where('id', $contactId)->first();
    }

    public static function customerLabel(int $contactId): ?string
    {
        $contact = static::contactById($contactId);

        return $contact ? static::contactDisplayName($contact) : null;
    }

    public static function contactDisplayName(object $contact): string
    {
        $name = trim((string) (($contact->commercial_name ?? '') ?: ($contact->name ?? '') ?: ($contact->fiscal_name ?? '')));
        $rfc = trim((string) ($contact->rfc ?? ''));

        if ($name === '') {
            $name = 'Contacto #' . ($contact->id ?? '');
        }

        return $rfc !== '' ? "{$name} ({$rfc})" : $name;
    }

    public static function initialProductOptions(): array
    {
        return static::productSearchOptions('', 50);
    }

    public static function productSearchOptions(string $search, int $limit = 80): array
    {
        if (! Schema::hasTable('products')) {
            return [];
        }

        $companyId = static::tenantCompanyId();
        $search = trim($search);

        $query = DB::table('products');

        if (Schema::hasColumn('products', 'company_id') && $companyId > 0) {
            $query->where('company_id', $companyId);
        }

        if (Schema::hasColumn('products', 'is_active')) {
            $query->where('is_active', true);
        }

        if (Schema::hasColumn('products', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        if (Schema::hasColumn('products', 'is_variant')) {
            $query->where(function ($q) {
                $q->where('is_variant', false)->orWhereNull('is_variant');
            });
        }

        if ($search !== '') {
            $like = '%' . mb_strtolower($search) . '%';

            $query->where(function ($q) use ($like) {
                foreach (['name', 'sku', 'barcode', 'internal_reference', 'description'] as $column) {
                    if (Schema::hasColumn('products', $column)) {
                        $q->orWhereRaw("LOWER(COALESCE({$column}, '')) LIKE ?", [$like]);
                    }
                }
            });
        }

        return $query
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->mapWithKeys(fn ($product): array => [
                (int) $product->id => static::productLabelFromRow($product),
            ])
            ->all();
    }

    public static function productById(int $productId): ?object
    {
        if ($productId <= 0 || ! Schema::hasTable('products')) {
            return null;
        }

        return DB::table('products')->where('id', $productId)->first();
    }

    public static function productLabel(int $productId): ?string
    {
        $product = static::productById($productId);

        return $product ? static::productLabelFromRow($product) : null;
    }

    public static function productLabelFromRow(object $product): string
    {
        $name = trim((string) ($product->name ?? ''));
        $sku = trim((string) (($product->sku ?? '') ?: ($product->internal_reference ?? '')));

        if ($name === '') {
            $name = 'Producto #' . ($product->id ?? '');
        }

        return $sku !== '' ? "{$sku} - {$name}" : $name;
    }

    public static function productSalePriceWithoutTax(object $product): float
    {
        foreach (['sale_price_without_tax', 'price_without_tax', 'unit_price_without_tax', 'sale_price', 'price', 'list_price', 'public_price'] as $column) {
            if (property_exists($product, $column) && (float) ($product->{$column} ?? 0) > 0) {
                return round((float) $product->{$column}, 6);
            }
        }

        return 0.0;
    }

    public static function productSaleTaxRate(object $product): float
    {
        foreach (['sale_tax_rate', 'tax_rate', 'vat_rate'] as $column) {
            if (property_exists($product, $column) && $product->{$column} !== null) {
                return round((float) $product->{$column}, 6);
            }
        }

        return 16.0;
    }

    public static function normalizeLineData(array $data): array
    {
        $productId = (int) ($data['product_id'] ?? 0);
        $product = $productId > 0 ? static::productById($productId) : null;

        if ($product) {
            if (empty($data['product_name'])) {
                $data['product_name'] = static::productLabelFromRow($product);
            }

            if (empty($data['description'])) {
                $data['description'] = static::productLabelFromRow($product);
            }

            foreach ([
                'sat_product_service_code',
                'sat_unit_code',
                'sat_tax_object_code',
            ] as $column) {
                if (empty($data[$column]) && property_exists($product, $column)) {
                    $data[$column] = (string) ($product->{$column} ?? '');
                }
            }

            if (! isset($data['unit_price_without_tax']) || (float) $data['unit_price_without_tax'] <= 0) {
                $data['unit_price_without_tax'] = static::productSalePriceWithoutTax($product);
            }

            if (! isset($data['tax_rate']) || $data['tax_rate'] === '') {
                $data['tax_rate'] = static::productSaleTaxRate($product);
            }
        }

        $quantity = (float) ($data['quantity'] ?? 0);
        $price = (float) ($data['unit_price_without_tax'] ?? 0);
        $taxRate = (float) ($data['tax_rate'] ?? 0);

        $subtotal = round($quantity * $price, 4);
        $tax = round($subtotal * ($taxRate / 100), 4);
        $total = round($subtotal + $tax, 4);

        $data['company_id'] = (int) ($data['company_id'] ?? static::tenantCompanyId());
        $data['source_type'] = (string) ($data['source_type'] ?? 'manual_line');
        $data['product_name'] = (string) ($data['product_name'] ?? $data['description'] ?? 'Producto');
        $data['description'] = (string) ($data['description'] ?? $data['product_name']);
        $data['quantity'] = $quantity;
        $data['unit_price_without_tax'] = $price;
        $data['unit_price'] = round($price * (1 + ($taxRate / 100)), 6);
        $data['tax_rate'] = $taxRate;
        $data['subtotal'] = $subtotal;
        $data['discount_total'] = (float) ($data['discount_total'] ?? 0);
        $data['tax_total'] = $tax;
        $data['total'] = $total;
        $data['metadata'] = $data['metadata'] ?? [];

        return $data;
    }

    public static function recalculateInvoice(Invoice $invoice): void
    {
        if (! Schema::hasTable('invoice_lines')) {
            return;
        }

        DB::table('invoice_lines')
            ->where('invoice_id', $invoice->id)
            ->update([
                'company_id' => (int) $invoice->company_id,
                'updated_at' => now(),
            ]);

        $subtotal = (float) DB::table('invoice_lines')->where('invoice_id', $invoice->id)->sum('subtotal');
        $discount = (float) DB::table('invoice_lines')->where('invoice_id', $invoice->id)->sum('discount_total');
        $tax = (float) DB::table('invoice_lines')->where('invoice_id', $invoice->id)->sum('tax_total');
        $total = (float) DB::table('invoice_lines')->where('invoice_id', $invoice->id)->sum('total');

        $paid = (float) ($invoice->paid_total ?? 0);

        if (Schema::hasTable('invoice_payments')) {
            $hasPayments = DB::table('invoice_payments')
                ->where('invoice_id', $invoice->id)
                ->exists();

            if ($hasPayments) {
                $paid = (float) DB::table('invoice_payments')
                    ->where('invoice_id', $invoice->id)
                    ->where(function ($q) {
                        $q->whereNull('status')
                          ->orWhereNotIn('status', ['cancelled', 'void']);
                    })
                    ->sum('amount');
            }
        }

        DB::table('invoices')
            ->where('id', $invoice->id)
            ->update([
                'subtotal' => round($subtotal, 4),
                'discount_total' => round($discount, 4),
                'tax_total' => round($tax, 4),
                'total' => round($total, 4),
                'paid_total' => round($paid, 4),
                'balance_total' => round($total - $paid, 4),
                'updated_at' => now(),
            ]);
    }

    public static function issueInvoice(Invoice $record): void
    {
        static::recalculateInvoice($record);
        $record->refresh();

        if (! $record->lines()->exists()) {
            Notification::make()
                ->title('No se puede facturar')
                ->body('La factura debe tener al menos una línea.')
                ->warning()
                ->send();

            return;
        }

        DB::table('invoices')
            ->where('id', $record->id)
            ->update([
                'status' => 'issued',
                'issued_at' => now(),
                'updated_at' => now(),
            ]);

        Notification::make()
            ->title('Factura marcada como facturada')
            ->success()
            ->send();
    }

    public static function cancelInvoice(Invoice $record, string $reason = ''): void
    {
        DB::table('invoices')
            ->where('id', $record->id)
            ->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by_user_id' => auth()->id(),
                'cancel_reason' => $reason !== '' ? $reason : null,
                'updated_at' => now(),
            ]);

        Notification::make()
            ->title('Factura cancelada')
            ->warning()
            ->send();
    }

    public static function currentUserCanModifyInvoice(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (method_exists($user, 'hasAnyRole')) {
            try {
                if ($user->hasAnyRole(['super_admin', 'admin', 'Administrador', 'Cajero', 'cajero', 'cashier'])) {
                    return true;
                }
            } catch (\Throwable $e) {
                // Si el sistema de roles no está disponible, caemos al permiso general autenticado.
            }
        }

        if (method_exists($user, 'hasRole')) {
            foreach (['super_admin', 'admin', 'Administrador', 'Cajero', 'cajero', 'cashier'] as $role) {
                try {
                    if ($user->hasRole($role)) {
                        return true;
                    }
                } catch (\Throwable $e) {
                    // Ignorar y continuar.
                }
            }
        }

        return true;
    }

    public static function customerChangedMessage(?Invoice $record): string
    {
        if (! $record) {
            return '';
        }

        $metadata = $record->metadata ?? [];

        if (is_string($metadata)) {
            $decoded = json_decode($metadata, true);
            $metadata = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($metadata) || empty($metadata['customer_changed_at'])) {
            return '';
        }

        $from = (string) ($metadata['customer_changed_from_label'] ?? 'cliente anterior');
        $to = (string) ($metadata['customer_changed_to_label'] ?? 'cliente actual');
        $at = (string) ($metadata['customer_changed_at'] ?? '');

        return "El cliente fue cambiado de {$from} a {$to}. Fecha: {$at}.";
    }



    public static function canModifyPayments(?Invoice $record): bool
    {
        if (! $record) {
            return false;
        }

        if ((string) $record->status === 'cancelled') {
            return false;
        }

        return static::currentUserCanModifyInvoice();
    }



    public static function customerFiscalSummaryHtml(?Invoice $record, Forms\Get $get): \Illuminate\Support\HtmlString
    {
        $contactId = (int) ($get('contact_id') ?: ($record?->contact_id ?? 0));
        $contact = $contactId > 0 ? static::contactById($contactId) : null;

        $fiscalName = $contact
            ? (string) (($contact->fiscal_name ?? '') ?: ($contact->name ?? '') ?: ($contact->commercial_name ?? ''))
            : (string) ($record?->customer_fiscal_name ?? $record?->customer_name ?? '');

        $rfc = $contact
            ? (string) ($contact->rfc ?? '')
            : (string) ($record?->customer_rfc ?? '');

        $postalCode = $contact
            ? (string) (($contact->fiscal_zip ?? '') ?: ($contact->fiscal_postal_code ?? '') ?: ($contact->postal_code ?? ''))
            : (string) ($record?->customer_postal_code ?? '');

        $taxRegimeCode = $contact
            ? (string) ($contact->sat_tax_regime_code ?? $contact->tax_regime ?? '')
            : (string) ($record?->customer_tax_regime_code ?? '');

        $cfdiUseCode = $contact
            ? (string) (($contact->customer_cfdi_use_code ?? '') ?: ($contact->sat_cfdi_use_code ?? '') ?: ($contact->cfdi_use_code ?? ''))
            : (string) ($record?->customer_cfdi_use_code ?? '');

        $email = $contact ? (string) ($contact->email ?? '') : '';
        $phone = $contact ? (string) (($contact->phone ?? '') ?: ($contact->mobile ?? '')) : '';
        $whatsapp = $contact
            ? static::contactWhatsappPhone($contact)
            : (string) ($record?->customer_whatsapp_phone ?? '');
        $address = $contact ? static::contactFiscalAddress($contact) : '';

        $taxRegimeLabel = static::taxRegimeLabel($taxRegimeCode);
        $cfdiUseLabel = static::cfdiUseLabel($cfdiUseCode);

        $value = fn ($v): string => e(trim((string) $v) !== '' ? trim((string) $v) : 'N/D');

        $html = '
            <div style="display:grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap:10px; font-size:13px;">
                <div><strong>Razón social</strong><br>'.$value($fiscalName).'</div>
                <div><strong>RFC</strong><br>'.$value($rfc).'</div>
                <div><strong>CP fiscal</strong><br>'.$value($postalCode).'</div>
                <div><strong>Contacto</strong><br>'.$value(trim($email . ($email && $phone ? " / " : "") . $phone)).'</div>
                <div><strong>WhatsApp facturación</strong><br>'.$value($whatsapp).'</div>
                <div style="grid-column: span 2;"><strong>Régimen fiscal</strong><br>'.$value($taxRegimeLabel).'</div>
                <div style="grid-column: span 2;"><strong>Uso CFDI</strong><br>'.$value($cfdiUseLabel).'</div>
                <div style="grid-column: span 4;"><strong>Dirección fiscal</strong><br>'.$value($address).'</div>
            </div>
        ';

        return new \Illuminate\Support\HtmlString($html);
    }

    public static function contactFiscalAddress(object $contact): string
    {
        $directFields = [
            'fiscal_address',
            'billing_address',
            'address',
            'full_address',
        ];

        foreach ($directFields as $field) {
            if (isset($contact->{$field}) && trim((string) $contact->{$field}) !== '') {
                return trim((string) $contact->{$field});
            }
        }

        $parts = [];

        foreach ([
            'street',
            'external_number',
            'internal_number',
            'neighborhood',
            'city',
            'municipality',
            'state',
            'country',
            'postal_code',
        ] as $field) {
            if (isset($contact->{$field}) && trim((string) $contact->{$field}) !== '') {
                $parts[] = trim((string) $contact->{$field});
            }
        }

        return implode(', ', array_unique($parts));
    }

    public static function taxRegimeLabel(?string $code): string
    {
        $code = trim((string) $code);

        if ($code === '') {
            return '';
        }

        return static::taxRegimeOptions()[$code]
            ?? static::satCatalogLabel($code, ['tax_regime', 'regimen_fiscal', 'sat_tax_regime', 'tax_regimes']);
    }

    public static function cfdiUseLabel(?string $code): string
    {
        $code = trim((string) $code);

        if ($code === '') {
            return '';
        }

        return static::cfdiUseOptions()[$code]
            ?? static::satCatalogLabel($code, ['cfdi_use', 'uso_cfdi', 'sat_cfdi_use', 'cfdi_uses']);
    }

    public static function satCatalogLabel(?string $code, array $catalogHints = []): string
    {
        $code = trim((string) $code);

        if ($code === '') {
            return '';
        }

        $tables = [
            'sat_billing_catalog_items',
            'sat_cfdi_uses',
            'sat_tax_regimes',
            'sat_regimen_fiscal',
            'sat_catalog_items',
        ];

        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $columns = Schema::getColumnListing($table);
            $codeColumns = array_values(array_intersect($columns, ['code', 'key', 'sat_code', 'value']));
            $labelColumns = array_values(array_intersect($columns, ['name', 'label', 'description', 'text']));

            if (! $codeColumns || ! $labelColumns) {
                continue;
            }

            foreach ($codeColumns as $codeColumn) {
                $query = DB::table($table)->where($codeColumn, $code);

                $catalogColumns = array_values(array_intersect($columns, ['catalog', 'catalog_code', 'catalog_type', 'type', 'group', 'category']));

                if ($catalogColumns && $catalogHints) {
                    $query->where(function ($q) use ($catalogColumns, $catalogHints) {
                        foreach ($catalogColumns as $catalogColumn) {
                            foreach ($catalogHints as $hint) {
                                $q->orWhereRaw("LOWER(COALESCE({$catalogColumn}, '')) LIKE ?", ['%' . strtolower($hint) . '%']);
                            }
                        }
                    });
                }

                $row = $query->first();

                if (! $row && $catalogColumns) {
                    $row = DB::table($table)->where($codeColumn, $code)->first();
                }

                if (! $row) {
                    continue;
                }

                foreach ($labelColumns as $labelColumn) {
                    $label = trim((string) ($row->{$labelColumn} ?? ''));

                    if ($label !== '') {
                        return $label;
                    }
                }
            }
        }

        return $code;
    }



    public static function taxRegimeOptions(): array
    {
        if (\Illuminate\Support\Facades\Schema::hasTable('sat_tax_regimes')) {
            $rows = \Illuminate\Support\Facades\DB::table('sat_tax_regimes')
                ->where('active', true)
                ->orderBy('code')
                ->get();

            if ($rows->count() > 0) {
                return $rows
                    ->mapWithKeys(fn ($row): array => [
                        (string) $row->code => (string) $row->code . ' - ' . (string) $row->name,
                    ])
                    ->all();
            }
        }

        return [
            '601' => '601 - General de Ley Personas Morales',
            '603' => '603 - Personas Morales con Fines no Lucrativos',
            '605' => '605 - Sueldos y Salarios e Ingresos Asimilados a Salarios',
            '606' => '606 - Arrendamiento',
            '607' => '607 - Régimen de Enajenación o Adquisición de Bienes',
            '608' => '608 - Demás ingresos',
            '610' => '610 - Residentes en el Extranjero sin Establecimiento Permanente en México',
            '611' => '611 - Ingresos por Dividendos',
            '612' => '612 - Personas Físicas con Actividades Empresariales y Profesionales',
            '614' => '614 - Ingresos por intereses',
            '615' => '615 - Régimen de los ingresos por obtención de premios',
            '616' => '616 - Sin obligaciones fiscales',
            '620' => '620 - Sociedades Cooperativas de Producción que optan por diferir sus ingresos',
            '621' => '621 - Incorporación Fiscal',
            '622' => '622 - Actividades Agrícolas, Ganaderas, Silvícolas y Pesqueras',
            '623' => '623 - Opcional para Grupos de Sociedades',
            '624' => '624 - Coordinados',
            '625' => '625 - Actividades Empresariales con ingresos a través de Plataformas Tecnológicas',
            '626' => '626 - Régimen Simplificado de Confianza',
        ];
    }

    public static function cfdiUseOptions(): array
    {
        if (\Illuminate\Support\Facades\Schema::hasTable('sat_cfdi_uses')) {
            $rows = \Illuminate\Support\Facades\DB::table('sat_cfdi_uses')
                ->where('active', true)
                ->orderBy('code')
                ->get();

            if ($rows->count() > 0) {
                return $rows
                    ->mapWithKeys(fn ($row): array => [
                        (string) $row->code => (string) $row->code . ' - ' . (string) $row->name,
                    ])
                    ->all();
            }
        }

        return [
            'G01' => 'G01 - Adquisición de mercancías',
            'G02' => 'G02 - Devoluciones, descuentos o bonificaciones',
            'G03' => 'G03 - Gastos en general',
            'I01' => 'I01 - Construcciones',
            'I02' => 'I02 - Mobiliario y equipo de oficina por inversiones',
            'I03' => 'I03 - Equipo de transporte',
            'I04' => 'I04 - Equipo de cómputo y accesorios',
            'I05' => 'I05 - Dados, troqueles, moldes, matrices y herramental',
            'I06' => 'I06 - Comunicaciones telefónicas',
            'I07' => 'I07 - Comunicaciones satelitales',
            'I08' => 'I08 - Otra maquinaria y equipo',
            'D01' => 'D01 - Honorarios médicos, dentales y gastos hospitalarios',
            'D02' => 'D02 - Gastos médicos por incapacidad o discapacidad',
            'D03' => 'D03 - Gastos funerales',
            'D04' => 'D04 - Donativos',
            'D05' => 'D05 - Intereses reales efectivamente pagados por créditos hipotecarios',
            'D06' => 'D06 - Aportaciones voluntarias al SAR',
            'D07' => 'D07 - Primas por seguros de gastos médicos',
            'D08' => 'D08 - Gastos de transportación escolar obligatoria',
            'D09' => 'D09 - Depósitos en cuentas para el ahorro / planes de pensiones',
            'D10' => 'D10 - Pagos por servicios educativos',
            'S01' => 'S01 - Sin efectos fiscales',
            'CP01' => 'CP01 - Pagos',
            'CN01' => 'CN01 - Nómina',
        ];
    }

    public static function cfdiUsesByRegime(): array
    {
        if (\Illuminate\Support\Facades\Schema::hasTable('sat_cfdi_use_tax_regime')) {
            $rows = \Illuminate\Support\Facades\DB::table('sat_cfdi_use_tax_regime')
                ->where('active', true)
                ->orderBy('tax_regime_code')
                ->orderBy('cfdi_use_code')
                ->get();

            if ($rows->count() > 0) {
                $map = [];

                foreach ($rows as $row) {
                    $map[(string) $row->tax_regime_code][] = (string) $row->cfdi_use_code;
                }

                foreach ($map as $regime => $uses) {
                    $map[$regime] = array_values(array_unique($uses));
                }

                return $map;
            }
        }

        $g = ['G01', 'G02', 'G03'];
        $i = ['I01', 'I02', 'I03', 'I04', 'I05', 'I06', 'I07', 'I08'];
        $d = ['D01', 'D02', 'D03', 'D04', 'D05', 'D06', 'D07', 'D08', 'D09', 'D10'];
        $base = ['S01', 'CP01'];

        return [
            '601' => array_merge($g, $i, $base),
            '603' => array_merge($g, $i, $base),
            '605' => array_merge($d, $base, ['CN01']),
            '606' => array_merge($g, $i, $d, $base),
            '607' => array_merge($d, $base),
            '608' => array_merge($d, $base),
            '610' => $base,
            '611' => array_merge($d, $base),
            '612' => array_merge($g, $i, $d, $base),
            '614' => array_merge($d, $base),
            '615' => array_merge($d, $base),
            '616' => $base,
            '620' => array_merge($g, $i, $base),
            '621' => array_merge($g, $i, $base),
            '622' => array_merge($g, $i, $base),
            '623' => array_merge($g, $i, $base),
            '624' => array_merge($g, $i, $base),
            '625' => array_merge($g, $i, $d, $base),
            '626' => array_merge($g, $i, $base),
        ];
    }

    public static function cfdiUseOptionsForRegime(?string $regimeCode): array
    {
        $regimeCode = trim((string) $regimeCode);
        $all = static::cfdiUseOptions();

        if ($regimeCode === '') {
            return $all;
        }

        $allowed = static::cfdiUsesByRegime()[$regimeCode] ?? array_keys($all);

        return collect($allowed)
            ->filter(fn (string $code): bool => isset($all[$code]))
            ->mapWithKeys(fn (string $code): array => [$code => $all[$code]])
            ->all();
    }

    public static function validCfdiUseForRegime(?string $regimeCode, ?string $preferredCode = null): string
    {
        $options = static::cfdiUseOptionsForRegime($regimeCode);
        $preferredCode = trim((string) $preferredCode);

        if ($preferredCode !== '' && array_key_exists($preferredCode, $options)) {
            return $preferredCode;
        }

        if (array_key_exists('S01', $options)) {
            return 'S01';
        }

        $first = array_key_first($options);

        return $first ? (string) $first : '';
    }



    public static function canChangeCustomer(?Invoice $record): bool
    {
        if (! $record) {
            return true;
        }

        return (string) ($record->status ?? 'draft') === 'draft';
    }



    public static function cfdiStatusLabel(?string $status): string
    {
        return match ((string) $status) {
            'pending', '' => 'Pendiente',
            'validation_error' => 'Error de validación',
            'ready_to_stamp' => 'Listo para timbrar',
            'stamping' => 'Timbrando',
            'stamped' => 'Timbrado',
            'stamp_error' => 'Error de timbrado',
            'cancel_pending' => 'Cancelación pendiente',
            'cancelled' => 'Cancelado SAT',
            'cancel_error' => 'Error de cancelación',
            default => (string) $status,
        };
    }

    public static function cfdiStatusColor(?string $status): string
    {
        return match ((string) $status) {
            'ready_to_stamp' => 'info',
            'stamped' => 'success',
            'validation_error', 'stamp_error', 'cancel_error' => 'danger',
            'cancel_pending', 'stamping' => 'warning',
            'cancelled' => 'gray',
            default => 'gray',
        };
    }



    public static function contactWhatsappPhone(?object $contact): string
    {
        if (! $contact) {
            return '';
        }

        foreach (['mobile', 'phone_mobile', 'whatsapp_phone', 'phone', 'telephone'] as $field) {
            $value = trim((string) ($contact->{$field} ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

}
