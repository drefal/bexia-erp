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

    protected static ?int $navigationSort = 99;

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

public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('id')
                            ->label('ID')
                            ->disabled(),

                        Forms\Components\TextInput::make('action')
                            ->label('Acción')
                            ->disabled(),

                        Forms\Components\TextInput::make('description')
                            ->label('Descripción')
                            ->disabled()
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('company_id')
                            ->label('Empresa')
                            ->disabled(),

                        Forms\Components\TextInput::make('user_id')
                            ->label('Usuario')
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('created_at')
                            ->label('Fecha')
                            ->disabled(),
                    ]),

                Forms\Components\Section::make('Referencias')
                    ->columns(4)
                    ->schema([
                        Forms\Components\TextInput::make('pos_session_id')
                            ->label('Sesión PDV')
                            ->disabled(),

                        Forms\Components\TextInput::make('pos_order_id')
                            ->label('Ticket')
                            ->disabled(),

                        Forms\Components\TextInput::make('pos_order_refund_id')
                            ->label('Devolución')
                            ->disabled(),

                        Forms\Components\TextInput::make('stock_movement_id')
                            ->label('Movimiento almacén')
                            ->disabled(),

                        Forms\Components\TextInput::make('entity_type')
                            ->label('Entidad')
                            ->disabled(),

                        Forms\Components\TextInput::make('entity_id')
                            ->label('ID entidad')
                            ->disabled(),

                        Forms\Components\TextInput::make('ip_address')
                            ->label('IP')
                            ->disabled(),

                        Forms\Components\TextInput::make('user_agent')
                            ->label('Navegador')
                            ->disabled()
                            ->columnSpan(2),
                    ]),

                Forms\Components\Section::make('Datos')
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
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('action')
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
                    ->label('Descripción')
                    ->searchable()
                    ->wrap()
                    ->limit(70),

                Tables\Columns\TextColumn::make('company_id')
                    ->label('Empresa')
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Usuario')
                    ->placeholder(fn ($record): string => $record->user_id ? ('Usuario #' . $record->user_id) : '-')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('pos_session_id')
                    ->label('Sesión')
                    ->sortable(),

                Tables\Columns\TextColumn::make('pos_order_id')
                    ->label('Ticket')
                    ->sortable(),

                Tables\Columns\TextColumn::make('pos_order_refund_id')
                    ->label('Devolución')
                    ->sortable(),

                Tables\Columns\TextColumn::make('ip_address')
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
                            ->label('Desde'),
                        Forms\Components\DatePicker::make('until')
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
