<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PosAuditLogResource\Pages;
use App\Models\PosAuditLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PosAuditLogResource extends Resource
{
    protected static ?string $model = PosAuditLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Punto de Venta';

    protected static ?string $navigationLabel = 'Auditoría PDV';

    protected static ?string $modelLabel = 'Registro de auditoría PDV';

    protected static ?string $pluralModelLabel = 'Auditoría PDV';

    protected static ?int $navigationSort = 60;

    protected static ?string $tenantOwnershipRelationshipName = 'company';



    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

public static function canView($record): bool
    {
        return static::canViewAny();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        try {
            if (function_exists('tenant')) {
                $tenant = tenant();

                if (is_object($tenant) && isset($tenant->id)) {
                    $query->where(function (Builder $q) use ($tenant) {
                        $q->where('company_id', (int) $tenant->id)
                            ->orWhereNull('company_id');
                    });
                }
            }
        } catch (\Throwable $e) {
            //
        }

        return $query;
    }

    /*
     * BEXIA_PAUDIT_RESOURCE_RESPONSIVE_V5_79_54C
     * Visual-only responsive marker.
     */


public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información')
                    ->extraAttributes(['class' => 'bexia-paudit-section bexia-paudit-section-info'])
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('id')
                            ->extraAttributes(['class' => 'bexia-paudit-field bexia-paudit-field-id bexia-paudit-mono'])
                            ->label('ID')
                            ->disabled(),

                        Forms\Components\TextInput::make('action')
                            ->extraAttributes(['class' => 'bexia-paudit-field bexia-paudit-field-action'])
                            ->label('Acción')
                            ->disabled(),

                        Forms\Components\TextInput::make('description')
                            ->extraAttributes(['class' => 'bexia-paudit-field bexia-paudit-field-description'])
                            ->label('Descripción')
                            ->disabled()
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('company_id')
                            ->extraAttributes(['class' => 'bexia-paudit-field bexia-paudit-field-company bexia-paudit-mono'])
                            ->label('Empresa')
                            ->disabled(),

                        Forms\Components\TextInput::make('user_id')
                            ->extraAttributes(['class' => 'bexia-paudit-field bexia-paudit-field-user bexia-paudit-mono'])
                            ->label('Usuario')
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('created_at')
                            ->extraAttributes(['class' => 'bexia-paudit-field bexia-paudit-field-datetime bexia-paudit-field-created'])
                            ->label('Fecha')
                            ->disabled(),
                    ]),

                Forms\Components\Section::make('Referencias')
                    ->extraAttributes(['class' => 'bexia-paudit-section bexia-paudit-section-refs'])
                    ->columns(4)
                    ->schema([
                        Forms\Components\TextInput::make('pos_session_id')
                            ->extraAttributes(['class' => 'bexia-paudit-field bexia-paudit-field-session bexia-paudit-mono'])
                            ->label('Sesión PDV')
                            ->disabled(),

                        Forms\Components\TextInput::make('pos_order_id')
                            ->extraAttributes(['class' => 'bexia-paudit-field bexia-paudit-field-ticket bexia-paudit-mono'])
                            ->label('Ticket')
                            ->disabled(),

                        Forms\Components\TextInput::make('pos_order_refund_id')
                            ->extraAttributes(['class' => 'bexia-paudit-field bexia-paudit-field-refund bexia-paudit-mono'])
                            ->label('Devolución')
                            ->disabled(),

                        Forms\Components\TextInput::make('stock_movement_id')
                            ->extraAttributes(['class' => 'bexia-paudit-field bexia-paudit-field-stock-movement bexia-paudit-mono'])
                            ->label('Movimiento almacén')
                            ->disabled(),

                        Forms\Components\TextInput::make('entity_type')
                            ->extraAttributes(['class' => 'bexia-paudit-field bexia-paudit-field-entity-type'])
                            ->label('Entidad')
                            ->disabled(),

                        Forms\Components\TextInput::make('entity_id')
                            ->extraAttributes(['class' => 'bexia-paudit-field bexia-paudit-field-entity-id bexia-paudit-mono'])
                            ->label('ID entidad')
                            ->disabled(),

                        Forms\Components\TextInput::make('ip_address')
                            ->extraAttributes(['class' => 'bexia-paudit-field bexia-paudit-field-ip bexia-paudit-mono'])
                            ->label('IP')
                            ->disabled(),

                        Forms\Components\TextInput::make('user_agent')
                            ->extraAttributes(['class' => 'bexia-paudit-field bexia-paudit-field-user-agent'])
                            ->label('Navegador')
                            ->disabled()
                            ->columnSpan(2),
                    ]),

                Forms\Components\Section::make('Datos')
                    ->extraAttributes(['class' => 'bexia-paudit-section bexia-paudit-section-data'])
                    ->columns(1)
                    ->schema([
                        Forms\Components\KeyValue::make('before_data')
                            ->label('Antes')
                            ->disabled(),

                        Forms\Components\KeyValue::make('after_data')
                            ->label('Después')
                            ->disabled(),

                        Forms\Components\KeyValue::make('metadata')
                            ->label('Metadata')
                            ->disabled(),
                    ]),
            ]);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\Navigation\BexiaMenuRuntime::shouldRegister(
            'resources.posauditlogresource',
            fn (): bool => static::bexiaBaseShouldRegisterNavigation(),
        );
    }

    protected static function bexiaBaseShouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('pos.menu.view')
            );
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('pos.menu.view')
            );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->extraHeaderAttributes(['class' => 'bexia-paudit-col-id bexia-paudit-mono'])
                    ->extraCellAttributes(['class' => 'bexia-paudit-col-id bexia-paudit-mono'])
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->extraHeaderAttributes(['class' => 'bexia-paudit-col-date bexia-paudit-col-created'])
                    ->extraCellAttributes(['class' => 'bexia-paudit-col-date bexia-paudit-col-created'])
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('action')
                    ->extraHeaderAttributes(['class' => 'bexia-paudit-col-action bexia-paudit-col-badge'])
                    ->extraCellAttributes(['class' => 'bexia-paudit-col-action bexia-paudit-col-badge'])
                    ->label('Acción')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'pos.refund.total.success',
                        'pos.refund.partial.success' => 'warning',
                        'pos.ticket.cancel_pending.success' => 'danger',
                        'pos.session.close.success' => 'success',
                        'pos.price_list.change' => 'info',
                        'pos.discount.applied' => 'success',
                        'pos.discount.blocked' => 'danger',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->extraHeaderAttributes(['class' => 'bexia-paudit-col-description bexia-paudit-col-primary'])
                    ->extraCellAttributes(['class' => 'bexia-paudit-col-description bexia-paudit-col-primary'])
                    ->label('Descripción')
                    ->searchable()
                    ->wrap()
                    ->limit(70),

                Tables\Columns\TextColumn::make('company_id')
                    ->extraHeaderAttributes(['class' => 'bexia-paudit-col-company bexia-paudit-mono'])
                    ->extraCellAttributes(['class' => 'bexia-paudit-col-company bexia-paudit-mono'])
                    ->label('Empresa')
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->extraHeaderAttributes(['class' => 'bexia-paudit-col-user'])
                    ->extraCellAttributes(['class' => 'bexia-paudit-col-user'])
                    ->label('Usuario')
                    ->placeholder(fn ($record): string => $record->user_id ? ('Usuario #' . $record->user_id) : '-')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('pos_session_id')
                    ->extraHeaderAttributes(['class' => 'bexia-paudit-col-session bexia-paudit-mono'])
                    ->extraCellAttributes(['class' => 'bexia-paudit-col-session bexia-paudit-mono'])
                    ->label('Sesión')
                    ->sortable(),

                Tables\Columns\TextColumn::make('pos_order_id')
                    ->extraHeaderAttributes(['class' => 'bexia-paudit-col-ticket bexia-paudit-mono'])
                    ->extraCellAttributes(['class' => 'bexia-paudit-col-ticket bexia-paudit-mono'])
                    ->label('Ticket')
                    ->sortable(),

                Tables\Columns\TextColumn::make('pos_order_refund_id')
                    ->extraHeaderAttributes(['class' => 'bexia-paudit-col-refund bexia-paudit-mono'])
                    ->extraCellAttributes(['class' => 'bexia-paudit-col-refund bexia-paudit-mono'])
                    ->label('Devolución')
                    ->sortable(),

                Tables\Columns\TextColumn::make('ip_address')
                    ->extraHeaderAttributes(['class' => 'bexia-paudit-col-ip bexia-paudit-mono'])
                    ->extraCellAttributes(['class' => 'bexia-paudit-col-ip bexia-paudit-mono'])
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('action')
                    ->label('Acción')
                    ->options([
                        'pos.refund.total.attempt' => 'Intento devolución total',
                        'pos.refund.total.success' => 'Devolución total registrada',
                        'pos.refund.partial.attempt' => 'Intento devolución parcial',
                        'pos.refund.partial.success' => 'Devolución parcial registrada',
                        'pos.ticket.cancel_pending.attempt' => 'Intento cancelar pendiente',
                        'pos.ticket.cancel_pending.success' => 'Ticket pendiente cancelado',
                        'pos.session.close.attempt' => 'Intento cierre caja',
                        'pos.session.close.success' => 'Cierre caja realizado',
                        'pos.price_list.change' => 'Cambio de lista de precios',
                        'pos.discount.applied' => 'Descuento aplicado',
                        'pos.discount.blocked' => 'Descuento bloqueado',
                    ]),

                Tables\Filters\Filter::make('created_at')
                    ->label('Fecha')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->extraAttributes(['class' => 'bexia-paudit-field bexia-paudit-field-filter-date bexia-paudit-field-from'])
                            ->label('Desde'),
                        Forms\Components\DatePicker::make('until')
                            ->extraAttributes(['class' => 'bexia-paudit-field bexia-paudit-field-filter-date bexia-paudit-field-until'])
                            ->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Ver detalle'),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPosAuditLogs::route('/'),
            'view' => Pages\ViewPosAuditLog::route('/{record}'),
        ];
    }
}
