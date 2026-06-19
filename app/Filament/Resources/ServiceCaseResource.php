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

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('company_id')
                    ->default(fn (): ?int => ServiceAccess::currentCompanyId()),

                Forms\Components\Section::make('Datos generales')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('folio')
                            ->label('Folio')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->options(ServiceCase::STATUSES)
                            ->required()
                            ->default('nuevo'),

                        Forms\Components\Select::make('priority')
                            ->label('Prioridad')
                            ->options(ServiceCase::PRIORITIES)
                            ->required()
                            ->default('media'),

                        Forms\Components\Select::make('channel')
                            ->label('Canal')
                            ->options(ServiceCase::CHANNELS)
                            ->required()
                            ->default('manual'),

                        Forms\Components\Select::make('case_type')
                            ->label('Tipo de caso')
                            ->options(ServiceCase::CASE_TYPES)
                            ->required()
                            ->default('general'),

                        Forms\Components\TextInput::make('assigned_team')
                            ->label('Equipo asignado')
                            ->maxLength(255),

                        Forms\Components\Select::make('assigned_employee_id')
                            ->label('Tecnico responsable')
                            ->helperText('Solo empleados del mismo grupo de empresas marcados como tecnico de servicio.')
                            ->searchable()
                            ->preload()
                            ->options(fn (): array => ServiceAccess::technicianEmployeeOptions())
                            ->getSearchResultsUsing(fn (string $search): array => ServiceAccess::technicianEmployeeOptions($search))
                            ->getOptionLabelUsing(fn ($value): ?string => ServiceAccess::employeeLabel((int) $value)),

                        Forms\Components\DateTimePicker::make('due_at')
                            ->label('Fecha compromiso'),
                    ]),

                Forms\Components\Section::make('Cliente / contacto')
                    ->columns(3)
                    ->schema([
                        Forms\Components\Select::make('customer_id')
                            ->label('Cliente')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->options(fn (): array => ServiceAccess::contactOptions())
                            ->getSearchResultsUsing(fn (string $search): array => ServiceAccess::contactOptions($search))
                            ->getOptionLabelUsing(fn ($value): ?string => ServiceAccess::contactLabel((int) $value))
                            ->afterStateUpdated(function ($state, Set $set): void {
                                $details = ServiceAccess::contactDetails((int) $state);

                                $set('contact_name', $details['contact_name'] ?? null);
                                $set('contact_email', $details['contact_email'] ?? null);
                                $set('contact_phone', $details['contact_phone'] ?? null);
                            }),

                        Forms\Components\TextInput::make('contact_name')
                            ->label('Contacto')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('contact_phone')
                            ->label('Telefono')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('contact_email')
                            ->label('Correo')
                            ->email()
                            ->maxLength(255),
                    ]),

                Forms\Components\Section::make('Caso')
                    ->schema([
                        Forms\Components\TextInput::make('subject')
                            ->label('Asunto')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Textarea::make('description')
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
                    ->description('Opcional. Usa catálogo si existe; si no, captura libremente producto, serie, lote, venta o factura.')
                    ->columns(12)
                    ->schema([
                        Forms\Components\Select::make('product_id')
                            ->label('Producto catálogo')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->options(fn (): array => ServiceAccess::productOptions())
                            ->getSearchResultsUsing(fn (string $search): array => ServiceAccess::productOptions($search))
                            ->getOptionLabelUsing(fn ($value): ?string => ServiceAccess::productLabel((int) $value))
                            ->afterStateUpdated(function ($state, Set $set): void {
                                $set('product_name', ServiceAccess::productLabel((int) $state));
                            })
                            ->columnSpan(6),

                        Forms\Components\TextInput::make('product_name')
                            ->label('Producto / modelo libre')
                            ->helperText('Captura manual cuando el producto aún no exista en catálogo.')
                            ->maxLength(255)
                            ->columnSpan(6),

                        Forms\Components\TextInput::make('serial_number')
                            ->label('Número de serie libre')
                            ->helperText('Captura libre mientras se cargan las series reales.')
                            ->maxLength(255)
                            ->columnSpan(4),

                        Forms\Components\TextInput::make('lot_number')
                            ->label('Lote libre')
                            ->helperText('Captura libre mientras se cargan lotes reales.')
                            ->maxLength(255)
                            ->columnSpan(4),

                        Forms\Components\TextInput::make('sale_reference')
                            ->label('Venta / documento libre')
                            ->helperText('Folio, pedido, nota o referencia manual.')
                            ->maxLength(255)
                            ->columnSpan(4),

                        Forms\Components\Select::make('sale_id')
                            ->label('Venta relacionada')
                            ->searchable()
                            ->preload()
                            ->options(fn (): array => ServiceAccess::saleOrderOptions())
                            ->getSearchResultsUsing(fn (string $search): array => ServiceAccess::saleOrderOptions($search))
                            ->getOptionLabelUsing(fn ($value): ?string => ServiceAccess::saleOrderLabel((int) $value))
                            ->columnSpan(6),

                        Forms\Components\Select::make('invoice_id')
                            ->label('Factura relacionada')
                            ->searchable()
                            ->preload()
                            ->options(fn (): array => ServiceAccess::invoiceOptions())
                            ->getSearchResultsUsing(fn (string $search): array => ServiceAccess::invoiceOptions($search))
                            ->getOptionLabelUsing(fn ($value): ?string => ServiceAccess::invoiceLabel((int) $value))
                            ->columnSpan(3),

                        Forms\Components\TextInput::make('invoice_reference')
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
                    ->label('Folio')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('subject')
                    ->label('Asunto')
                    ->searchable()
                    ->limit(45),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('priority')
                    ->label('Prioridad')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('case_type')
                    ->label('Tipo')
                    ->sortable(),

                Tables\Columns\TextColumn::make('product_name')
                    ->label('Producto')
                    ->searchable()
                    ->limit(35)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('serial_number')
                    ->label('Serie')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('invoice_id')
                    ->label('Factura')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('channel')
                    ->label('Canal')
                    ->sortable(),

                Tables\Columns\TextColumn::make('assigned_team')
                    ->label('Equipo')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('assigned_employee_id')
                    ->label('Tecnico')
                    ->formatStateUsing(fn ($state): ?string => filled($state) ? ServiceAccess::employeeLabel((int) $state) : null)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('company_id')
                    ->label('Empresa')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('due_at')
                    ->label('Compromiso')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options(ServiceCase::STATUSES),

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
