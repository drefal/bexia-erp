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

    /*
     * BEXIA_SAT_DOWNLOAD_REQUEST_RESOURCE_RESPONSIVE_V5_79_71C
     * Visual-only responsive marker.
     */


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos de la solicitud SAT')
                    ->extraAttributes(['class' => 'bexia-sdr-section bexia-sdr-section-request'])
                    ->description('Consulta enviada al SAT. Primero se solicita, después se verifica, luego se descargan paquetes y finalmente se procesa la metadata o XML.')
                    ->schema([
                        Forms\Components\Select::make('company_id')
                            ->extraAttributes(['class' => 'bexia-sdr-field bexia-sdr-field-company bexia-sdr-select-field'])
                            ->label('Empresa')
                            ->options(fn () => FiscalSatAccess::companyOptions())
                            ->searchable()
                            ->required(),

                        Forms\Components\Select::make('direction')
                            ->extraAttributes(['class' => 'bexia-sdr-field bexia-sdr-field-direction bexia-sdr-select-field'])
                            ->label('Flujo fiscal')
                            ->options([
                                'issued' => 'CFDI emitidos por la empresa',
                                'received' => 'CFDI recibidos por la empresa',
                            ])
                            ->default('received')
                            ->required(),

                        Forms\Components\Select::make('request_kind')
                            ->extraAttributes(['class' => 'bexia-sdr-field bexia-sdr-field-request-kind bexia-sdr-select-field'])
                            ->label('Tipo de descarga')
                            ->options([
                                'metadata' => 'Metadata',
                                'xml' => 'XML',
                            ])
                            ->default('metadata')
                            ->required()
                            ->helperText('Metadata trae el resumen de CFDI. XML trae los comprobantes completos cuando se solicite ese tipo de descarga.'),

                        Forms\Components\DateTimePicker::make('date_from')
                            ->extraAttributes(['class' => 'bexia-sdr-field bexia-sdr-field-date-from bexia-sdr-date-field'])
                            ->label('Desde')
                            ->seconds(false)
                            ->required(),

                        Forms\Components\DateTimePicker::make('date_to')
                            ->extraAttributes(['class' => 'bexia-sdr-field bexia-sdr-field-date-to bexia-sdr-date-field'])
                            ->label('Hasta')
                            ->seconds(false)
                            ->required(),

                        Forms\Components\Textarea::make('notes')
                            ->extraAttributes(['class' => 'bexia-sdr-field bexia-sdr-field-notes bexia-sdr-long-field'])
                            ->label('Notas internas')
                            ->rows(3),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Resultado SAT')
                    ->extraAttributes(['class' => 'bexia-sdr-section bexia-sdr-section-result'])
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->extraAttributes(['class' => 'bexia-sdr-field bexia-sdr-field-status bexia-sdr-select-field'])
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
                            ->extraAttributes(['class' => 'bexia-sdr-field bexia-sdr-field-request-uuid bexia-sdr-code-field bexia-sdr-uuid-field'])
                            ->label('UUID solicitud SAT')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('sat_status_code')
                            ->extraAttributes(['class' => 'bexia-sdr-field bexia-sdr-field-sat-status-code bexia-sdr-code-field'])
                            ->label('Código SAT')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\DateTimePicker::make('requested_at')
                            ->extraAttributes(['class' => 'bexia-sdr-field bexia-sdr-field-requested-at bexia-sdr-date-field'])
                            ->label('Solicitada')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\Textarea::make('sat_message')
                            ->extraAttributes(['class' => 'bexia-sdr-field bexia-sdr-field-sat-message bexia-sdr-long-field'])
                            ->label('Mensaje SAT')
                            ->rows(3)
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\Textarea::make('error_message')
                            ->extraAttributes(['class' => 'bexia-sdr-field bexia-sdr-field-error-message bexia-sdr-long-field bexia-sdr-error-field'])
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
                    ->extraHeaderAttributes(['class' => 'bexia-sdr-col-company bexia-sdr-col-context bexia-sdr-col-long-text'])
                    ->extraCellAttributes(['class' => 'bexia-sdr-col-company bexia-sdr-col-context bexia-sdr-col-long-text'])
                    ->label('Empresa')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('direction')
                    ->extraHeaderAttributes(['class' => 'bexia-sdr-col-direction bexia-sdr-col-badge bexia-sdr-col-compact'])
                    ->extraCellAttributes(['class' => 'bexia-sdr-col-direction bexia-sdr-col-badge bexia-sdr-col-compact'])
                    ->label('Flujo fiscal')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'issued' => 'CFDI emitidos por la empresa',
                        'received' => 'CFDI recibidos por la empresa',
                        default => (string) $state,
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('request_kind')
                    ->extraHeaderAttributes(['class' => 'bexia-sdr-col-request-kind bexia-sdr-col-badge bexia-sdr-col-context'])
                    ->extraCellAttributes(['class' => 'bexia-sdr-col-request-kind bexia-sdr-col-badge bexia-sdr-col-context'])
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'metadata' => 'Metadata',
                        'xml' => 'XML',
                        default => (string) $state,
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('date_from')
                    ->extraHeaderAttributes(['class' => 'bexia-sdr-col-date-from bexia-sdr-col-date bexia-sdr-col-compact'])
                    ->extraCellAttributes(['class' => 'bexia-sdr-col-date-from bexia-sdr-col-date bexia-sdr-col-compact'])
                    ->label('Desde')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('date_to')
                    ->extraHeaderAttributes(['class' => 'bexia-sdr-col-date-to bexia-sdr-col-date bexia-sdr-col-compact'])
                    ->extraCellAttributes(['class' => 'bexia-sdr-col-date-to bexia-sdr-col-date bexia-sdr-col-compact'])
                    ->label('Hasta')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->extraHeaderAttributes(['class' => 'bexia-sdr-col-status bexia-sdr-col-badge bexia-sdr-col-compact'])
                    ->extraCellAttributes(['class' => 'bexia-sdr-col-status bexia-sdr-col-badge bexia-sdr-col-compact'])
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
                    ->extraHeaderAttributes(['class' => 'bexia-sdr-col-request-uuid bexia-sdr-col-code bexia-sdr-col-uuid bexia-sdr-col-long-text'])
                    ->extraCellAttributes(['class' => 'bexia-sdr-col-request-uuid bexia-sdr-col-code bexia-sdr-col-uuid bexia-sdr-col-long-text'])
                    ->label('Solicitud SAT')
                    ->limit(22)
                    ->copyable(),

                Tables\Columns\TextColumn::make('sat_message')
                    ->extraHeaderAttributes(['class' => 'bexia-sdr-col-sat-message bexia-sdr-col-message bexia-sdr-col-long-text bexia-sdr-col-context'])
                    ->extraCellAttributes(['class' => 'bexia-sdr-col-sat-message bexia-sdr-col-message bexia-sdr-col-long-text bexia-sdr-col-context'])
                    ->label('Mensaje SAT')
                    ->limit(35),

                Tables\Columns\TextColumn::make('created_at')
                    ->extraHeaderAttributes(['class' => 'bexia-sdr-col-created-at bexia-sdr-col-date bexia-sdr-col-compact'])
                    ->extraCellAttributes(['class' => 'bexia-sdr-col-created-at bexia-sdr-col-date bexia-sdr-col-compact'])
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
