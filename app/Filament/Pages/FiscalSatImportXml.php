<?php

namespace App\Filament\Pages;

use App\Support\FiscalSat\FiscalSatAccess;
use App\Support\FiscalSat\SatCfdiXmlImportService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;

class FiscalSatImportXml extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationLabel = 'Importar XML';

    protected static ?string $title = 'Importar XML CFDI';

    protected static ?string $navigationGroup = 'Fiscal SAT';

    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.pages.fiscal-sat-import-xml';

    public ?array $data = [];

    public ?array $lastImportResult = null;

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->ensureFiscalSatStorageDirectories();

        $this->form->fill([
            'direction' => 'received',
        ]);
    }

    private function ensureFiscalSatStorageDirectories(): void
    {
        Storage::disk('local')->makeDirectory('fiscal-sat/imports/tmp');
        Storage::disk('local')->makeDirectory('fiscal-sat/cfdi');
        Storage::disk('local')->makeDirectory('fiscal-sat/packages');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        return FiscalSatAccess::can('fiscal_sat.cfdi.import');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos de importación')
                    ->description('Carga manual de XML CFDI. Después, la descarga masiva SAT usará este mismo motor de importación.')
                    ->schema([
                        Forms\Components\Select::make('company_id')
                            ->label('Empresa')
                            ->options(fn () => FiscalSatAccess::companyOptions())
                            ->searchable()
                            ->required(),

                        Forms\Components\Select::make('direction')
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
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Nota')
                    ->schema([
                        Forms\Components\Placeholder::make('warning')
                            ->label('')
                            ->content('El XML se guardará en storage privado. No se requiere conexión SAT en esta fase.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function importXml(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->ensureFiscalSatStorageDirectories();

        $data = $this->form->getState();

        $companyId = (int) ($data['company_id'] ?? 0);
        $allowedCompanyIds = FiscalSatAccess::allowedCompanyIds(auth()->user());

        if (! in_array($companyId, $allowedCompanyIds, true)) {
            abort(403, 'Empresa no permitida.');
        }

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
            companyId: $companyId,
            direction: (string) $data['direction'],
            userId: auth()->id(),
            source: 'manual'
        );

        Storage::disk('local')->delete($path);

        $this->lastImportResult = $result;

        $this->form->fill([
            'company_id' => $companyId,
            'direction' => $data['direction'],
            'xml_file' => null,
        ]);

        Notification::make()
            ->success()
            ->title('XML CFDI importado')
            ->body('UUID: ' . $result['uuid'] . ' | ' . ($result['direction_label'] ?? $result['direction']) . ' | Total: $' . number_format((float) $result['total'], 2))
            ->send();
    }
}
