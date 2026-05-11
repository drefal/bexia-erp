<?php

namespace App\Filament\Resources\ContactResource\Pages\Concerns;

use App\Services\SatConstanciaParser;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

trait ImportsSatConstancia
{
    protected function importSatConstanciaAction(): Action
    {
        return Action::make('import_sat_constancia')
            ->label('Importar Constancia SAT')
            ->icon('heroicon-o-document-arrow-up')
            ->color('info')
            ->visible(fn (): bool => $this->shouldShowImportSatConstanciaAction())
            ->modalHeading('Importar Constancia de Situación Fiscal')
            ->modalDescription('Sube el PDF de la constancia. Bexia validará el RFC antes de llenar el formulario.')
            ->form([
                Forms\Components\FileUpload::make('constancia_pdf')
                    ->label('Constancia SAT en PDF')
                    ->disk('local')
                    ->directory('contacts/csf/tmp')
                    ->storeFiles(false)
                    ->acceptedFileTypes(['application/pdf'])
                    ->maxSize(10240)
                    ->preserveFilenames()
                    ->required()
                    ->helperText('El archivo se guardará en storage privado de Bexia y quedará ligado al contacto.'),
            ])
            ->action(function (array $data): void {
                $relativePath = null;

                try {
                    $uploaded = $data['constancia_pdf'] ?? null;

                    if (is_array($uploaded)) {
                        $uploaded = array_values($uploaded)[0] ?? null;
                    }

                    if (! $uploaded instanceof TemporaryUploadedFile) {
                        $this->failSatConstanciaImport('No se recibió correctamente el archivo PDF. Intenta seleccionarlo de nuevo.');
                    }

                    $originalName = $uploaded->getClientOriginalName() ?: 'constancia_sat.pdf';
                    $extension = strtolower($uploaded->getClientOriginalExtension() ?: 'pdf');

                    if ($extension !== 'pdf') {
                        $this->failSatConstanciaImport('El archivo debe ser un PDF.');
                    }

                    $safeBaseName = Str::slug(pathinfo($originalName, PATHINFO_FILENAME));
                    if ($safeBaseName === '') {
                        $safeBaseName = 'constancia-sat';
                    }

                    $filename = $safeBaseName . '-' . now()->format('Ymd-His') . '-' . Str::random(8) . '.pdf';
                    $directory = 'contacts/csf/' . now()->format('Y/m');

                    $relativePath = $uploaded->storeAs($directory, $filename, 'local');

                    if (! $relativePath || ! Storage::disk('local')->exists($relativePath)) {
                        $this->failSatConstanciaImport('El PDF no se pudo guardar en storage. Revisa permisos de storage/app.');
                    }

                    $path = Storage::disk('local')->path($relativePath);

                    if (! is_file($path)) {
                        $this->failSatConstanciaImport('El PDF se guardó, pero Bexia no lo pudo leer desde: ' . $relativePath);
                    }

                    $parsed = app(SatConstanciaParser::class)->parse($path);

                    $rfc = strtoupper(trim((string) ($parsed['rfc'] ?? '')));

                    if ($rfc === '') {
                        Storage::disk('local')->delete($relativePath);

                        $this->failSatConstanciaImport('No pude detectar el RFC en la constancia. No se llenaron los campos.');
                    }

                    $duplicate = $this->findDuplicateMainContactByRfc($rfc);

                    if ($duplicate) {
                        Storage::disk('local')->delete($relativePath);

                        $this->failSatConstanciaImport(
                            'Ya existe un contacto principal activo con el RFC '
                            . $rfc
                            . ': '
                            . ($duplicate->name ?? 'Contacto ID ' . $duplicate->id)
                            . '. No se llenaron los campos para evitar duplicarlo.'
                        );
                    }

                    $parsed['csf_pdf_path'] = $relativePath;
                    $parsed['csf_source_filename'] = $originalName;
                    $parsed['csf_imported_at'] = now()->toDateTimeString();
                    $parsed['csf_imported_by_user_id'] = auth()->id();

                    $sourceNote = 'Archivo Constancia SAT: ' . $originalName
                        . ' | Ruta: ' . $relativePath
                        . ' | Importado: ' . now()->format('Y-m-d H:i:s');

                    $parsed['internal_notes'] = trim((string) ($parsed['internal_notes'] ?? '') . "\n" . $sourceNote);

                    $currentState = $this->form->getRawState();

                    if (filled($currentState['internal_notes'] ?? null) && filled($parsed['internal_notes'] ?? null)) {
                        $parsed['internal_notes'] = trim((string) $currentState['internal_notes'])
                            . "\n\n---\n"
                            . trim((string) $parsed['internal_notes']);
                    }

                    $this->form->fill(array_replace($currentState, $parsed));

                    Notification::make()
                        ->title('Constancia SAT procesada')
                        ->body('Revisa los datos detectados antes de guardar el contacto.')
                        ->success()
                        ->send();
                } catch (ValidationException $e) {
                    throw $e;
                } catch (Throwable $e) {
                    if ($relativePath && Storage::disk('local')->exists($relativePath)) {
                        Storage::disk('local')->delete($relativePath);
                    }

                    report($e);

                    $this->failSatConstanciaImport($e->getMessage());
                }
            });
    }

    protected function shouldShowImportSatConstanciaAction(): bool
    {
        try {
            $state = $this->form->getRawState();

            if (filled($state['csf_pdf_path'] ?? null)) {
                return false;
            }
        } catch (Throwable) {
            // Ignorar si el formulario todavía no está listo.
        }

        if (property_exists($this, 'record') && $this->record && filled($this->record->csf_pdf_path ?? null)) {
            return false;
        }

        return true;
    }

    protected function failSatConstanciaImport(string $message): void
    {
        Notification::make()
            ->title('No se pudo importar la constancia')
            ->body($message)
            ->danger()
            ->persistent()
            ->send();

        if (method_exists($this, 'addError')) {
            $this->addError('mountedActionsData.0.constancia_pdf', $message);
            $this->addError('constancia_pdf', $message);
        }

        throw ValidationException::withMessages([
            'constancia_pdf' => $message,
            'mountedActionsData.0.constancia_pdf' => $message,
        ]);
    }

    protected function findDuplicateMainContactByRfc(?string $rfc): ?object
    {
        $rfc = strtoupper(trim((string) $rfc));

        if ($rfc === '' || ! Schema::hasTable('contacts')) {
            return null;
        }

        $currentRecordId = null;

        if (property_exists($this, 'record') && $this->record) {
            $currentRecordId = $this->record->getKey();
        }

        $currentState = [];

        try {
            $currentState = $this->form->getRawState();
        } catch (Throwable) {
            $currentState = [];
        }

        $companyId = $currentState['company_id'] ?? null;

        if (! $companyId && Filament::getTenant()) {
            $companyId = Filament::getTenant()->getKey();
        }

        if (! $companyId && auth()->user() && isset(auth()->user()->company_id)) {
            $companyId = auth()->user()->company_id;
        }

        $query = DB::table('contacts')
            ->whereNull('parent_contact_id')
            ->where('address_type', 'main')
            ->whereRaw('upper(trim(rfc)) = ?', [$rfc]);

        if ($companyId) {
            $query->where('company_id', $companyId);
        } else {
            $query->whereNull('company_id');
        }

        if ($currentRecordId) {
            $query->where('id', '<>', $currentRecordId);
        }

        if (Schema::hasColumn('contacts', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query
            ->orderBy('id')
            ->first(['id', 'name', 'rfc']);
    }

    protected function viewSatConstanciaAction(): Action
    {
        return Action::make('view_sat_constancia')
            ->label('Ver Constancia SAT')
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->url(fn (): ?string => (property_exists($this, 'record') && $this->record && filled($this->record->csf_pdf_path ?? null))
                ? route('contacts.csf.show', ['contact' => $this->record])
                : null)
            ->openUrlInNewTab()
            ->visible(fn (): bool => property_exists($this, 'record') && $this->record && filled($this->record->csf_pdf_path ?? null));
    }

    protected function downloadSatConstanciaAction(): Action
    {
        return Action::make('download_sat_constancia')
            ->label('Descargar Constancia SAT')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->url(fn (): ?string => (property_exists($this, 'record') && $this->record && filled($this->record->csf_pdf_path ?? null))
                ? route('contacts.csf.download', ['contact' => $this->record])
                : null)
            ->visible(fn (): bool => property_exists($this, 'record') && $this->record && filled($this->record->csf_pdf_path ?? null));
    }

}
