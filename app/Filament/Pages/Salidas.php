<?php

namespace App\Filament\Pages;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Support\DynamicFormBuilder;
use App\Models\ExitWarehouse;
use App\Models\ExitProject;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form as FilamentForm;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Pagination\LengthAwarePaginator;

class Salidas extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;
    use WithPagination;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';
    protected static ?string $navigationLabel = 'Salidas';

    protected static ?int $navigationSort = 101;
    protected static ?string $navigationGroup = 'Salidas';
protected static ?string $title = 'Salidas';
    protected static string $view = 'filament.pages.salidas';

    public array $data = [];
    public ?int $submissionId = null;
    public ?int $formId = null;
    public string $status = 'BORRADOR';
    public bool $showForm = false;
    public string $search = '';
    public string $sortField = 'fecha';
    public string $sortDirection = 'desc';
    public int $perPage = 10;

    private array $formKeys = ['salidas_gl7', 'salidas'];

private function canAccessSalidas(): bool
{
    $user = Filament::auth()->user();

    if (! $user) {
        return false;
    }

    if (method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin()) {
        return true;
    }

    if (method_exists($user, 'isGroupAdmin') && $user->isGroupAdmin()) {
        return true;
    }

    return $user->can('salidas.ver')
        || $user->can('salidas.view')
        || $user->can('salidas.access');
}

private function canCreateSalidas(): bool
{
    $user = Filament::auth()->user();

    return $user && $user->can('salidas.create');
}

private function canManageAllPdfs(): bool
{
    $user = Filament::auth()->user();

    return $user && $user->can('salidas.ver_todas');
}

private function canSendPdf(): bool
{
    $user = Filament::auth()->user();

    return $user && $user->can('salidas.enviar_pdf');
}

public function canDeleteSalidas(): bool
{
    $user = Filament::auth()->user();

    if (! $user) {
        return false;
    }

    if (method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin()) {
        return true;
    }

    if (method_exists($user, 'isGroupAdmin') && $user->isGroupAdmin()) {
        return true;
    }

    return $user->can('salidas.delete');
}
    
private function canConfigureSalidas(): bool
{
    $user = Filament::auth()->user();

    if (! $user) {
        return false;
    }

    if (method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin()) {
        return true;
    }

    if (method_exists($user, 'isGroupAdmin') && $user->isGroupAdmin()) {
        return true;
    }

    return $user->can('salidas.configurar');
}

private function currentTenantId(): ?int
{
    $tenant = Filament::getTenant();

    if ($tenant) {
        return (int) $tenant->getKey();
    }

    $routeTenant = request()->route('tenant');

    return is_numeric($routeTenant) ? (int) $routeTenant : null;
}

private function shippingWarehouseOptions(): array
{
    $tenantId = $this->currentTenantId();

    if (! $tenantId) {
        return [];
    }

    return ExitWarehouse::query()
        ->where('company_id', $tenantId)
        ->where('is_active', true)
        ->whereIn('usage_type', ['envio', 'ambos'])
        ->orderBy('sort_order')
        ->orderBy('name')
        ->pluck('name', 'name')
        ->all();
}

private function receivingWarehouseOptions(): array
{
    $tenantId = $this->currentTenantId();

    if (! $tenantId) {
        return [];
    }

    return ExitWarehouse::query()
        ->where('company_id', $tenantId)
        ->where('is_active', true)
        ->whereIn('usage_type', ['recepcion', 'ambos'])
        ->orderBy('sort_order')
        ->orderBy('name')
        ->pluck('name', 'name')
        ->all();
}

private function projectCatalogOptions(): array
{
    $tenantId = $this->currentTenantId();

    if (! $tenantId) {
        return [];
    }

    return ExitProject::query()
        ->where('company_id', $tenantId)
        ->where('is_active', true)
        ->orderBy('sort_order')
        ->orderBy('name')
        ->pluck('name', 'name')
        ->all();
}

private function fieldMatchesCatalog(array $field, array $needles): bool
{
    $name = $this->normalizeText((string) ($field['name'] ?? ''));
    $label = $this->normalizeText((string) ($field['label'] ?? ''));

    foreach ($needles as $needle) {
        $needle = $this->normalizeText((string) $needle);

        if ($needle !== '' && (str_contains($name, $needle) || str_contains($label, $needle))) {
            return true;
        }
    }

    return false;
}

private function applyCatalogOptionsToField(
    array $field,
    array $shippingOptions,
    array $receivingOptions,
    array $projectOptions
): array {
    if (($field['type'] ?? null) === 'items') {
        $field['item_fields'] = array_map(
            fn (array $itemField) => $this->applyCatalogOptionsToField(
                $itemField,
                $shippingOptions,
                $receivingOptions,
                $projectOptions
            ),
            $field['item_fields'] ?? []
        );

        return $field;
    }

    if (($field['type'] ?? 'text') !== 'select') {
        return $field;
    }

    if ($this->fieldMatchesCatalog($field, [
        'almacen_envio',
        'almacen de envio',
        'almacen envio',
        'bodega de envio',
        'bodega envio',
    ])) {
        $field['options'] = $shippingOptions;
        return $field;
    }

    if ($this->fieldMatchesCatalog($field, [
        'almacen_recepcion',
        'almacen de recepcion',
        'almacen recepcion',
        'bodega de recepcion',
        'bodega recepcion',
    ])) {
        $field['options'] = $receivingOptions;
        return $field;
    }

    if ($this->fieldMatchesCatalog($field, [
        'proyecto',
        'project',
    ])) {
        $field['options'] = $projectOptions;
        return $field;
    }

    return $field;
}

private function applyCatalogOptionsToSchema(array $schema): array
{
    $shippingOptions = $this->shippingWarehouseOptions();
    $receivingOptions = $this->receivingWarehouseOptions();
    $projectOptions = $this->projectCatalogOptions();

    $steps = $schema['steps'] ?? [];

    foreach ($steps as $stepIndex => $step) {
        $fields = $step['fields'] ?? [];

        $schema['steps'][$stepIndex]['fields'] = array_map(
            fn (array $field) => $this->applyCatalogOptionsToField(
                $field,
                $shippingOptions,
                $receivingOptions,
                $projectOptions
            ),
            $fields
        );
    }

    return $schema;
}

    protected function hasFormActionsInHeader(): bool
    {
        return false;
    }

public function mount(): void
    {
        $user = Filament::auth()->user();
        $tenant = Filament::getTenant();

        if (! $user) {
            $this->redirect('/admin/login');
            return;
        }

        if (! $this->canAccessSalidas()) {
            Notification::make()
                ->title('No tienes permiso para ver Salidas.')
                ->danger()
                ->send();

            $this->redirect(route('filament.admin.pages.dashboard', [
                'tenant' => $tenant?->getKey() ?? request()->route('tenant'),
            ]));

            return;
        }

        $form = $this->resolveFormDefinition();

        if (! $form) {
            Notification::make()
                ->title('No existe un formulario de Salidas (key salidas_gl7 / salidas).')
                ->danger()
                ->send();

            $this->redirect(route('filament.admin.pages.dashboard', [
                'tenant' => $tenant?->getKey() ?? request()->route('tenant'),
            ]));

            return;
        }

        $this->formId = $form->id;
        $this->showForm = false;
        $this->data = [];
    }

    private function resolveFormDefinition(): ?Form
    {
        $tenant = Filament::getTenant();

        $query = Form::query()->whereIn('key', $this->formKeys);

        if ($tenant && Schema::hasColumn('forms', 'company_id')) {
            $query->where('company_id', $tenant->getKey());
        }

        return $query->orderByRaw("
            CASE
                WHEN key = 'salidas_gl7' THEN 1
                WHEN key = 'salidas' THEN 2
                ELSE 99
            END
        ")->first();
    }

private function submissionsQuery(): Builder
{
    $tenant = Filament::getTenant();

    $query = FormSubmission::query()
        ->with('form')
        ->where('status', 'ENVIADA');

    if ($this->formId) {
        $query->where('form_id', $this->formId);
    }

    if ($tenant && Schema::hasColumn('form_submissions', 'company_id')) {
        $query->where('company_id', $tenant->getKey());
    }

    return $query;
}

    public function getSubmissionsProperty(): Collection
    {
        $user = Filament::auth()->user();

        if (! $user) {
            return collect();
        }

        if ($this->canManageAllPdfs()) {
            return $this->submissionsQuery()->get();
        }

        return $this->submissionsQuery()
            ->where('created_by', $user->id)
            ->get();
    }

    public function updatingSearch(): void
{
    $this->resetPage();
}

public function updatedPerPage(): void
{
    $this->resetPage();
}

public function sortBy(string $field): void
{
    if ($this->sortField === $field) {
        $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        $this->sortField = $field;
        $this->sortDirection = 'asc';
    }

    $this->resetPage();
}

private function normalizeText(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');

    $map = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
        'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
        'ñ' => 'n',
    ];

    return strtr($text, $map);
}

private function findStep(array $steps, string $wantedTitle): ?array
{
    $wanted = $this->normalizeText($wantedTitle);

    foreach ($steps as $st) {
        $title = $this->normalizeText($st['title'] ?? '');
        if ($title === $wanted) {
            return $st;
        }
    }

    foreach ($steps as $st) {
        $title = $this->normalizeText($st['title'] ?? '');
        if ($wanted !== '' && str_contains($title, $wanted)) {
            return $st;
        }
    }

    return null;
}

private function findValueInSchema(array $schema, array $data, string $stepTitle, array $nameCandidates = [], array $labelNeedles = []): mixed
{
    $steps = $schema['steps'] ?? [];
    $step = $this->findStep($steps, $stepTitle);

    if ($step) {
        $fields = $step['fields'] ?? [];

        foreach ($nameCandidates as $candidate) {
            foreach ($fields as $f) {
                if (($f['name'] ?? null) === $candidate) {
                    $name = $f['name'] ?? null;
                    return $name ? ($data[$name] ?? null) : null;
                }
            }
        }

        foreach ($fields as $f) {
            $label = $this->normalizeText($f['label'] ?? '');

            foreach ($labelNeedles as $needle) {
                if ($needle !== '' && str_contains($label, $this->normalizeText($needle))) {
                    $name = $f['name'] ?? null;
                    return $name ? ($data[$name] ?? null) : null;
                }
            }
        }
    }

    foreach ($nameCandidates as $candidate) {
        if (array_key_exists($candidate, $data)) {
            return $data[$candidate];
        }
    }

    foreach ($data as $key => $value) {
        $keyNorm = $this->normalizeText($key);

        foreach ($labelNeedles as $needle) {
            if ($needle !== '' && str_contains($keyNorm, $this->normalizeText($needle))) {
                return $value;
            }
        }
    }

    return null;
}

public function getPaginatedSubmissionsProperty(): LengthAwarePaginator
{
    $rows = $this->submissions->map(function ($submission) {
        $schemaRaw = $submission->form->schema ?? [];
        $schema = is_array($schemaRaw) ? $schemaRaw : (json_decode($schemaRaw, true) ?: []);
        $data = is_array($submission->data ?? null) ? $submission->data : [];

        $envia = $this->findValueInSchema(
            $schema,
            $data,
            'Datos generales',
            ['envia_nombre', 'nombre_quien_envia', 'nombre_envia'],
            ['nombre de quien envia']
        );

        $recibe = $this->findValueInSchema(
            $schema,
            $data,
            'Participantes / autorización',
            ['recibe_nombre', 'nombre_quien_recibe', 'nombre_recibe'],
            ['nombre de quien recibe']
        );

        $proyecto = $this->findValueInSchema(
            $schema,
            $data,
            'Participantes / autorización',
            ['proyecto', 'project'],
            ['proyecto']
        );

        $observaciones = $this->findValueInSchema(
            $schema,
            $data,
            'Participantes / autorización',
            ['observacion', 'observaciones'],
            ['observacion', 'observaciones']
        );

        $folio = $submission->folio ?: ('SAL-' . $submission->id);

        $fecha = $submission->submitted_at
            ? $submission->submitted_at->copy()->timezone('America/Mexico_City')
            : ($submission->created_at
                ? $submission->created_at->copy()->timezone('America/Mexico_City')
                : null);

        return [
            'submission' => $submission,
            'folio' => $folio,
            'fecha' => $fecha?->format('d-m-Y H:i') ?? '-',
            'fecha_sort' => $fecha?->timestamp ?? 0,
            'envia' => (string) ($envia ?? ''),
            'recibe' => (string) ($recibe ?? ''),
            'proyecto' => (string) ($proyecto ?? ''),
            'observaciones' => (string) ($observaciones ?? ''),
        ];
    });

    $needle = $this->normalizeText($this->search ?? '');

    $rows = $rows->filter(function ($row) use ($needle) {
        if ($needle === '') {
            return true;
        }

        $haystack = implode(' ', [
            $row['folio'],
            $row['fecha'],
            $row['envia'],
            $row['recibe'],
            $row['proyecto'],
            $row['observaciones'],
        ]);

        return str_contains($this->normalizeText($haystack), $needle);
    });

    $rows = match ($this->sortField) {
        'folio' => $this->sortDirection === 'asc'
            ? $rows->sortBy(fn ($row) => $this->normalizeText($row['folio']))
            : $rows->sortByDesc(fn ($row) => $this->normalizeText($row['folio'])),

        'fecha' => $this->sortDirection === 'asc'
            ? $rows->sortBy('fecha_sort')
            : $rows->sortByDesc('fecha_sort'),

        'envia' => $this->sortDirection === 'asc'
            ? $rows->sortBy(fn ($row) => $this->normalizeText($row['envia']))
            : $rows->sortByDesc(fn ($row) => $this->normalizeText($row['envia'])),

        'recibe' => $this->sortDirection === 'asc'
            ? $rows->sortBy(fn ($row) => $this->normalizeText($row['recibe']))
            : $rows->sortByDesc(fn ($row) => $this->normalizeText($row['recibe'])),

        'proyecto' => $this->sortDirection === 'asc'
            ? $rows->sortBy(fn ($row) => $this->normalizeText($row['proyecto']))
            : $rows->sortByDesc(fn ($row) => $this->normalizeText($row['proyecto'])),

        'observaciones' => $this->sortDirection === 'asc'
            ? $rows->sortBy(fn ($row) => $this->normalizeText($row['observaciones']))
            : $rows->sortByDesc(fn ($row) => $this->normalizeText($row['observaciones'])),

        default => $rows->sortByDesc('fecha_sort'),
    };

    $rows = $rows->values();

    $page = $this->getPage();
    $total = $rows->count();

    $items = $rows->forPage($page, $this->perPage)->values();

    return new LengthAwarePaginator(
        $items,
        $total,
        $this->perPage,
        $page,
        [
            'path' => request()->url(),
            'pageName' => 'page',
        ]
    );
}

    private function createNewSubmission(): void
    {
        $user = Filament::auth()->user();
        $tenant = Filament::getTenant();

        if (! $this->canCreateSalidas()) {
            Notification::make()
                ->title('No tienes permiso para crear salidas.')
                ->danger()
                ->send();
            return;
        }

        if (! $this->formId || ! $user) {
            Notification::make()
                ->title('No se pudo preparar el formulario de salida.')
                ->danger()
                ->send();

            return;
        }

        $payload = [
            'form_id' => $this->formId,
            'status' => 'BORRADOR',
            'created_by' => $user->id,
            'data' => [],
        ];

        if ($tenant && Schema::hasColumn('form_submissions', 'company_id')) {
            $payload['company_id'] = $tenant->getKey();
        }

        $submission = FormSubmission::create($payload);

        $this->submissionId = $submission->id;
        $this->status = $submission->status ?? 'BORRADOR';
        $this->data = [];
        $this->showForm = true;

        $this->form->fill($this->data);
        $this->dispatch('$refresh');
    }

    public function newSubmission(): void
    {
        $this->createNewSubmission();
    }

    public function backToList(): void
    {
        $this->showForm = false;
        $this->submissionId = null;
        $this->status = 'BORRADOR';
        $this->data = [];
        $this->dispatch('$refresh');
    }

    private function dbStatus(): string
    {
        return FormSubmission::find($this->submissionId)?->status ?? 'BORRADOR';
    }

    public function form(FilamentForm $form): FilamentForm
    {
        if (! $this->showForm || ! $this->formId) {
            return $form->statePath('data')->schema([]);
        }

        $formDef = Form::find($this->formId);
        $schema = is_array($formDef?->schema) ? $formDef->schema : [];
        $schema = $this->applyCatalogOptionsToSchema($schema);

        return $form
            ->statePath('data')
            ->schema(DynamicFormBuilder::form($schema));
    }

protected function getHeaderActions(): array
{
    return [
        Action::make('nuevaSalida')
            ->label('Nueva salida')
            ->color('primary')
            ->visible(fn () => ! $this->showForm && $this->canCreateSalidas())
            ->action('newSubmission'),
    ];
}

protected function getFormActions(): array
    {
        if (! $this->showForm) {
            return [];
        }

        return [
            Action::make('guardar')
                ->label('Guardar borrador')
                ->color('gray')
                ->action('saveDraft')
                ->visible(fn () => $this->canCreateSalidas())
                ->disabled(fn () => ! $this->submissionId || $this->dbStatus() !== 'BORRADOR'),

            Action::make('enviar')
                ->label('Crear salida')
                ->color('primary')
                ->requiresConfirmation()
                ->action('submitAndSendPdf')
                ->visible(fn () => $this->canCreateSalidas())
                ->disabled(fn () => ! $this->submissionId || $this->dbStatus() !== 'BORRADOR'),

            Action::make('reenviarPdfCorreo')
                ->label('Reenviar PDF por correo')
                ->color('success')
                ->requiresConfirmation()
                ->action('sendPdfEmail')
                ->visible(fn () => $this->canSendPdf())
                ->disabled(fn () => ! $this->submissionId),
        ];
    }



    private function currentPdfSlug(?FormSubmission $submission = null): string
    {
        $tenant = Filament::getTenant();

        if (! $tenant && $submission && Schema::hasColumn('form_submissions', 'company_id')) {
            $companyId = $submission->company_id ?? null;

            if ($companyId) {
                $tenant = \App\Models\Company::query()->find($companyId);
            }
        }

        $slug = null;

        if ($tenant) {
            $slug = $tenant->slug ?? null;

            if (! $slug && property_exists($tenant, 'name')) {
                $slug = \Illuminate\Support\Str::slug((string) ($tenant->name ?? ''), '-');
            }
        }

        $slug = trim((string) $slug);

        if ($slug === '') {
            $slug = 'sal';
        }

        return strtoupper($slug);
    }

    private function pdfSequence(FormSubmission $submission): string
    {
        $folio = (string) ($submission->folio ?? '');
        $slug = $this->currentPdfSlug($submission);

        if ($folio !== '') {
            if (preg_match('/^' . preg_quote($slug, '/') . '-(\d+)-\d{8}-\d{6}$/i', $folio, $m)) {
                return $m[1];
            }

            if (preg_match('/^SAL-\d{8}-\d{6}-(\d+)$/i', $folio, $m)) {
                return $m[1];
            }

            if (preg_match('/(\d+)$/', $folio, $m)) {
                return $m[1];
            }
        }

        return (string) $submission->id;
    }

    private function pdfDateTimeTag(FormSubmission $submission): string
    {
        return optional($submission->submitted_at)
            ?->timezone('America/Mexico_City')
            ?->format('Ymd-His') ?? now('America/Mexico_City')->format('Ymd-His');
    }

    private function pdfBaseName(FormSubmission $submission): string
    {
        return sprintf(
            '%s-%s-%s',
            $this->currentPdfSlug($submission),
            $this->pdfSequence($submission),
            $this->pdfDateTimeTag($submission),
        );
    }

    private function resendFromAddress(): string
    {
        $fromName = trim((string) (config('mail.from.name') ?: 'Notificaciones BexiaERP'));
        $fromEmail = trim((string) (config('mail.from.address') ?: 'notificaciones@bexiaerp.com'));

        if ($fromName === '') {
            return $fromEmail;
        }

        return sprintf('%s <%s>', $fromName, $fromEmail);
    }

    private function resendQrUrlForSubmission(FormSubmission $submission): string
    {
        return 'https://quickchart.io/qr?size=180&text=' . rawurlencode($this->pdfUrlFor($submission));
    }

    private function sendPdfViaResend(array $to, FormSubmission $submission, string $pdfRelativePath, ?string $fullPath = null): void
    {
        $apiKey = env('RESEND_API_KEY');

        if (! $apiKey) {
            throw new \RuntimeException('RESEND_API_KEY vacia.');
        }
        $pdfUrl = $this->pdfUrlFor($submission);
        $qrUrl = $this->resendQrUrlForSubmission($submission);

        $absolutePath = $fullPath ?: storage_path('app/public/' . ltrim($pdfRelativePath, '/'));

        if (! is_file($absolutePath)) {
            throw new \RuntimeException("PDF no encontrado: {$absolutePath}");
        }

        $binary = file_get_contents($absolutePath);

        if ($binary === false) {
            throw new \RuntimeException("No se pudo leer el PDF: {$absolutePath}");
        }

        $to = array_values(array_filter(array_map(
            fn ($value) => trim((string) $value),
            $to
        )));

        if ($to === []) {
            throw new \RuntimeException('No hay correos destino para enviar la salida.');
        }

        $safeFolio = htmlspecialchars($this->displayFolioFor($submission), ENT_QUOTES, 'UTF-8');
        $safePdfUrl = htmlspecialchars($pdfUrl, ENT_QUOTES, 'UTF-8');
        $safeQrUrl = htmlspecialchars($qrUrl, ENT_QUOTES, 'UTF-8');

        $html = '<div style="font-family:Arial,sans-serif;font-size:14px;line-height:1.5;color:#111827;">'
            . '<p>Hola,</p>'
            . '<p>Adjunto encontraras el PDF de la salida <strong>' . $safeFolio . '</strong>.</p>'
            . '<p>Puedes ver el PDF aqui:</p>'
            . '<p><a href="' . $safePdfUrl . '">' . $safePdfUrl . '</a></p>'
            . '<p>O escanea este QR para abrir el PDF:</p>'
            . '<p><img src="' . $safeQrUrl . '" alt="QR para ver el PDF" width="180" height="180"></p>'
            . '<p>Saludos,<br>Notificaciones BexiaERP</p>'
            . '</div>';

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->timeout(30)
            ->post('https://api.resend.com/emails', [
                'from' => $this->resendFromAddress(),
                'to' => $to,
                'subject' => 'Salida ' . $this->displayFolioFor($submission),
                'html' => $html,
                'attachments' => [
                    [
                        'filename' => basename($absolutePath),
                        'content' => base64_encode($binary),
                    ],
                ],
            ]);

        Log::info('SALIDAS: Resend API response', [
            'submission_id' => $submission->id,
            'status' => $response->status(),
            'body' => $response->json() ?: $response->body(),
        ]);

        if ($response->failed()) {
            throw new \RuntimeException(
                'Resend API error [' . $response->status() . ']: ' . substr($response->body(), 0, 500)
            );
        }
    }

    public function displayFolioFor(FormSubmission $submission): string
    {
        return $this->pdfBaseName($submission);
    }

    private function syncSubmissionFolio(FormSubmission $submission): void
    {
        $desiredFolio = $this->displayFolioFor($submission);

        if (($submission->folio ?? null) !== $desiredFolio) {
            $submission->forceFill([
                'folio' => $desiredFolio,
            ])->save();

            $submission->refresh();
        }
    }

    public function pdfUrlFor(FormSubmission $submission): string
    {
        $filename = $this->displayFolioFor($submission) . '.pdf';

        return asset('storage/pdf/' . str_replace('%2F', '/', rawurlencode($filename)));
    }

    public function viewSubmission(int $submissionId): void
    {
        $submission = FormSubmission::with('form')->find($submissionId);

        if (! $submission) {
            Notification::make()
                ->title('No se encontró la salida.')
                ->danger()
                ->send();
            return;
        }

        if (! $this->canManageAllPdfs() && (int) $submission->created_by !== (int) Filament::auth()->id()) {
            Notification::make()
                ->title('No puedes ver esta salida.')
                ->danger()
                ->send();
            return;
        }

        $this->submissionId = $submission->id;
        $this->formId = $submission->form_id;
        $this->status = (string) ($submission->status ?? 'BORRADOR');
        $this->data = is_array($submission->data ?? null) ? $submission->data : [];
        $this->showForm = true;

        $this->form->fill($this->data);
        $this->dispatch('$refresh');
    }

    private function persistCurrentStateToSubmission(FormSubmission $submission): void
    {
        $state = $this->form->getState();
        $state = is_array($state) ? $state : [];

        $this->data = $state;

        $submission->update([
            'data' => $this->data,
        ]);
    }

    public function saveDraft(): void
    {
        $submission = FormSubmission::find($this->submissionId);

        if (! $submission) {
            Notification::make()
                ->title('No se encontró el borrador.')
                ->danger()
                ->send();
            return;
        }

        if ($submission->status !== 'BORRADOR') {
            Notification::make()
                ->title('Ya fue enviada.')
                ->danger()
                ->send();
            return;
        }

        if (! $this->canManageAllPdfs() && (int) $submission->created_by !== (int) Filament::auth()->id()) {
            Notification::make()
                ->title('No puedes modificar esta salida.')
                ->danger()
                ->send();
            return;
        }

        $this->persistCurrentStateToSubmission($submission);

        Notification::make()
            ->title('Borrador guardado')
            ->success()
            ->send();
    }

    public function submit(): void
    {
        $submission = FormSubmission::find($this->submissionId);

        if (! $submission) {
            Notification::make()
                ->title('No se encontró el borrador.')
                ->danger()
                ->send();
            return;
        }

        if ($submission->status !== 'BORRADOR') {
            Notification::make()
                ->title('Ya fue enviada.')
                ->danger()
                ->send();
            return;
        }

        if (! $this->canManageAllPdfs() && (int) $submission->created_by !== (int) Filament::auth()->id()) {
            Notification::make()
                ->title('No puedes enviar esta salida.')
                ->danger()
                ->send();
            return;
        }

        $this->persistCurrentStateToSubmission($submission);

        $folio = $submission->folio ?: ('SAL-' . now()->format('Ymd-His') . '-' . random_int(100, 999));

        $submission->update([
            'folio' => $folio,
            'status' => 'ENVIADA',
            'submitted_at' => now(),
            'data' => $this->data,
        ]);

        $this->status = 'ENVIADA';
        $this->dispatch('$refresh');

        Notification::make()
            ->title('Enviada: ' . $this->displayFolioFor($submission))
            ->success()
            ->send();
    }

    public function submitAndSendPdf(): void
    {
        $this->submit();

        if ($this->dbStatus() !== 'ENVIADA') {
            return;
        }

        if ($this->canSendPdf()) {
            $this->sendPdfEmail();
        }

        $this->showForm = false;
        $this->dispatch('$refresh');
    }

    public function sendPdfEmail(): void
    {
        if (! $this->canSendPdf()) {
            Notification::make()
                ->title('No tienes permiso para enviar PDFs.')
                ->danger()
                ->send();
            return;
            
        }

        Log::info('SALIDAS: CLICK sendPdfEmail', [
            'submissionId' => $this->submissionId,
            'db_status' => $this->dbStatus(),
        ]);

        $submission = FormSubmission::with('form')->find($this->submissionId);

        if (! $submission) {
            Notification::make()
                ->title('No se encontró la salida.')
                ->danger()
                ->send();
            return;
        }

        if (! $this->canManageAllPdfs() && (int) $submission->created_by !== (int) Filament::auth()->id()) {
            Notification::make()
                ->title('No puedes enviar el PDF de esta salida.')
                ->danger()
                ->send();
            return;
        }

        if (! $submission->form) {
            Notification::make()
                ->title('No se encontró el formulario asociado.')
                ->danger()
                ->send();
            return;
        }

        $this->persistCurrentStateToSubmission($submission);
        $submission->refresh();

        if (empty($submission->folio)) {
            $submission->update([
                'folio' => 'SAL-' . now()->format('Ymd-His') . '-' . random_int(100, 999),
            ]);

            $submission->refresh();
        }

        $data = is_array($submission->data) ? $submission->data : [];

        $to = collect([
            $data['envia_email'] ?? $data['envia_correo'] ?? null,
            $data['recibe_email'] ?? $data['recibe_correo'] ?? null,
            $data['autoriza_email'] ?? $data['autoriza_correo'] ?? null,
        ])->filter()->unique()->values();

        if ($to->isEmpty()) {
            Notification::make()
                ->title('No hay correos (envía/recibe/autoriza).')
                ->danger()
                ->send();
            return;
        }

        try {
            if (! app()->bound('dompdf.wrapper')) {
                throw new \RuntimeException('No hay motor PDF instalado (barryvdh/laravel-dompdf).');
            }

            $schemaRaw = $submission->form->schema ?? [];
            $schemaArray = is_array($schemaRaw) ? $schemaRaw : (json_decode($schemaRaw, true) ?: []);

            Log::info('SALIDAS: Generando PDF', [
                'submission_id' => $submission->id,
                'folio' => $submission->folio,
                'to' => $to->all(),
            ]);

            $pdf = app('dompdf.wrapper')->loadView('pdfs.salidas', [
                'folioDisplay' => $this->displayFolioFor($submission),
                'submission' => $submission,
                'form' => $submission->form,
                'schemaArray' => $schemaArray,
            ]);

            $binary = $pdf->output();
            $bytes = strlen($binary);

            if ($bytes < 200) {
                throw new \RuntimeException("PDF demasiado pequeño ({$bytes} bytes).");
            }

            $fecha = isset($data['fecha_salida'])
                ? \Carbon\Carbon::parse($data['fecha_salida'])->format('d-m-Y')
                : now()->format('d-m-Y');

            $proyecto = $data['proyecto'] ?? 'Sin Proyecto';
            $proyecto = preg_replace('/[\/\\\\\:\*\?\"\<\>\|]/', '', $proyecto);
            $proyecto = trim(preg_replace('/\s+/', ' ', $proyecto));

            if ($proyecto === '') {
                $proyecto = 'Sin Proyecto';
            }

            $folioCorto = 'SAL-' . (preg_match('/(\d+)$/', (string) $submission->folio, $m) ? $m[1] : $submission->id);

            $this->syncSubmissionFolio($submission);

            $filename = $this->pdfBaseName($submission) . '.pdf';
            $pdfRelativePath = 'pdf/' . $filename;

            Storage::disk('public')->makeDirectory('pdf');
            Storage::disk('public')->put($pdfRelativePath, $binary);

            $fullPath = Storage::disk('public')->path($pdfRelativePath);

            Log::info('SALIDAS: PDF guardado', [
                'pdfRelativePath' => $pdfRelativePath,
                'fullPath' => $fullPath,
                'bytes' => $bytes,
            ]);

            Log::info('SALIDAS: Antes de Resend API', [
                'to' => $to,
                'bytes' => $bytes,
                'pdfRelativePath' => $pdfRelativePath,
            ]);

            $this->sendPdfViaResend(
                to: collect($to)->filter()->values()->all(),
                submission: $submission,
                pdfRelativePath: $pdfRelativePath,
                fullPath: $fullPath ?? null,
            );

            Log::info('SALIDAS: Resend API enviado OK', [
                'to' => $to,
                'submission_id' => $submission->id,
                'pdfRelativePath' => $pdfRelativePath,
            ]);

            Notification::make()
                ->title('PDF enviado')
                ->body("Guardado en: storage/app/public/{$pdfRelativePath}")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Log::error('SALIDAS: sendPdfEmail FAIL', [
                'submissionId' => $this->submissionId,
                'error' => $e->getMessage(),
            ]);

            Notification::make()
                ->title('Falló el envío de PDF')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
public function deleteSubmission(int $submissionId): void
{
    $submission = FormSubmission::find($submissionId);

    if (! $submission) {
        Notification::make()
            ->title('No se encontró la salida.')
            ->danger()
            ->send();
        return;
    }

    if (! $this->canDeleteSalidas()) {
        Notification::make()
            ->title('No tienes permiso para eliminar salidas.')
            ->danger()
            ->send();
        return;
    }

    if (! $this->canManageAllPdfs() && (int) $submission->created_by !== (int) Filament::auth()->id()) {
        Notification::make()
            ->title('No puedes eliminar esta salida.')
            ->danger()
            ->send();
        return;
    }

    $data = is_array($submission->data) ? $submission->data : [];

    // 1) Borrar PDF generado
    $fecha = isset($data['fecha_salida'])
        ? \Carbon\Carbon::parse($data['fecha_salida'])->format('d-m-Y')
        : ($submission->submitted_at
            ? $submission->submitted_at->copy()->timezone('America/Mexico_City')->format('d-m-Y')
            : now()->format('d-m-Y'));

    $proyecto = $data['proyecto'] ?? 'Sin Proyecto';
    $proyecto = preg_replace('/[\/\\\\\:\*\?\"\<\>\|]/', '', $proyecto);
    $proyecto = trim(preg_replace('/\s+/', ' ', $proyecto));

    if ($proyecto === '') {
        $proyecto = 'Sin Proyecto';
    }

    $folioCorto = 'SAL-' . (preg_match('/(\d+)$/', (string) $submission->folio, $m) ? $m[1] : $submission->id);
    $pdfFilename = "{$folioCorto} - {$proyecto} - {$fecha}.pdf";
    $pdfRelativePath = 'pdf/' . $pdfFilename;

    if (Storage::disk('public')->exists($pdfRelativePath)) {
        Storage::disk('public')->delete($pdfRelativePath);
    }

    // 2) Borrar fotos y otros archivos subidos
    $deleteFilesRecursively = function ($value) use (&$deleteFilesRecursively) {
        if (is_array($value)) {
            foreach ($value as $item) {
                $deleteFilesRecursively($item);
            }
            return;
        }

        if (! is_string($value)) {
            return;
        }

        $path = trim($value);

        if ($path === '') {
            return;
        }

        $looksLikeUpload =
            str_starts_with($path, 'salidas/') ||
            str_starts_with($path, 'storage/salidas/') ||
            str_starts_with($path, '/storage/salidas/') ||
            str_starts_with($path, 'pdf/') ||
            preg_match('/\.(jpg|jpeg|png|gif|webp|pdf)$/i', $path);

        if (! $looksLikeUpload) {
            return;
        }

        $candidates = [
            $path,
            ltrim($path, '/'),
            str_starts_with($path, 'storage/') ? substr($path, 8) : $path,
            str_starts_with($path, '/storage/') ? substr($path, 9) : $path,
        ];

        foreach ($candidates as $candidate) {
            if (Storage::disk('public')->exists($candidate)) {
                Storage::disk('public')->delete($candidate);
                break;
            }
        }
    };

    $deleteFilesRecursively($data);

    // 3) Borrar registro de BD
    $submission->delete();

    Notification::make()
        ->title('Salida eliminada')
        ->success()
        ->send();

    $this->dispatch('$refresh');
}
public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('salidas.ver')
                || $user?->can('salidas.ver_todas')
                || $user?->can('salidas.configurar')
            );
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return auth()->check()
            && (
                $user?->can('salidas.ver')
                || $user?->can('salidas.ver_todas')
                || $user?->can('salidas.configurar')
            );
    }

}