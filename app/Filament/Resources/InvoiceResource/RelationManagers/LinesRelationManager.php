<?php

namespace App\Filament\Resources\InvoiceResource\RelationManagers;

use App\Filament\Resources\InvoiceResource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Líneas de factura';

    protected static ?string $modelLabel = 'línea';

    protected static ?string $pluralModelLabel = 'líneas';

    public function form(Form $form): Form
    {
        return $form->schema(static::lineFormSchema());
    }

    public static function lineFormSchema(): array
    {
        return [
            Forms\Components\Section::make('Línea de factura')
                ->columns(12)
                ->schema([
                    Forms\Components\Select::make('product_id')
                        ->label('Producto')
                        ->searchable()
                        ->preload()
                        ->options(fn (): array => InvoiceResource::initialProductOptions())
                        ->getSearchResultsUsing(fn (string $search): array => InvoiceResource::productSearchOptions($search))
                        ->getOptionLabelUsing(fn ($value): ?string => InvoiceResource::productLabel((int) $value))
                        ->reactive()
                        ->afterStateUpdated(function ($state, Forms\Set $set): void {
                            $product = InvoiceResource::productById((int) ($state ?? 0));

                            if (! $product) {
                                return;
                            }

                            $label = InvoiceResource::productLabelFromRow($product);

                            $set('product_name', $label);
                            $set('description', $label);
                            $set('sat_product_service_code', (string) ($product->sat_product_service_code ?? ''));
                            $set('sat_unit_code', (string) ($product->sat_unit_code ?? ''));
                            $set('sat_tax_object_code', (string) ($product->sat_tax_object_code ?? ''));
                            $set('unit_price_without_tax', InvoiceResource::productSalePriceWithoutTax($product));
                            $set('tax_rate', InvoiceResource::productSaleTaxRate($product));
                        })
                        ->required()
                        ->columnSpan(5),

                    Forms\Components\TextInput::make('product_name')
                        ->label('Etiqueta')
                        ->required()
                        ->maxLength(255)
                        ->columnSpan(4),

                    Forms\Components\TextInput::make('sat_product_service_code')
                        ->label('Clave SAT')
                        ->maxLength(80)
                        ->columnSpan(3),

                    Forms\Components\Textarea::make('description')
                        ->label('Descripción / comentario de línea')
                        ->rows(2)
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('quantity')
                        ->label('Cantidad')
                        ->numeric()
                        ->default(1)
                        ->required()
                        ->columnSpan(2),

                    Forms\Components\TextInput::make('unit_price_without_tax')
                        ->label('Precio s/IVA')
                        ->numeric()
                        ->default(0)
                        ->required()
                        ->columnSpan(2),

                    Forms\Components\TextInput::make('tax_rate')
                        ->label('IVA %')
                        ->numeric()
                        ->default(16)
                        ->required()
                        ->columnSpan(2),

                    Forms\Components\TextInput::make('sat_unit_code')
                        ->label('UdM SAT')
                        ->maxLength(80)
                        ->columnSpan(2),

                    Forms\Components\TextInput::make('sat_tax_object_code')
                        ->label('Objeto impuesto SAT')
                        ->maxLength(80)
                        ->columnSpan(3),

                    Forms\Components\Hidden::make('company_id')
                        ->default(fn (): int => InvoiceResource::tenantCompanyId()),

                    Forms\Components\Hidden::make('source_type')
                        ->default('manual_line'),
                ]),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Productos agregados')
            ->description('Agrega, modifica o elimina líneas desde los modales. También puedes agregar comentarios sin importe.')
            ->columns([
                Tables\Columns\TextColumn::make('product_name')
                    ->label('Producto / comentario')
                    ->searchable()
                    ->wrap()
                    ->description(fn ($record): ?string => $record->description && $record->description !== $record->product_name ? $record->description : null),

                Tables\Columns\TextColumn::make('sat_unit_code')
                    ->label('Unidad')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Cantidad')
                    ->alignRight()
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2)),

                Tables\Columns\TextColumn::make('unit_price_without_tax')
                    ->label('Precio s/IVA')
                    ->alignRight()
                    ->formatStateUsing(fn ($state): string => '$ ' . number_format((float) $state, 2)),

                Tables\Columns\TextColumn::make('tax_rate')
                    ->label('Impuesto')
                    ->alignCenter()
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2) . '%'),

                Tables\Columns\TextColumn::make('subtotal')
                    ->label('Subtotal')
                    ->alignRight()
                    ->formatStateUsing(fn ($state): string => '$ ' . number_format((float) $state, 2))
                    ->summarize(
                        Tables\Columns\Summarizers\Sum::make()
                            ->label('Subtotal')
                            ->formatStateUsing(fn ($state): string => '$ ' . number_format((float) $state, 2))
                    ),

                Tables\Columns\TextColumn::make('tax_total')
                    ->label('Impuestos')
                    ->alignRight()
                    ->formatStateUsing(fn ($state): string => '$ ' . number_format((float) $state, 2))
                    ->summarize(
                        Tables\Columns\Summarizers\Sum::make()
                            ->label('Impuestos')
                            ->formatStateUsing(fn ($state): string => '$ ' . number_format((float) $state, 2))
                    ),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->alignRight()
                    ->weight('bold')
                    ->formatStateUsing(fn ($state): string => '$ ' . number_format((float) $state, 2))
                    ->summarize(
                        Tables\Columns\Summarizers\Sum::make()
                            ->label('Total')
                            ->formatStateUsing(fn ($state): string => '$ ' . number_format((float) $state, 2))
                    ),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Agregar línea')
                    ->icon('heroicon-o-plus-circle')
                    ->modalHeading('Agregar línea de factura')
                    ->modalWidth('5xl')
                    ->visible(fn (): bool => InvoiceResource::canModifyLines($this->getOwnerRecord()))
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['invoice_id'] = (int) $this->getOwnerRecord()->id;
                        $data['company_id'] = (int) $this->getOwnerRecord()->company_id;

                        return InvoiceResource::normalizeLineData($data);
                    })
                    ->after(function (): void {
                        InvoiceResource::recalculateInvoice($this->getOwnerRecord());
                        $this->getOwnerRecord()->refresh();
                    }),

                Tables\Actions\Action::make('add_comment')
                    ->label('Agregar comentario')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('gray')
                    ->modalHeading('Agregar comentario a la factura')
                    ->modalWidth('3xl')
                    ->visible(fn (): bool => InvoiceResource::canModifyLines($this->getOwnerRecord()))
                    ->form([
                        Forms\Components\TextInput::make('title')
                            ->label('Título')
                            ->default('Comentario')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Textarea::make('comment')
                            ->label('Comentario')
                            ->required()
                            ->rows(4),
                    ])
                    ->action(function (array $data): void {
                        $invoice = $this->getOwnerRecord();

                        DB::table('invoice_lines')->insert([
                            'invoice_id' => (int) $invoice->id,
                            'company_id' => (int) $invoice->company_id,
                            'source_type' => 'comment',
                            'source_line_id' => null,
                            'product_id' => null,
                            'product_name' => (string) ($data['title'] ?? 'Comentario'),
                            'description' => (string) ($data['comment'] ?? ''),
                            'quantity' => 0,
                            'unit_price_without_tax' => 0,
                            'unit_price' => 0,
                            'tax_rate' => 0,
                            'subtotal' => 0,
                            'discount_total' => 0,
                            'tax_total' => 0,
                            'total' => 0,
                            'sat_product_service_code' => null,
                            'sat_unit_code' => null,
                            'sat_tax_object_code' => null,
                            'metadata' => json_encode(['type' => 'comment'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        InvoiceResource::recalculateInvoice($invoice);
                        $invoice->refresh();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Modificar')
                    ->icon('heroicon-o-pencil-square')
                    ->modalHeading('Modificar línea')
                    ->modalWidth('5xl')
                    ->visible(fn ($record): bool => InvoiceResource::canModifyLines($this->getOwnerRecord())
                        && (string) ($record->source_type ?? '') !== 'comment')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['company_id'] = (int) $this->getOwnerRecord()->company_id;

                        return InvoiceResource::normalizeLineData($data);
                    })
                    ->after(function (): void {
                        InvoiceResource::recalculateInvoice($this->getOwnerRecord());
                        $this->getOwnerRecord()->refresh();
                    }),

                Tables\Actions\Action::make('edit_comment')
                    ->label('Modificar')
                    ->icon('heroicon-o-pencil-square')
                    ->modalHeading('Modificar comentario')
                    ->modalWidth('3xl')
                    ->visible(fn ($record): bool => InvoiceResource::canModifyLines($this->getOwnerRecord())
                        && (string) ($record->source_type ?? '') === 'comment')
                    ->fillForm(fn ($record): array => [
                        'title' => $record->product_name,
                        'comment' => $record->description,
                    ])
                    ->form([
                        Forms\Components\TextInput::make('title')
                            ->label('Título')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Textarea::make('comment')
                            ->label('Comentario')
                            ->required()
                            ->rows(4),
                    ])
                    ->action(function ($record, array $data): void {
                        $record->update([
                            'product_name' => (string) ($data['title'] ?? 'Comentario'),
                            'description' => (string) ($data['comment'] ?? ''),
                            'updated_at' => now(),
                        ]);

                        InvoiceResource::recalculateInvoice($this->getOwnerRecord());
                        $this->getOwnerRecord()->refresh();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->label('Eliminar')
                    ->icon('heroicon-o-trash')
                    ->visible(fn (): bool => InvoiceResource::canModifyLines($this->getOwnerRecord()))
                    ->after(function (): void {
                        InvoiceResource::recalculateInvoice($this->getOwnerRecord());
                        $this->getOwnerRecord()->refresh();
                    }),
            ])
            ->emptyStateHeading('Sin líneas')
            ->emptyStateDescription('Agrega productos o comentarios mientras la factura esté en borrador.')
            ->paginated(false);
    }
}
