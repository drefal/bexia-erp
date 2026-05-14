<?php

namespace App\Filament\Resources\InvoiceResource\RelationManagers;

use App\Filament\Resources\InvoiceResource;
use App\Filament\Resources\TreasuryMovementResource;
use App\Models\PaymentForm;
use App\Models\TreasuryAccount;
use App\Support\Billing\InvoicePaymentTreasuryService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Throwable;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Pagos';

    protected static ?string $modelLabel = 'pago';

    protected static ?string $pluralModelLabel = 'pagos';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Pagos')
            ->description('Registra cobros relacionados con la factura. El cobro se confirma desde Tesorería.')
            ->columns([
                Tables\Columns\TextColumn::make('payment_label')
                    ->label('Forma de pago')
                    ->searchable()
                    ->placeholder('Pago'),

                Tables\Columns\TextColumn::make('reference')
                    ->label('Referencia')
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('treasuryMovement.treasuryAccount.name')
                    ->label('Cuenta / Caja')
                    ->placeholder('Sin movimiento'),

                Tables\Columns\TextColumn::make('treasuryMovement.status')
                    ->label('Estado tesorería')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => match ((string) $state) {
                        'draft' => 'Borrador',
                        'posted' => 'Confirmado',
                        'cancelled' => 'Cancelado',
                        default => $state ?: 'Sin movimiento',
                    })
                    ->color(fn ($state): string => match ((string) $state) {
                        'posted' => 'success',
                        'cancelled' => 'danger',
                        'draft' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado pago')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => match ((string) $state) {
                        'paid' => 'Pagado',
                        'pending' => 'Pendiente',
                        'cancelled' => 'Cancelado',
                        default => $state ?: 'Pendiente',
                    })
                    ->color(fn ($state): string => match ((string) $state) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Fecha pago')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Pendiente'),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Importe')
                    ->alignRight()
                    ->formatStateUsing(fn ($state): string => '$'.number_format((float) $state, 2, '.', ','))
                    ->summarize(
                        Tables\Columns\Summarizers\Sum::make()
                            ->label('Total')
                            ->formatStateUsing(fn ($state): string => '$'.number_format((float) $state, 2, '.', ','))
                    ),
            ])
            ->headerActions([
                Tables\Actions\Action::make('register_treasury_payment')
                    ->label('Registrar pago')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->modalHeading('Registrar pago')
                    ->modalDescription('Se creará un pago pendiente y un movimiento de tesorería en borrador. El saldo se afectará al confirmar el movimiento en Tesorería.')
                    ->modalWidth('4xl')
                    ->visible(fn (): bool => app(InvoicePaymentTreasuryService::class)->canRegisterPayment($this->getOwnerRecord()))
                    ->form(fn (): array => [
                        Forms\Components\Select::make('treasury_account_id')
                            ->label('Cuenta / Caja')
                            ->options(fn (): array => TreasuryAccount::query()
                                ->where('company_id', (int) $this->getOwnerRecord()->company_id)
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->required()
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('payment_form_id')
                            ->label('Forma de pago')
                            ->options(fn (): array => PaymentForm::query()
                                ->where('company_id', (int) $this->getOwnerRecord()->company_id)
                                ->where('is_active', true)
                                ->orderBy('sort_order')
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn ($form) => [$form->id => "{$form->code} - {$form->name}"])
                                ->all())
                            ->required()
                            ->searchable()
                            ->preload(),

                        Forms\Components\DatePicker::make('payment_date')
                            ->label('Fecha')
                            ->required()
                            ->default(now()),

                        Forms\Components\TextInput::make('amount')
                            ->label('Importe')
                            ->numeric()
                            ->prefix('$')
                            ->inputMode('decimal')
                            ->step('0.01')
                            ->required()
                            ->minValue(0.01)
                            ->default(fn (): float => app(InvoicePaymentTreasuryService::class)->openBalance($this->getOwnerRecord()))
                            ->helperText(fn (): string => 'Saldo pendiente: $'.number_format(app(InvoicePaymentTreasuryService::class)->openBalance($this->getOwnerRecord()), 2, '.', ',')),

                        Forms\Components\TextInput::make('reference')
                            ->label('Referencia')
                            ->maxLength(255),

                        Forms\Components\Textarea::make('notes')
                            ->label('Nota')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->action(function (array $data): void {
                        try {
                            $payment = app(InvoicePaymentTreasuryService::class)
                                ->createDraftPayment($this->getOwnerRecord(), $data, auth()->id());

                            $this->getOwnerRecord()->refresh();

                            Notification::make()
                                ->title('Pago registrado')
                                ->body('Se creó el pago pendiente y el movimiento de tesorería en borrador.')
                                ->success()
                                ->send();
                        } catch (Throwable $e) {
                            report($e);

                            Notification::make()
                                ->title('No se pudo registrar el pago')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('view_treasury_movement')
                    ->label('Ver movimiento')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->visible(fn ($record): bool => filled($record->treasury_movement_id))
                    ->url(fn ($record): string => TreasuryMovementResource::getUrl('view', [
                        'record' => $record->treasury_movement_id,
                    ])),
            ])
            ->emptyStateHeading('Sin pagos')
            ->emptyStateDescription('Registra pagos para disminuir el saldo de la factura. El movimiento se confirma desde Tesorería.')
            ->paginated(false);
    }
}
