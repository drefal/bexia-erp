<?php

namespace App\Filament\Resources\InvoiceResource\RelationManagers;

use App\Filament\Resources\InvoiceResource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Pagos';

    protected static ?string $modelLabel = 'pago';

    protected static ?string $pluralModelLabel = 'pagos';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Pago')
                ->columns(12)
                ->schema([
                    Forms\Components\TextInput::make('payment_label')
                        ->label('Método / referencia')
                        ->placeholder('Efectivo, transferencia, tarjeta, etc.')
                        ->maxLength(255)
                        ->required()
                        ->columnSpan(4),

                    Forms\Components\TextInput::make('payment_form_code')
                        ->label('Forma de pago SAT')
                        ->placeholder('01, 03, 04...')
                        ->maxLength(40)
                        ->columnSpan(2),

                    Forms\Components\TextInput::make('amount')
                        ->label('Importe')
                        ->numeric()
                        ->required()
                        ->default(0)
                        ->columnSpan(2),

                    Forms\Components\Select::make('status')
                        ->label('Estado')
                        ->options([
                            'paid' => 'Pagado',
                            'pending' => 'Pendiente',
                            'cancelled' => 'Cancelado',
                        ])
                        ->default('paid')
                        ->required()
                        ->columnSpan(2),

                    Forms\Components\DateTimePicker::make('paid_at')
                        ->label('Fecha de pago')
                        ->default(now())
                        ->seconds(false)
                        ->columnSpan(2),

                    Forms\Components\Textarea::make('metadata_note')
                        ->label('Nota')
                        ->rows(2)
                        ->columnSpanFull(),

                    Forms\Components\Hidden::make('company_id')
                        ->default(fn (): int => InvoiceResource::tenantCompanyId()),

                    Forms\Components\Hidden::make('source_type')
                        ->default('manual_payment'),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Pagos')
            ->description('Registra pagos relacionados con la factura. El saldo se recalcula automáticamente.')
            ->columns([
                Tables\Columns\TextColumn::make('payment_label')
                    ->label('Método / referencia')
                    ->searchable()
                    ->placeholder('Pago'),

                Tables\Columns\TextColumn::make('payment_form_code')
                    ->label('Forma SAT')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => match ((string) $state) {
                        'paid' => 'Pagado',
                        'pending' => 'Pendiente',
                        'cancelled' => 'Cancelado',
                        default => $state ?: 'Pagado',
                    })
                    ->color(fn ($state): string => match ((string) $state) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i'),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Importe')
                    ->alignRight()
                    ->formatStateUsing(fn ($state): string => '$ ' . number_format((float) $state, 2))
                    ->summarize(
                        Tables\Columns\Summarizers\Sum::make()
                            ->label('Total pagado')
                            ->formatStateUsing(fn ($state): string => '$ ' . number_format((float) $state, 2))
                    ),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Registrar pago')
                    ->icon('heroicon-o-banknotes')
                    ->modalHeading('Registrar pago')
                    ->modalWidth('4xl')
                    ->visible(fn (): bool => InvoiceResource::canModifyPayments($this->getOwnerRecord()))
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['invoice_id'] = (int) $this->getOwnerRecord()->id;
                        $data['company_id'] = (int) $this->getOwnerRecord()->company_id;
                        $data['source_type'] = $data['source_type'] ?? 'manual_payment';

                        $note = trim((string) ($data['metadata_note'] ?? ''));
                        unset($data['metadata_note']);

                        $data['metadata'] = $note !== ''
                            ? ['note' => $note]
                            : [];

                        return $data;
                    })
                    ->after(function (): void {
                        InvoiceResource::recalculateInvoice($this->getOwnerRecord());
                        $this->getOwnerRecord()->refresh();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Modificar')
                    ->modalHeading('Modificar pago')
                    ->modalWidth('4xl')
                    ->visible(fn (): bool => InvoiceResource::canModifyPayments($this->getOwnerRecord()))
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['company_id'] = (int) $this->getOwnerRecord()->company_id;

                        $note = trim((string) ($data['metadata_note'] ?? ''));
                        unset($data['metadata_note']);

                        if ($note !== '') {
                            $data['metadata'] = ['note' => $note];
                        }

                        return $data;
                    })
                    ->after(function (): void {
                        InvoiceResource::recalculateInvoice($this->getOwnerRecord());
                        $this->getOwnerRecord()->refresh();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->label('Eliminar')
                    ->visible(fn (): bool => InvoiceResource::canModifyPayments($this->getOwnerRecord()))
                    ->after(function (): void {
                        InvoiceResource::recalculateInvoice($this->getOwnerRecord());
                        $this->getOwnerRecord()->refresh();
                    }),
            ])
            ->emptyStateHeading('Sin pagos')
            ->emptyStateDescription('Registra pagos para disminuir el saldo de la factura.')
            ->paginated(false);
    }
}
