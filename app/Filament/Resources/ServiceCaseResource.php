<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceCaseResource\Pages;
use App\Models\ServiceCase;
use App\Models\ServiceCaseEvent;
use App\Support\Service\ServiceAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ServiceCaseResource extends Resource
{
    protected static ?string $model = ServiceCase::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'Atencion y Servicio';

    protected static ?string $navigationLabel = 'Tickets de servicio';

    protected static ?string $modelLabel = 'ticket de servicio';

    protected static ?string $pluralModelLabel = 'tickets de servicio';

    protected static ?int $navigationSort = 10;

    protected static ?string $tenantOwnershipRelationshipName = null;

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        return ServiceAccess::can([
            'service.menu.view',
            'service.cases.view',
            'service.cases.create',
            'service.cases.update',
        ]);
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return ServiceAccess::can('service.cases.create');
    }

    public static function canEdit(Model $record): bool
    {
        if (in_array((string) ($record->status ?? ''), ['entregado', 'cerrado', 'rechazado', 'cancelado'], true)) {
            if ((string) ($record->attention_route ?? '') === 'non_repair') {
                return ServiceAccess::can('service.cases.update');
            }

            return ServiceAccess::can('service.repairs.reopen');
        }

        return ServiceAccess::can('service.cases.update');
    }

    public static function canDelete(Model $record): bool
    {
        return ServiceAccess::can('service.cases.delete');
    }

    public static function canDeleteAny(): bool
    {
        return ServiceAccess::can('service.cases.delete');
    }

    public static function getEloquentQuery(): Builder
    {
        $model = static::getModel();

        $query = $model::query();

        $companyId = ServiceAccess::currentCompanyId();

        if ($companyId && ServiceAccess::tableHasCompany('service_cases')) {
            $query->where('company_id', $companyId);
        }

        return $query;
    }


    /*
     * BEXIA_SVC_RESOURCE_RESPONSIVE_V5_79_48C
     * Visual-only responsive marker.
     */

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('company_id')
                    ->default(fn (): ?int => ServiceAccess::currentCompanyId()),

                Forms\Components\Section::make('Clasificación de atención')
                    ->extraAttributes(['class' => 'bexia-svc-section bexia-svc-section-classification'])
                    ->description('La ruta la determina el Encargado de Técnicos o el Supervisor.')
                    ->columns(4)
                    ->schema([
                        Forms\Components\Placeholder::make('attention_route_display')
                            ->label('Ruta')
                            ->content(fn ($record): string => $record
                                ? (ServiceCase::ATTENTION_ROUTES[(string) ($record->attention_route ?? '')] ?? 'Pendiente')
                                : 'Pendiente'),

                        Forms\Components\Placeholder::make('non_repair_type_display')
                            ->label('Tipo sin reparación')
                            ->content(fn ($record): string => $record
                                ? (ServiceCase::NON_REPAIR_TYPES[(string) ($record->non_repair_type ?? '')] ?? 'No aplica')
                                : 'No aplica'),

                        Forms\Components\Placeholder::make('classified_at_display')
                            ->label('Clasificado')
                            ->content(fn ($record): string => filled($record?->classified_at)
                                ? (string) $record->classified_at
                                : 'Pendiente'),

                        Forms\Components\Placeholder::make('repair_order_display')
                            ->label('Orden vinculada')
                            ->content(function ($record): string {
                                if (! $record) {
                                    return 'No aplica';
                                }

                                $repair = $record->repairOrders()
                                    ->orderByDesc('id')
                                    ->first();

                                return $repair?->folio ?: 'No aplica';
                            }),

                        Forms\Components\Placeholder::make('assigned_employee_display')
                            ->label('Responsable')
                            ->content(fn ($record): string => filled($record?->assigned_employee_id)
                                ? (ServiceAccess::employeeLabel((int) $record->assigned_employee_id) ?? 'Sin asignar')
                                : 'Sin asignar'),

                        Forms\Components\Placeholder::make('due_at_display')
                            ->label('Fecha compromiso')
                            ->content(fn ($record): string => filled($record?->due_at)
                                ? (string) $record->due_at
                                : 'Sin fecha'),

                        Forms\Components\Placeholder::make('classification_notes_display')
                            ->label('Notas')
                            ->content(fn ($record): string => filled($record?->classification_notes)
                                ? (string) $record->classification_notes
                                : 'Sin notas')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record): bool => (bool) $record && filled($record->attention_route))
                    ->collapsible(),

                Forms\Components\Section::make('Atención directa')
                    ->extraAttributes([
                        'class' =>
                            'bexia-svc-section bexia-svc-section-direct-attention',
                    ])
                    ->description(
                        'Seguimiento y resolución del ticket que no requiere reparación.'
                    )
                    ->columns(4)
                    ->schema([
                        Forms\Components\Placeholder::make(
                            'direct_attention_type_display'
                        )
                            ->label('Tipo de atención')
                            ->content(fn ($record): string =>
                                ServiceCase::NON_REPAIR_TYPES[
                                    (string) (
                                        $record?->non_repair_type
                                        ?? ''
                                    )
                                ]
                                ?? 'Sin definir'
                            ),

                        Forms\Components\Placeholder::make(
                            'first_response_display'
                        )
                            ->label('Primera respuesta')
                            ->content(fn ($record): string =>
                                filled(
                                    $record?->first_response_at
                                )
                                    ? (string)
                                        $record
                                            ->first_response_at
                                    : 'Pendiente'
                            ),

                        Forms\Components\Placeholder::make(
                            'resolution_type_display'
                        )
                            ->label('Resolución')
                            ->content(fn ($record): string =>
                                \App\Support\Service\ServiceCaseDirectAttentionService::resolutionTypeLabel(
                                    $record?->resolution_type
                                )
                            ),

                        Forms\Components\Placeholder::make(
                            'direct_closed_at_display'
                        )
                            ->label('Cerrado')
                            ->content(fn ($record): string =>
                                filled($record?->closed_at)
                                    ? (string)
                                        $record->closed_at
                                    : 'Abierto'
                            ),

                        Forms\Components\Placeholder::make(
                            'resolution_notes_display'
                        )
                            ->label('Solución proporcionada')
                            ->content(fn ($record): string =>
                                filled(
                                    $record?->resolution_notes
                                )
                                    ? (string)
                                        $record
                                            ->resolution_notes
                                    : 'Pendiente'
                            )
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record): bool =>
                        (bool) $record
                        && (string) (
                            $record->attention_route
                            ?? ''
                        ) === 'non_repair'
                    )
                    ->collapsible(),
                Forms\Components\Section::make('Datos generales')
                    ->extraAttributes(['class' => 'bexia-svc-section bexia-svc-section-general'])
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('folio')
                            ->extraAttributes(['class' => 'bexia-svc-field bexia-svc-field-folio'])
                            ->label('Folio')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\Select::make('status')
                            ->extraAttributes(['class' => 'bexia-svc-field bexia-svc-field-status'])
                            ->label('Estado')
                            ->options(ServiceCase::STATUSES)
                            ->required()
                            ->default('nuevo'),

                        Forms\Components\Select::make('priority')
                            ->extraAttributes(['class' => 'bexia-svc-field bexia-svc-field-priority'])
                            ->label('Prioridad')
                            ->options(ServiceCase::PRIORITIES)
                            ->required()
                            ->default('media'),

                        Forms\Components\Select::make('channel')
                            ->extraAttributes(['class' => 'bexia-svc-field bexia-svc-field-channel'])
                            ->label('Canal')
                            ->options(ServiceCase::CHANNELS)
                            ->required()
                            ->default('manual'),

                        Forms\Components\Select::make('case_type')
                            ->extraAttributes(['class' => 'bexia-svc-field bexia-svc-field-case-type'])
                            ->label('Tipo de caso')
                            ->options(ServiceCase::CASE_TYPES)
                            ->required()
                            ->default('general'),

                        Forms\Components\Hidden::make('assigned_team')
                            ->dehydrated(false),

                    ]),

                Forms\Components\Section::make('Cliente / contacto')
                    ->extraAttributes(['class' => 'bexia-svc-section bexia-svc-section-contact'])
                    ->columns(3)
                    ->schema([
                        Forms\Components\Select::make('customer_id')
                            ->extraAttributes(['class' => 'bexia-svc-field bexia-svc-field-customer'])
                            ->extraFieldWrapperAttributes([
                                'class' => 'bexia-svc-dropdown-overlay-host bexia-svc-customer-overlay-host',
                                'style' => 'position: relative; overflow: visible; z-index: 40;',
                            ])
                            ->helperText(new \Illuminate\Support\HtmlString(<<<'HTML'
<style id="bexia-svc-dropdown-overlay-style">
.bexia-svc-dropdown-overlay-host,
.bexia-svc-dropdown-overlay-host .fi-fo-field-wrp,
.bexia-svc-dropdown-overlay-host .fi-input-wrp,
.fi-section:has(.bexia-svc-dropdown-overlay-host),
.fi-section:has(.bexia-svc-dropdown-overlay-host) .fi-section-content-ctn,
.fi-section:has(.bexia-svc-dropdown-overlay-host) .fi-section-content {
    overflow: visible !important;
}
.bexia-svc-dropdown-overlay-host:focus-within {
    z-index: 9999 !important;
}
.bexia-svc-dropdown-overlay-host .choices {
    position: relative !important;
    overflow: visible !important;
}
.bexia-svc-dropdown-overlay-host .choices__list--dropdown {
    position: absolute !important;
    inset-inline: 0 !important;
    top: calc(100% + 0.25rem) !important;
    width: 100% !important;
    min-width: 100% !important;
    height: auto !important;
    max-height: none !important;
    overflow: visible !important;
    z-index: 9999 !important;
}
.bexia-svc-dropdown-overlay-host .choices__list--dropdown .choices__input {
    position: static !important;
}
.bexia-svc-dropdown-overlay-host .choices__list--dropdown .choices__list[role="listbox"] {
    position: static !important;
    inset: auto !important;
    top: auto !important;
    width: auto !important;
    min-width: 0 !important;
    max-height: 18rem !important;
    overflow-y: auto !important;
    z-index: auto !important;
}
</style>
HTML))
                            ->label('Cliente')
                            ->options(ServiceAccess::contactOptions())
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set): void {
                                $details = ServiceAccess::contactDetails((int) $state);

                                $set('contact_name', $details['contact_name'] ?? null);
                                $set('contact_email', $details['contact_email'] ?? null);
                                $set('contact_phone', $details['contact_phone'] ?? null);
                            }),

                        Forms\Components\TextInput::make('contact_name')
                            ->extraAttributes(['class' => 'bexia-svc-field bexia-svc-field-contact-name'])
                            ->label('Contacto')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('contact_phone')
                            ->extraAttributes(['class' => 'bexia-svc-field bexia-svc-field-phone'])
                            ->label('Telefono')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('contact_email')
                            ->extraAttributes(['class' => 'bexia-svc-field bexia-svc-field-email'])
                            ->label('Correo')
                            ->email()
                            ->maxLength(255),
                    ]),

                Forms\Components\Section::make('Caso')
                    ->extraAttributes(['class' => 'bexia-svc-section bexia-svc-section-case'])
                    ->schema([
                        Forms\Components\TextInput::make('subject')
                            ->extraAttributes(['class' => 'bexia-svc-field bexia-svc-field-subject'])
                            ->label('Asunto')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Textarea::make('description')
                            ->extraAttributes(['class' => 'bexia-svc-field bexia-svc-field-description'])
                            ->label('Descripcion')
                            ->rows(5)
                            ->columnSpanFull(),

                        Forms\Components\FileUpload::make('uploaded_attachments')
                            ->label('Fotos y archivos')
                            ->helperText('Agrega fotos del producto, evidencia, PDF, documentos o archivos relacionados al ticket.')
                            ->multiple()
                            ->disk('public')
                            ->directory('service-attachments/tickets')
                            ->downloadable()
                            ->openable()
                            ->previewable()
                            ->maxSize(20480)
                            ->acceptedFileTypes([
                                'image/*',
                                'application/pdf',
                                'text/plain',
                                'application/zip',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            ])
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Producto / documento relacionado')
                    ->extraAttributes(['class' => 'bexia-svc-section bexia-svc-section-product'])
                    ->description('Opcional. Usa catálogo si existe; si no, captura libremente producto, serie, lote, venta o factura.')
                    ->columns(12)
                    ->schema([
                        Forms\Components\Select::make('product_id')
                            ->extraAttributes(['class' => 'bexia-svc-field bexia-svc-field-product'])
                            ->extraFieldWrapperAttributes([
                                'class' => 'bexia-svc-dropdown-overlay-host bexia-svc-product-overlay-host',
                                'style' => 'position: relative; overflow: visible; z-index: 40;',
                            ])
                            ->label('Producto catálogo')
                            ->searchable()
                            ->native(false)
                            ->live()
                            ->searchPrompt('Busca por SKU, nombre o descripción')
                            ->searchingMessage('Buscando productos...')
                            ->noSearchResultsMessage('No se encontraron productos')
                            ->searchDebounce(400)
                            ->optionsLimit(50)
                            ->getSearchResultsUsing(fn (string $search): array => ServiceAccess::productOptions($search))
                            ->getOptionLabelUsing(fn ($value): ?string => ServiceAccess::productLabel((int) $value))
                            ->afterStateUpdated(function ($state, Set $set): void {
                                $set('product_name', ServiceAccess::productLabel((int) $state));
                            })
                            ->columnSpan(6),

                        Forms\Components\TextInput::make('product_name')
                            ->extraAttributes(['class' => 'bexia-svc-field bexia-svc-field-product-name'])
                            ->label('Producto / modelo libre')
                            ->helperText('Captura manual cuando el producto aún no exista en catálogo.')
                            ->maxLength(255)
                            ->columnSpan(6),

                        Forms\Components\TextInput::make('serial_number')
                            ->extraAttributes(['class' => 'bexia-svc-field bexia-svc-field-serial'])
                            ->label('Número de serie libre')
                            ->helperText('Captura libre mientras se cargan las series reales.')
                            ->maxLength(255)
                            ->columnSpan(4),

                        Forms\Components\TextInput::make('lot_number')
                            ->extraAttributes(['class' => 'bexia-svc-field bexia-svc-field-lot'])
                            ->label('Lote libre')
                            ->helperText('Captura libre mientras se cargan lotes reales.')
                            ->maxLength(255)
                            ->columnSpan(4),

                        Forms\Components\TextInput::make('sale_reference')
                            ->extraAttributes(['class' => 'bexia-svc-field bexia-svc-field-sale-reference'])
                            ->label('Venta / documento libre')
                            ->helperText('Folio, pedido, nota o referencia manual.')
                            ->maxLength(255)
                            ->columnSpan(4),

                        Forms\Components\Select::make('sale_id')
                            ->extraAttributes(['class' => 'bexia-svc-field bexia-svc-field-sale'])
                            ->label('Venta relacionada')
                            ->searchable()
                            ->preload()
                            ->options(fn (): array => ServiceAccess::saleOrderOptions())
                            ->getSearchResultsUsing(fn (string $search): array => ServiceAccess::saleOrderOptions($search))
                            ->getOptionLabelUsing(fn ($value): ?string => ServiceAccess::saleOrderLabel((int) $value))
                            ->columnSpan(6),

                        Forms\Components\Select::make('invoice_id')
                            ->extraAttributes(['class' => 'bexia-svc-field bexia-svc-field-invoice'])
                            ->label('Factura relacionada')
                            ->searchable()
                            ->preload()
                            ->options(fn (): array => ServiceAccess::invoiceOptions())
                            ->getSearchResultsUsing(fn (string $search): array => ServiceAccess::invoiceOptions($search))
                            ->getOptionLabelUsing(fn ($value): ?string => ServiceAccess::invoiceLabel((int) $value))
                            ->columnSpan(3),

                        Forms\Components\TextInput::make('invoice_reference')
                            ->extraAttributes(['class' => 'bexia-svc-field bexia-svc-field-invoice-reference'])
                            ->label('Factura / folio libre')
                            ->helperText('UUID, folio fiscal, serie-folio o referencia manual.')
                            ->maxLength(255)
                            ->columnSpan(3),
                    ]),

                
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('folio')
                    ->extraHeaderAttributes(['class' => 'bexia-svc-col-folio bexia-svc-col-primary'])
                    ->extraCellAttributes(['class' => 'bexia-svc-col-folio bexia-svc-col-primary'])
                    ->label('Folio')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('subject')
                    ->extraHeaderAttributes(['class' => 'bexia-svc-col-subject bexia-svc-col-wrap'])
                    ->extraCellAttributes(['class' => 'bexia-svc-col-subject bexia-svc-col-wrap'])
                    ->label('Asunto')
                    ->searchable()
                    ->limit(45),

                Tables\Columns\TextColumn::make('status')
                    ->extraHeaderAttributes(['class' => 'bexia-svc-col-status'])
                    ->extraCellAttributes(['class' => 'bexia-svc-col-status'])
                    ->label('Estado')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('attention_route')
                    ->extraHeaderAttributes(['class' => 'bexia-svc-col-route'])
                    ->extraCellAttributes(['class' => 'bexia-svc-col-route'])
                    ->label('Ruta')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'repair' => 'Reparación',
                        'non_repair' => 'Sin reparación',
                        default => 'Pendiente',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('priority')
                    ->extraHeaderAttributes(['class' => 'bexia-svc-col-priority'])
                    ->extraCellAttributes(['class' => 'bexia-svc-col-priority'])
                    ->label('Prioridad')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('case_type')
                    ->extraHeaderAttributes(['class' => 'bexia-svc-col-case-type'])
                    ->extraCellAttributes(['class' => 'bexia-svc-col-case-type'])
                    ->label('Tipo')
                    ->sortable(),

                Tables\Columns\TextColumn::make('product_name')
                    ->extraHeaderAttributes(['class' => 'bexia-svc-col-product bexia-svc-col-wrap'])
                    ->extraCellAttributes(['class' => 'bexia-svc-col-product bexia-svc-col-wrap'])
                    ->label('Producto')
                    ->searchable()
                    ->limit(35)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('serial_number')
                    ->extraHeaderAttributes(['class' => 'bexia-svc-col-serial bexia-svc-col-wrap'])
                    ->extraCellAttributes(['class' => 'bexia-svc-col-serial bexia-svc-col-wrap'])
                    ->label('Serie')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('invoice_id')
                    ->extraHeaderAttributes(['class' => 'bexia-svc-col-invoice'])
                    ->extraCellAttributes(['class' => 'bexia-svc-col-invoice'])
                    ->label('Factura')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('channel')
                    ->extraHeaderAttributes(['class' => 'bexia-svc-col-channel'])
                    ->extraCellAttributes(['class' => 'bexia-svc-col-channel'])
                    ->label('Canal')
                    ->sortable(),

                Tables\Columns\TextColumn::make('assigned_team')
                    ->extraHeaderAttributes(['class' => 'bexia-svc-col-team bexia-svc-col-wrap'])
                    ->extraCellAttributes(['class' => 'bexia-svc-col-team bexia-svc-col-wrap'])
                    ->label('Equipo')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('assigned_employee_id')
                    ->extraHeaderAttributes(['class' => 'bexia-svc-col-technician'])
                    ->extraCellAttributes(['class' => 'bexia-svc-col-technician'])
                    ->label('Tecnico')
                    ->formatStateUsing(fn ($state): ?string => filled($state) ? ServiceAccess::employeeLabel((int) $state) : null)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('company_id')
                    ->extraHeaderAttributes(['class' => 'bexia-svc-col-company'])
                    ->extraCellAttributes(['class' => 'bexia-svc-col-company'])
                    ->label('Empresa')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('due_at')
                    ->extraHeaderAttributes(['class' => 'bexia-svc-col-due-at'])
                    ->extraCellAttributes(['class' => 'bexia-svc-col-due-at'])
                    ->label('Compromiso')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->extraHeaderAttributes(['class' => 'bexia-svc-col-created-at'])
                    ->extraCellAttributes(['class' => 'bexia-svc-col-created-at'])
                    ->label('Creado')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options(ServiceCase::STATUSES),

                Tables\Filters\SelectFilter::make('attention_route')
                    ->label('Ruta de atención')
                    ->options(ServiceCase::ATTENTION_ROUTES),

                Tables\Filters\SelectFilter::make('priority')
                    ->label('Prioridad')
                    ->options(ServiceCase::PRIORITIES),

                Tables\Filters\SelectFilter::make('case_type')
                    ->label('Tipo')
                    ->options(ServiceCase::CASE_TYPES),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (ServiceCase $record): bool => static::canEdit($record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => static::canDeleteAny()),
                ]),
            ]);
    }

    public static function logEvent(ServiceCase $record, string $eventType, ?string $fromStatus = null, ?string $toStatus = null, ?string $notes = null): void
    {
        ServiceCaseEvent::create([
            'company_id' => $record->company_id,
            'service_case_id' => $record->id,
            'event_type' => $eventType,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'performed_by' => auth()->id(),
            'performed_at' => now(),
            'notes' => $notes,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServiceCases::route('/'),
            'create' => Pages\CreateServiceCase::route('/create'),
            'edit' => Pages\EditServiceCase::route('/{record}/edit'),
        ];
    }
}
