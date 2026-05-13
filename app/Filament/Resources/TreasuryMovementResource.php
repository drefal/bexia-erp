<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TreasuryMovementResource\Pages;
use App\Models\PaymentForm;
use App\Models\TreasuryAccount;
use App\Models\TreasuryMovement;
use App\Support\Treasury\TreasuryMovementPostingService;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class TreasuryMovementResource extends Resource
{
    protected static ?string $model = TreasuryMovement::class;

    protected static ?string $tenantOwnershipRelationshipName = 'company';

    protected static ?string $tenantRelationshipName = 'treasuryMovements';

    protected static ?string $navigationGroup = 'Tesorería';

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $modelLabel = 'movimiento de tesorería';

    protected static ?string $pluralModelLabel = 'movimientos de tesorería';

    protected static ?int $navigationSort = 30;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->can('treasury.view');
    }

    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->can('treasury.view');
    }

    public static function canView($record): bool
    {
        return auth()->check() && auth()->user()->can('treasury.view');
    }

    public static function canCreate(): bool
    {
        return auth()->check() && auth()->user()->can('treasury.create');
    }

    public static function canEdit($record): bool
    {
        return auth()->check()
            && auth()->user()->can('treasury.update')
            && (string) ($record->status ?? '') === 'draft';
    }

    public static function canDelete($record): bool
    {
        /*
         * BEXIA_V5524B3_TREASURY_NO_DELETE
         * Los movimientos de dinero no se borran; se cancelan para conservar historial.
         */
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Hidden::make('company_id')
                ->default(fn (): ?int => static::companyId()),

            Forms\Components\Hidden::make('status')
                ->default('draft'),

            Forms\Components\Select::make('treasury_account_id')
                ->label('Cuenta / Caja')
                ->options(fn (): array => TreasuryAccount::query()
                    ->where('company_id', static::companyId())
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->required()
                ->searchable()
                ->preload()
                ->disabled(fn (?TreasuryMovement $record): bool => filled($record) && (string) $record->status !== 'draft'),

            Forms\Components\Select::make('type')
                ->label('Tipo')
                ->options([
                    'inbound' => 'Entrada',
                    'outbound' => 'Salida',
                ])
                ->helperText('Las transferencias entre cuentas se habilitarán en una fase posterior.')
                ->required()
                ->default('inbound')
                ->disabled(fn (?TreasuryMovement $record): bool => filled($record) && (string) $record->status !== 'draft'),

            Forms\Components\DatePicker::make('movement_date')
                ->label('Fecha')
                ->required()
                ->default(now())
                ->disabled(fn (?TreasuryMovement $record): bool => filled($record) && (string) $record->status !== 'draft'),

            Forms\Components\TextInput::make('amount')
                ->label('Importe')
                ->numeric()
                ->prefix('$')
                ->inputMode('decimal')
                ->step('0.01')
                ->required()
                ->minValue(0.01)
                ->disabled(fn (?TreasuryMovement $record): bool => filled($record) && (string) $record->status !== 'draft'),

            Forms\Components\TextInput::make('currency_code')
                ->label('Moneda')
                ->default('MXN')
                ->required()
                ->maxLength(3)
                ->disabled(fn (?TreasuryMovement $record): bool => filled($record) && (string) $record->status !== 'draft'),

            Forms\Components\Select::make('payment_form_id')
                ->label('Forma de pago')
                ->options(fn (): array => PaymentForm::query()
                    ->where('company_id', static::companyId())
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get()
                    ->mapWithKeys(fn ($form) => [$form->id => "{$form->code} - {$form->name}"])
                    ->all())
                ->searchable()
                ->preload()
                ->disabled(fn (?TreasuryMovement $record): bool => filled($record) && (string) $record->status !== 'draft'),

            Forms\Components\TextInput::make('reference')
                ->label('Referencia')
                ->maxLength(255)
                ->disabled(fn (?TreasuryMovement $record): bool => filled($record) && (string) $record->status !== 'draft'),

            Forms\Components\Textarea::make('description')
                ->label('Descripción')
                ->columnSpanFull()
                ->disabled(fn (?TreasuryMovement $record): bool => filled($record) && (string) $record->status !== 'draft'),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Información del movimiento')
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('movement_date')
                        ->label('Fecha')
                        ->date('d/m/Y'),

                    Infolists\Components\TextEntry::make('treasuryAccount.name')
                        ->label('Cuenta / Caja'),

                    Infolists\Components\TextEntry::make('type')
                        ->label('Tipo')
                        ->badge()
                        ->formatStateUsing(fn (?string $state): string => self::typeLabel($state))
                        ->color(fn (?string $state): string => match ($state) {
                            'inbound' => 'success',
                            'outbound' => 'danger',
                            default => 'gray',
                        }),

                    Infolists\Components\TextEntry::make('amount')
                        ->label('Importe')
                        // BEXIA_V5524B6_TREASURY_MONEY_FORMAT_INFOLIST
                        ->formatStateUsing(fn ($state, $record): string => self::moneyLabel($state, $record?->currency_code)),

                    Infolists\Components\TextEntry::make('paymentForm.name')
                        ->label('Forma de pago')
                        ->placeholder('-'),

                    Infolists\Components\TextEntry::make('reference')
                        ->label('Referencia')
                        ->placeholder('-'),

                    Infolists\Components\TextEntry::make('status')
                        ->label('Estado')
                        ->badge()
                        ->formatStateUsing(fn (?string $state): string => self::statusLabel($state))
                        ->color(fn (?string $state): string => self::statusColor($state)),

                    Infolists\Components\TextEntry::make('posted_at')
                        ->label('Confirmado el')
                        ->dateTime('d/m/Y H:i')
                        ->placeholder('-'),

                    Infolists\Components\TextEntry::make('cancelled_at')
                        ->label('Cancelado el')
                        ->dateTime('d/m/Y H:i')
                        ->placeholder('-'),

                    Infolists\Components\TextEntry::make('description')
                        ->label('Descripción')
                        ->columnSpanFull()
                        ->placeholder('-'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->where('company_id', static::companyId())
                // BEXIA_V5524B7_TREASURY_RECENT_MOVEMENTS_FIRST
                ->orderByDesc('movement_date')
                ->orderByDesc('id'))
            ->columns([
                Tables\Columns\TextColumn::make('movement_date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('treasuryAccount.name')
                    ->label('Cuenta / Caja')
                    ->searchable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->formatStateUsing(fn (?string $state): string => self::typeLabel($state))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'inbound' => 'success',
                        'outbound' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Importe')
                    // BEXIA_V5524B6_TREASURY_MONEY_FORMAT_TABLE
                    ->formatStateUsing(fn ($state, $record): string => self::moneyLabel($state, $record?->currency_code)),

                Tables\Columns\TextColumn::make('paymentForm.name')
                    ->label('Forma de pago')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('reference')
                    ->label('Referencia')
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->formatStateUsing(fn (?string $state): string => self::statusLabel($state))
                    ->badge()
                    ->color(fn (?string $state): string => self::statusColor($state)),
            ])
            ->filters([
                /*
                 * BEXIA_V5524B8_TREASURY_MOVEMENT_FILTERS
                 * Filtros operativos para búsqueda de movimientos de tesorería.
                 */
                Tables\Filters\Filter::make('movement_date_range')
                    ->label('Rango de fechas')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('Desde'),

                        Forms\Components\DatePicker::make('until')
                            ->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('movement_date', '>=', $date),
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('movement_date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if (! empty($data['from'])) {
                            $indicators[] = 'Desde '.\Carbon\Carbon::parse($data['from'])->format('d/m/Y');
                        }

                        if (! empty($data['until'])) {
                            $indicators[] = 'Hasta '.\Carbon\Carbon::parse($data['until'])->format('d/m/Y');
                        }

                        return $indicators;
                    }),

                Tables\Filters\SelectFilter::make('treasury_account_id')
                    ->label('Cuenta / Caja')
                    ->options(fn (): array => TreasuryAccount::query()
                        ->where('company_id', static::companyId())
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'draft' => 'Borrador',
                        'posted' => 'Confirmado',
                        'cancelled' => 'Cancelado',
                    ]),

                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'inbound' => 'Entrada',
                        'outbound' => 'Salida',
                    ]),
            ])
            ->actions([
                /*
                 * BEXIA_V5524B9_TREASURY_PRINT_ACTION
                 */
                Tables\Actions\Action::make('print_movement')
                    ->label('Imprimir')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn ($record): string => route('treasury.movements.print', ['movement' => $record]))
                    ->openUrlInNewTab(),


                Tables\Actions\ViewAction::make()
                    ->label('Ver'),

                Tables\Actions\EditAction::make()
                    ->label('Editar')
                    ->visible(fn (TreasuryMovement $record): bool => $record->status === 'draft'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTreasuryMovements::route('/'),
            'create' => Pages\CreateTreasuryMovement::route('/create'),
            'view' => Pages\ViewTreasuryMovement::route('/{record}'),
            'edit' => Pages\EditTreasuryMovement::route('/{record}/edit'),
        ];
    }

    public static function companyId(): ?int
    {
        return Filament::getTenant()?->id
            ?? auth()->user()?->company_id
            ?? null;
    }

    public static function typeLabel(?string $state): string
    {
        return match ((string) $state) {
            'inbound' => 'Entrada',
            'outbound' => 'Salida',
            default => filled($state) ? (string) $state : '-',
        };
    }

    public static function statusLabel(?string $state): string
    {
        return match ((string) $state) {
            'draft' => 'Borrador',
            'posted' => 'Confirmado',
            'cancelled' => 'Cancelado',
            default => filled($state) ? (string) $state : '-',
        };
    }

    public static function statusColor(?string $state): string
    {
        return match ((string) $state) {
            'posted' => 'success',
            'cancelled' => 'danger',
            'draft' => 'gray',
            default => 'gray',
        };
    }

    public static function postMovement(TreasuryMovement $record): void
    {
        try {
            app(TreasuryMovementPostingService::class)->post($record, auth()->id());

            Notification::make()
                ->title('Movimiento confirmado')
                ->body('El saldo de la cuenta/caja fue actualizado.')
                ->success()
                ->send();
        } catch (Throwable $e) {
            report($e);

            Notification::make()
                ->title('No se pudo confirmar')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public static function cancelMovement(TreasuryMovement $record): void
    {
        try {
            app(TreasuryMovementPostingService::class)->cancel($record);

            Notification::make()
                ->title('Movimiento cancelado')
                ->body('El movimiento quedó cancelado. Si estaba confirmado, el saldo fue reversado.')
                ->success()
                ->send();
        } catch (Throwable $e) {
            report($e);

            Notification::make()
                ->title('No se pudo cancelar')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public static function moneyLabel($amount, ?string $currencyCode = 'MXN'): string
    {
        /*
         * BEXIA_V5524B6_TREASURY_MONEY_HELPER
         * Formato operativo: $1,500.00
         */
        $value = is_numeric($amount) ? (float) $amount : 0.0;
        $formatted = '$'.number_format($value, 2, '.', ',');

        $currencyCode = strtoupper((string) ($currencyCode ?: 'MXN'));

        return $currencyCode !== 'MXN'
            ? "{$formatted} {$currencyCode}"
            : $formatted;
    }


}
