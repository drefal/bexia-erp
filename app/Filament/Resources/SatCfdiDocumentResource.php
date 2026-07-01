<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SatCfdiDocumentResource\Pages;
use App\Models\SatCfdiDocument;
use App\Support\FiscalSat\FiscalSatAccess;
use App\Support\FiscalSat\SatCfdiXmlImportService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class SatCfdiDocumentResource extends Resource
{
    protected static ?string $model = SatCfdiDocument::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'CFDI';

    protected static ?string $modelLabel = 'CFDI';

    protected static ?string $pluralModelLabel = 'CFDI';

    protected static ?string $navigationGroup = 'Fiscal SAT';

    protected static ?int $navigationSort = 5;

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

    /*
     * BEXIA_SCFDOC_RESOURCE_RESPONSIVE_V5_79_57C
     * Visual-only responsive marker.
     */


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos fiscales')
                    ->extraAttributes(['class' => 'bexia-scfdoc-section bexia-scfdoc-section-fiscal'])
                    ->schema([
                        Forms\Components\TextInput::make('uuid')->label('UUID')->disabled()
                            ->extraAttributes(['class' => 'bexia-scfdoc-field bexia-scfdoc-field-uuid bexia-scfdoc-mono']),
                        Forms\Components\TextInput::make('direction')->label('Dirección')->disabled()
                            ->extraAttributes(['class' => 'bexia-scfdoc-field bexia-scfdoc-field-direction bexia-scfdoc-badge-field']),
                        Forms\Components\TextInput::make('cfdi_type')->label('Tipo CFDI')->disabled()
                            ->extraAttributes(['class' => 'bexia-scfdoc-field bexia-scfdoc-field-type bexia-scfdoc-badge-field']),
                        Forms\Components\TextInput::make('status')->label('Estado')->disabled()
                            ->extraAttributes(['class' => 'bexia-scfdoc-field bexia-scfdoc-field-status bexia-scfdoc-badge-field']),
                    ])
                    ->columns(4),

                Forms\Components\Section::make('Emisor y receptor')
                    ->extraAttributes(['class' => 'bexia-scfdoc-section bexia-scfdoc-section-parties'])
                    ->schema([
                        Forms\Components\TextInput::make('issuer_rfc')->label('RFC emisor')->disabled()
                            ->extraAttributes(['class' => 'bexia-scfdoc-field bexia-scfdoc-field-rfc bexia-scfdoc-mono']),
                        Forms\Components\TextInput::make('issuer_name')->label('Emisor')->disabled()
                            ->extraAttributes(['class' => 'bexia-scfdoc-field bexia-scfdoc-field-name']),
                        Forms\Components\TextInput::make('receiver_rfc')->label('RFC receptor')->disabled()
                            ->extraAttributes(['class' => 'bexia-scfdoc-field bexia-scfdoc-field-rfc bexia-scfdoc-mono']),
                        Forms\Components\TextInput::make('receiver_name')->label('Receptor')->disabled()
                            ->extraAttributes(['class' => 'bexia-scfdoc-field bexia-scfdoc-field-name']),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Importes')
                    ->extraAttributes(['class' => 'bexia-scfdoc-section bexia-scfdoc-section-amounts'])
                    ->schema([
                        Forms\Components\TextInput::make('subtotal')->label('Subtotal')->disabled()
                            ->extraAttributes(['class' => 'bexia-scfdoc-field bexia-scfdoc-field-money bexia-scfdoc-field-subtotal']),
                        Forms\Components\TextInput::make('total_transferred_taxes')->label('Impuestos trasladados')->disabled()
                            ->extraAttributes(['class' => 'bexia-scfdoc-field bexia-scfdoc-field-money bexia-scfdoc-field-tax-transferred']),
                        Forms\Components\TextInput::make('total_withheld_taxes')->label('Retenciones')->disabled()
                            ->extraAttributes(['class' => 'bexia-scfdoc-field bexia-scfdoc-field-money bexia-scfdoc-field-tax-withheld']),
                        Forms\Components\TextInput::make('total')->label('Total')->disabled()
                            ->extraAttributes(['class' => 'bexia-scfdoc-field bexia-scfdoc-field-money bexia-scfdoc-field-total']),
                    ])
                    ->columns(4),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('issued_at', 'desc')
            ->headerActions([
                Tables\Actions\Action::make('importXml')
                    ->label('Importar XML')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('primary')
                    ->visible(fn () => FiscalSatAccess::can('fiscal_sat.cfdi.import'))
                    ->form([
                        Forms\Components\Select::make('company_id')
                            ->extraAttributes(['class' => 'bexia-scfdoc-field bexia-scfdoc-field-import-company bexia-scfdoc-select'])
                            ->label('Empresa')
                            ->options(fn () => FiscalSatAccess::companyOptions())
                            ->searchable()
                            ->required(),

                        Forms\Components\Select::make('direction')
                            ->extraAttributes(['class' => 'bexia-scfdoc-field bexia-scfdoc-field-import-direction bexia-scfdoc-select'])
                            ->label('Dirección fiscal')
                            ->options([
                                'issued' => 'Emitido por la empresa',
                                'received' => 'Recibido por la empresa',
                            ])
                            ->required(),

                        Forms\Components\FileUpload::make('xml_file')
                            ->label('Archivo XML CFDI')
                            ->disk('local')
                            ->directory('fiscal-sat/imports/tmp')
                            ->acceptedFileTypes([
                                'application/xml',
                                'text/xml',
                                'application/octet-stream',
                            ])
                            ->maxSize(10240)
                            ->preserveFilenames()
                            ->required(),

                        Forms\Components\Placeholder::make('warning')
                            ->extraAttributes(['class' => 'bexia-scfdoc-placeholder bexia-scfdoc-placeholder-warning'])
                            ->label('Importante')
                            ->content('Por ahora la carga es manual. El XML se guardará en storage privado y se registrará en el repositorio Fiscal SAT.'),
                    ])
                    ->action(function (array $data): void {
                        $path = $data['xml_file'] ?? null;

                        if (is_array($path)) {
                            $path = reset($path);
                        }

                        if (! is_string($path) || $path === '') {
                            throw new \RuntimeException('No se recibió archivo XML.');
                        }

                        $fullPath = Storage::disk('local')->path($path);

                        $result = app(SatCfdiXmlImportService::class)->importFromPath(
                            path: $fullPath,
                            companyId: (int) $data['company_id'],
                            direction: (string) $data['direction'],
                            userId: auth()->id(),
                            source: 'manual'
                        );

                        Storage::disk('local')->delete($path);

                        Notification::make()
                            ->success()
                            ->title('XML CFDI importado')
                            ->body('UUID: ' . $result['uuid'] . ' | ' . ($result['direction_label'] ?? $result['direction']) . ' | Total: $' . number_format((float) $result['total'], 2))
                            ->send();
                    }),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('company.name')
                    ->extraHeaderAttributes(['class' => 'bexia-scfdoc-col-company bexia-scfdoc-col-primary'])
                    ->extraCellAttributes(['class' => 'bexia-scfdoc-col-company bexia-scfdoc-col-primary'])
                    ->label('Empresa')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('direction')
                    ->extraHeaderAttributes(['class' => 'bexia-scfdoc-col-direction bexia-scfdoc-col-badge'])
                    ->extraCellAttributes(['class' => 'bexia-scfdoc-col-direction bexia-scfdoc-col-badge'])
                    ->label('Dirección')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'issued' => 'Emitido',
                        'received' => 'Recibido',
                        default => (string) $state,
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('uuid')
                    ->extraHeaderAttributes(['class' => 'bexia-scfdoc-col-uuid bexia-scfdoc-mono'])
                    ->extraCellAttributes(['class' => 'bexia-scfdoc-col-uuid bexia-scfdoc-mono'])
                    ->label('UUID')
                    ->searchable()
                    ->copyable()
                    ->limit(12),

                Tables\Columns\TextColumn::make('cfdi_type')
                    ->extraHeaderAttributes(['class' => 'bexia-scfdoc-col-type bexia-scfdoc-col-badge'])
                    ->extraCellAttributes(['class' => 'bexia-scfdoc-col-type bexia-scfdoc-col-badge'])
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'I' => 'Ingreso',
                        'E' => 'Egreso',
                        'P' => 'Pago',
                        'N' => 'Nómina',
                        'T' => 'Traslado',
                        default => (string) $state,
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->extraHeaderAttributes(['class' => 'bexia-scfdoc-col-status bexia-scfdoc-col-badge'])
                    ->extraCellAttributes(['class' => 'bexia-scfdoc-col-status bexia-scfdoc-col-badge'])
                    ->label('Estado')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('issuer_rfc')
                    ->extraHeaderAttributes(['class' => 'bexia-scfdoc-col-rfc bexia-scfdoc-mono'])
                    ->extraCellAttributes(['class' => 'bexia-scfdoc-col-rfc bexia-scfdoc-mono'])
                    ->label('RFC emisor')
                    ->searchable(),

                Tables\Columns\TextColumn::make('receiver_rfc')
                    ->extraHeaderAttributes(['class' => 'bexia-scfdoc-col-rfc bexia-scfdoc-mono'])
                    ->extraCellAttributes(['class' => 'bexia-scfdoc-col-rfc bexia-scfdoc-mono'])
                    ->label('RFC receptor')
                    ->searchable(),

                Tables\Columns\TextColumn::make('issued_at')
                    ->extraHeaderAttributes(['class' => 'bexia-scfdoc-col-date'])
                    ->extraCellAttributes(['class' => 'bexia-scfdoc-col-date'])
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total')
                    ->extraHeaderAttributes(['class' => 'bexia-scfdoc-col-money'])
                    ->extraCellAttributes(['class' => 'bexia-scfdoc-col-money'])
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
