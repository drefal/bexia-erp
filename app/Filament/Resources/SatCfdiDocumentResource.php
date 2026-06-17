<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SatCfdiDocumentResource\Pages;
use App\Models\SatCfdiDocument;
use App\Support\FiscalSat\FiscalSatAccess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SatCfdiDocumentResource extends Resource
{
    protected static ?string $model = SatCfdiDocument::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'CFDI';

    protected static ?string $modelLabel = 'CFDI';

    protected static ?string $pluralModelLabel = 'CFDI';

    protected static ?string $navigationGroup = 'Fiscal SAT';

    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        return FiscalSatAccess::can('fiscal_sat.cfdi.view');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos fiscales')
                    ->schema([
                        Forms\Components\TextInput::make('uuid')->label('UUID')->disabled(),
                        Forms\Components\TextInput::make('direction')->label('Dirección')->disabled(),
                        Forms\Components\TextInput::make('cfdi_type')->label('Tipo CFDI')->disabled(),
                        Forms\Components\TextInput::make('status')->label('Estado')->disabled(),
                    ])
                    ->columns(4),

                Forms\Components\Section::make('Emisor y receptor')
                    ->schema([
                        Forms\Components\TextInput::make('issuer_rfc')->label('RFC emisor')->disabled(),
                        Forms\Components\TextInput::make('issuer_name')->label('Emisor')->disabled(),
                        Forms\Components\TextInput::make('receiver_rfc')->label('RFC receptor')->disabled(),
                        Forms\Components\TextInput::make('receiver_name')->label('Receptor')->disabled(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Importes')
                    ->schema([
                        Forms\Components\TextInput::make('subtotal')->label('Subtotal')->disabled(),
                        Forms\Components\TextInput::make('total_transferred_taxes')->label('Impuestos trasladados')->disabled(),
                        Forms\Components\TextInput::make('total_withheld_taxes')->label('Retenciones')->disabled(),
                        Forms\Components\TextInput::make('total')->label('Total')->disabled(),
                    ])
                    ->columns(4),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('issued_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('company.name')
                    ->label('Empresa')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('direction')
                    ->label('Dirección')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('uuid')
                    ->label('UUID')
                    ->searchable()
                    ->copyable()
                    ->limit(12),

                Tables\Columns\TextColumn::make('cfdi_type')
                    ->label('Tipo')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('issuer_rfc')
                    ->label('RFC emisor')
                    ->searchable(),

                Tables\Columns\TextColumn::make('receiver_rfc')
                    ->label('RFC receptor')
                    ->searchable(),

                Tables\Columns\TextColumn::make('issued_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('MXN')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('direction')
                    ->label('Dirección')
                    ->options([
                        'issued' => 'Emitido',
                        'received' => 'Recibido',
                    ]),

                Tables\Filters\SelectFilter::make('cfdi_type')
                    ->label('Tipo CFDI')
                    ->options([
                        'I' => 'Ingreso',
                        'E' => 'Egreso',
                        'P' => 'Pago',
                        'N' => 'Nómina',
                        'T' => 'Traslado',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'vigente' => 'Vigente',
                        'cancelado' => 'Cancelado',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return FiscalSatAccess::scopeCompany(parent::getEloquentQuery());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSatCfdiDocuments::route('/'),
            'view' => Pages\ViewSatCfdiDocument::route('/{record}'),
        ];
    }
}
