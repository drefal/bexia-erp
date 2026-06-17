<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SatDownloadRequestResource\Pages;
use App\Models\SatDownloadRequest;
use App\Support\FiscalSat\FiscalSatAccess;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SatDownloadRequestResource extends Resource
{
    protected static ?string $model = SatDownloadRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static ?string $navigationLabel = 'Descarga CFDI';

    protected static ?string $modelLabel = 'Solicitud de descarga SAT';

    protected static ?string $pluralModelLabel = 'Descargas CFDI';

    protected static ?string $navigationGroup = 'Fiscal SAT';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        return FiscalSatAccess::can('fiscal_sat.downloads.view');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('company.name')
                    ->label('Empresa')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('direction')
                    ->label('Dirección')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('request_kind')
                    ->label('Tipo')
                    ->badge(),

                Tables\Columns\TextColumn::make('date_from')
                    ->label('Desde')
                    ->dateTime('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('date_to')
                    ->label('Hasta')
                    ->dateTime('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('request_uuid')
                    ->label('Solicitud SAT')
                    ->limit(18)
                    ->copyable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('direction')
                    ->label('Dirección')
                    ->options([
                        'issued' => 'Emitidos',
                        'received' => 'Recibidos',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'draft' => 'Borrador',
                        'requested' => 'Solicitada',
                        'processing' => 'Procesando',
                        'completed' => 'Completada',
                        'error' => 'Error',
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
            'index' => Pages\ListSatDownloadRequests::route('/'),
            'view' => Pages\ViewSatDownloadRequest::route('/{record}'),
        ];
    }
}
