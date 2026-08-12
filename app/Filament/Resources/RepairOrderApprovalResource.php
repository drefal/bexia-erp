<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RepairOrderApprovalResource\Pages;
use App\Models\RepairOrderApproval;
use App\Models\ServiceCaseEvent;
use App\Support\Service\ServiceAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RepairOrderApprovalResource extends Resource
{
    protected static ?string $model = RepairOrderApproval::class;

    protected static ?string $navigationIcon = 'heroicon-o-check-badge';

    protected static ?string $navigationGroup = 'Atencion y Servicio';

    protected static ?string $navigationLabel = 'Aprobaciones de servicio';

    protected static ?string $modelLabel = 'aprobacion de servicio';

    protected static ?string $pluralModelLabel = 'aprobaciones de servicio';

    protected static ?int $navigationSort = 80;

    protected static ?string $tenantOwnershipRelationshipName = null;

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        return ServiceAccess::can([
            'service.repairs.approve_warranty',
            'service.repairs.reject_warranty',
            'service.repairs.authorize_delivery',
            'service.repairs.reopen',
            'service.events.view',
        ]);
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return ServiceAccess::can([
            'service.repairs.approve_warranty',
            'service.repairs.reject_warranty',
            'service.repairs.authorize_delivery',
        ]);
    }

    public static function canEdit(Model $record): bool
    {
        return $record instanceof RepairOrderApproval
            && static::isPendingStatus($record)
            && ! static::hasLinkedApprovalRequest($record)
            && static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $model = static::getModel();

        $query = $model::query();

        $companyId = ServiceAccess::currentCompanyId();

        if ($companyId && ServiceAccess::tableHasCompany('repair_order_approvals')) {
            $query->where('company_id', $companyId);
        }

        return $query;
    }

    /*
     * BEXIA_REPAIR_ORDER_APPROVAL_RESOURCE_RESPONSIVE_V5_79_67C
     * Visual-only responsive marker.
     */


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('company_id')
                    ->default(fn (): ?int => ServiceAccess::currentCompanyId()),

                Forms\Components\Section::make('Solicitud')
                    ->extraAttributes(['class' => 'bexia-roa-section bexia-roa-section-main'])
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('service_case_id')
                            ->extraAttributes(['class' => 'bexia-roa-field bexia-roa-field-service-case bexia-roa-id-field'])
                            ->label('ID ticket')
                            ->numeric(),

                        Forms\Components\TextInput::make('repair_order_id')
                            ->extraAttributes(['class' => 'bexia-roa-field bexia-roa-field-repair-order bexia-roa-id-field'])
                            ->label('ID reparacion')
                            ->numeric(),

                        Forms\Components\Select::make('approval_type')
                            ->extraAttributes(['class' => 'bexia-roa-field bexia-roa-field-approval-type'])
                            ->label('Tipo de aprobacion')
                            ->options(RepairOrderApproval::TYPES)
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->extraAttributes(['class' => 'bexia-roa-field bexia-roa-field-status'])
                            ->label('Estado')
                            ->options(RepairOrderApproval::STATUSES)
                            ->required()
                            ->default('pendiente'),

                        Forms\Components\TextInput::make('amount')
                            ->extraAttributes(['class' => 'bexia-roa-field bexia-roa-field-amount bexia-roa-money-field'])
                            ->label('Importe')
                            ->numeric()
                            ->default(0),

                        Forms\Components\DateTimePicker::make('requested_at')
                            ->extraAttributes(['class' => 'bexia-roa-field bexia-roa-field-requested-at bexia-roa-date-field'])
                            ->label('Solicitado')
                            ->default(now()),

                        Forms\Components\Textarea::make('reason')
                            ->extraAttributes(['class' => 'bexia-roa-field bexia-roa-field-reason'])
                            ->label('Motivo')
                            ->required()
                            ->rows(4)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('comments')
                            ->extraAttributes(['class' => 'bexia-roa-field bexia-roa-field-comments'])
                            ->label('Comentarios resolucion')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->extraHeaderAttributes(['class' => 'bexia-roa-col-id bexia-roa-col-code bexia-roa-col-compact'])
                    ->extraCellAttributes(['class' => 'bexia-roa-col-id bexia-roa-col-code bexia-roa-col-compact'])
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('approval_type')
                    ->extraHeaderAttributes(['class' => 'bexia-roa-col-approval-type bexia-roa-col-context bexia-roa-col-badge'])
                    ->extraCellAttributes(['class' => 'bexia-roa-col-approval-type bexia-roa-col-context bexia-roa-col-badge'])
                    ->label('Tipo')
                    ->badge()
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->extraHeaderAttributes(['class' => 'bexia-roa-col-status bexia-roa-col-context bexia-roa-col-badge'])
                    ->extraCellAttributes(['class' => 'bexia-roa-col-status bexia-roa-col-context bexia-roa-col-badge'])
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'pending', 'pendiente' => 'Pendiente',
                        'approved', 'aprobado' => 'Aprobado',
                        'rejected', 'rechazado' => 'Rechazado',
                        'cancelled', 'canceled', 'cancelado' => 'Cancelado',
                        default => (string) $state,
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('repair_order_id')
                    ->extraHeaderAttributes(['class' => 'bexia-roa-col-repair-order bexia-roa-col-code bexia-roa-col-compact'])
                    ->extraCellAttributes(['class' => 'bexia-roa-col-repair-order bexia-roa-col-code bexia-roa-col-compact'])
                    ->label('Reparacion')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('service_case_id')
                    ->extraHeaderAttributes(['class' => 'bexia-roa-col-service-case bexia-roa-col-code bexia-roa-col-compact'])
                    ->extraCellAttributes(['class' => 'bexia-roa-col-service-case bexia-roa-col-code bexia-roa-col-compact'])
                    ->label('Ticket')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('amount')
                    ->extraHeaderAttributes(['class' => 'bexia-roa-col-amount bexia-roa-col-money bexia-roa-col-compact'])
                    ->extraCellAttributes(['class' => 'bexia-roa-col-amount bexia-roa-col-money bexia-roa-col-compact'])
                    ->label('Importe')
                    ->money('MXN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('requested_by')
                    ->extraHeaderAttributes(['class' => 'bexia-roa-col-requested-by bexia-roa-col-person bexia-roa-col-context'])
                    ->extraCellAttributes(['class' => 'bexia-roa-col-requested-by bexia-roa-col-person bexia-roa-col-context'])
                    ->label('Solicito')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('requested_at')
                    ->extraHeaderAttributes(['class' => 'bexia-roa-col-requested-at bexia-roa-col-date bexia-roa-col-context'])
                    ->extraCellAttributes(['class' => 'bexia-roa-col-requested-at bexia-roa-col-date bexia-roa-col-context'])
                    ->label('Fecha solicitud')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('decided_by')
                    ->extraHeaderAttributes(['class' => 'bexia-roa-col-decided-by bexia-roa-col-person bexia-roa-col-context'])
                    ->extraCellAttributes(['class' => 'bexia-roa-col-decided-by bexia-roa-col-person bexia-roa-col-context'])
                    ->label('Resolvió')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('decided_at')
                    ->extraHeaderAttributes(['class' => 'bexia-roa-col-decided-at bexia-roa-col-date bexia-roa-col-context'])
                    ->extraCellAttributes(['class' => 'bexia-roa-col-decided-at bexia-roa-col-date bexia-roa-col-context'])
                    ->label('Fecha resolucion')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('reason')
                    ->extraHeaderAttributes(['class' => 'bexia-roa-col-reason bexia-roa-col-long-text bexia-roa-col-context'])
                    ->extraCellAttributes(['class' => 'bexia-roa-col-reason bexia-roa-col-long-text bexia-roa-col-context'])
                    ->label('Motivo')
                    ->limit(50)
                    ->searchable(),

                Tables\Columns\TextColumn::make('company_id')
                    ->extraHeaderAttributes(['class' => 'bexia-roa-col-company bexia-roa-col-code bexia-roa-col-context'])
                    ->extraCellAttributes(['class' => 'bexia-roa-col-company bexia-roa-col-code bexia-roa-col-context'])
                    ->label('Empresa')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options(RepairOrderApproval::STATUSES),

                Tables\Filters\SelectFilter::make('approval_type')
                    ->label('Tipo')
                    ->options(RepairOrderApproval::TYPES),
            ])
            ->actions([
                Tables\Actions\Action::make('resolver_flujo')
                    ->label('Resolver')
                    ->icon('heroicon-o-check-circle')
                    ->color('primary')
                    ->visible(
                        fn (RepairOrderApproval $record): bool =>
                            static::isPendingStatus($record)
                            && static::hasLinkedApprovalRequest($record)
                    )
                    ->url(
                        fn (RepairOrderApproval $record): string =>
                            url(
                                '/admin/'
                                . (int) $record->company_id
                                . '/my-pending-approvals'
                            )
                    ),

                Tables\Actions\EditAction::make()
                    ->visible(fn (RepairOrderApproval $record): bool => static::canEdit($record)),

                Tables\Actions\Action::make('aprobar')
                    ->label('Aprobar')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(
                        fn (RepairOrderApproval $record): bool =>
                            static::isPendingStatus($record)
                            && ! static::hasLinkedApprovalRequest($record)
                            && static::canApproveRecord($record)
                    )
                    ->action(function (RepairOrderApproval $record): void {
                        $record->update([
                            'status' => 'aprobado',
                            'decided_by' => auth()->id(),
                            'decided_at' => now(),
                        ]);

                        static::logApprovalEvent($record, 'aprobacion_aprobada', 'Aprobacion autorizada desde Filament.');

                        Notification::make()
                            ->title('Aprobacion autorizada')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('rechazar')
                    ->label('Rechazar')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(
                        fn (RepairOrderApproval $record): bool =>
                            static::isPendingStatus($record)
                            && ! static::hasLinkedApprovalRequest($record)
                            && static::canRejectRecord($record)
                    )
                    ->action(function (RepairOrderApproval $record): void {
                        $record->update([
                            'status' => 'rechazado',
                            'decided_by' => auth()->id(),
                            'decided_at' => now(),
                        ]);

                        static::logApprovalEvent($record, 'aprobacion_rechazada', 'Aprobacion rechazada desde Filament.');

                        Notification::make()
                            ->title('Aprobacion rechazada')
                            ->danger()
                            ->send();
                    }),
            ])
            ->bulkActions([]);
    }

    public static function isPendingStatus(
        RepairOrderApproval $record
    ): bool {
        return in_array(
            strtolower((string) $record->status),
            ['pending', 'pendiente'],
            true
        );
    }

    public static function linkedApprovalRequestId(
        RepairOrderApproval $record
    ): ?int {
        $metadata = $record->metadata;

        if (is_string($metadata)) {
            $metadata = json_decode($metadata, true);
        }

        if (! is_array($metadata)) {
            return null;
        }

        $requestId = (int) (
            $metadata['approval_request_id'] ?? 0
        );

        return $requestId > 0 ? $requestId : null;
    }

    public static function hasLinkedApprovalRequest(
        RepairOrderApproval $record
    ): bool {
        return static::linkedApprovalRequestId($record) !== null;
    }

    public static function canApproveRecord(RepairOrderApproval $record): bool
    {
        return match ($record->approval_type) {
            'aceptar_garantia' => ServiceAccess::can('service.repairs.approve_warranty'),
            'rechazar_garantia' => ServiceAccess::can('service.repairs.reject_warranty'),
            'entrega_sin_cobro', 'reemplazar_producto', 'nota_credito' => ServiceAccess::can('service.repairs.authorize_delivery'),
            default => static::canViewAny(),
        };
    }

    public static function canRejectRecord(RepairOrderApproval $record): bool
    {
        return match ($record->approval_type) {
            'aceptar_garantia' => ServiceAccess::can('service.repairs.approve_warranty'),
            'rechazar_garantia' => ServiceAccess::can('service.repairs.reject_warranty'),
            default => static::canViewAny(),
        };
    }

    public static function logApprovalEvent(RepairOrderApproval $record, string $eventType, string $notes): void
    {
        ServiceCaseEvent::create([
            'company_id' => $record->company_id,
            'service_case_id' => $record->service_case_id,
            'repair_order_id' => $record->repair_order_id,
            'event_type' => $eventType,
            'from_status' => null,
            'to_status' => $record->status,
            'performed_by' => auth()->id(),
            'performed_at' => now(),
            'notes' => $notes,
            'metadata' => [
                'approval_id' => $record->id,
                'approval_type' => $record->approval_type,
                'amount' => $record->amount,
            ],
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRepairOrderApprovals::route('/'),
            'create' => Pages\CreateRepairOrderApproval::route('/create'),
            'edit' => Pages\EditRepairOrderApproval::route('/{record}/edit'),
        ];
    }
}
