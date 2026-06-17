<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SatDownloadRequestResource\Pages;
use App\Models\SatDownloadRequest;
use App\Support\FiscalSat\FiscalSatAccess;
use App\Support\FiscalSat\SatDownloadRequestVerifier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SatDownloadRequestResource extends Resource
{
    protected static ?string $model = SatDownloadRequest::class;

    protected static bool $isScopedToTenant = false;

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

    public static function canView($record): bool
    {
        return FiscalSatAccess::can('fiscal_sat.downloads.view');
    }

    public static function canCreate(): bool
    {
        return FiscalSatAccess::can('fiscal_sat.downloads.create');
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos de la solicitud SAT')
                    ->description('Consulta enviada al SAT. Primero se solicita, después se verifica, luego se descargan paquetes y finalmente se procesa la metadata o XML.')
                    ->schema([
                        Forms\Components\Select::make('company_id')
                            ->label('Empresa')
                            ->options(fn () => FiscalSatAccess::companyOptions())
                            ->searchable()
                            ->required(),

                        Forms\Components\Select::make('direction')
                            ->label('Flujo fiscal')
                            ->options([
                                'issued' => 'CFDI emitidos por la empresa',
                                'received' => 'CFDI recibidos por la empresa',
                            ])
                            ->default('received')
                            ->required(),

                        Forms\Components\Select::make('request_kind')
                            ->label('Tipo de descarga')
                            ->options([
                                'metadata' => 'Metadata',
                                'xml' => 'XML',
                            ])
                            ->default('metadata')
                            ->required()
                            ->helperText('Metadata trae el resumen de CFDI. XML trae los comprobantes completos cuando se solicite ese tipo de descarga.'),

                        Forms\Components\DateTimePicker::make('date_from')
                            ->label('Desde')
                            ->seconds(false)
                            ->required(),

                        Forms\Components\DateTimePicker::make('date_to')
                            ->label('Hasta')
                            ->seconds(false)
                            ->required(),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notas internas')
                            ->rows(3),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Resultado SAT')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->options([
                                'draft' => 'Borrador',
                                'requested' => 'Solicitada',
                                'processing' => 'Procesando',
                                'completed' => 'Completada',
                                'error' => 'Error',
                            ])
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('request_uuid')
                            ->label('UUID solicitud SAT')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('sat_status_code')
                            ->label('Código SAT')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\DateTimePicker::make('requested_at')
                            ->label('Solicitada')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\Textarea::make('sat_message')
                            ->label('Mensaje SAT')
                            ->rows(3)
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\Textarea::make('error_message')
                            ->label('Error')
                            ->rows(3)
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columns(2)
                    ->visible(fn (?SatDownloadRequest $record): bool => filled($record)),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('Sin solicitudes de descarga')
            ->emptyStateDescription('Crea una solicitud para que el SAT prepare metadata o XML.')
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('company.name')
                    ->label('Empresa')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('direction')
                    ->label('Flujo fiscal')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'issued' => 'CFDI emitidos por la empresa',
                        'received' => 'CFDI recibidos por la empresa',
                        default => (string) $state,
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('request_kind')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'metadata' => 'Metadata',
                        'xml' => 'XML',
                        default => (string) $state,
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('date_from')
                    ->label('Desde')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('date_to')
                    ->label('Hasta')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'draft' => 'Borrador',
                        'requested' => 'Solicitada',
                        'processing' => 'Procesando',
                        'completed' => 'Completada',
                        'error' => 'Error',
                        default => (string) $state,
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('request_uuid')
                    ->label('Solicitud SAT')
                    ->limit(22)
                    ->copyable(),

                Tables\Columns\TextColumn::make('sat_message')
                    ->label('Mensaje SAT')
                    ->limit(35),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('direction')
                    ->label('Flujo fiscal')
                    ->options([
                        'issued' => 'CFDI emitidos por la empresa',
                        'received' => 'CFDI recibidos por la empresa',
                    ]),

                Tables\Filters\SelectFilter::make('request_kind')
                    ->label('Tipo')
                    ->options([
                        'metadata' => 'Metadata',
                        'xml' => 'XML',
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
            'create' => Pages\CreateSatDownloadRequest::route('/create'),
            'view' => Pages\ViewSatDownloadRequest::route('/{record}'),
        ];
    }
}
