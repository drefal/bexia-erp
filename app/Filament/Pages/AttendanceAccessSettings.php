<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Company;
use App\Models\User;
use App\Support\Attendance\AttendanceAccessPolicyService as S;
use App\Support\Attendance\AttendanceAccessCoverage;
use App\Support\Navigation\BexiaMenuRuntime;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Locked;
use Throwable;

class AttendanceAccessSettings extends Page
{
    protected static ?string $navigationIcon =
        'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'RRHH';

    protected static ?string $navigationLabel =
        'Control de acceso';

    protected static ?string $title =
        'Configuracion de control de acceso';

    protected static ?int $navigationSort = 48;

    protected static ?string $slug =
        'configuracion-control-acceso';

    protected static string $view =
        'filament.pages.attendance-access-settings';

    #[Locked]
    public int $pageCompanyId = 0;

    #[Locked]
    public string $loadedScope = '';

    #[Locked]
    public string $policyToken = '';

    #[Locked]
    public string $policySource = 'default';

    #[Locked]
    public ?int $policyId = null;

    #[Locked]
    public array $originalValues = [];

    #[Locked]
    public ?array $confirmedValues = null;

    public string $scope = 'company';

    public array $policy = [];

    public string $changeReason = '';

    #[Locked]
    public ?array $coverageResult = null;

    #[Locked]
    public ?array $coverageValues = null;

    public string $coverageFilter = 'all';

    public string $coverageSearch = '';

    public static function canAccess(): bool
    {
        $actor = auth()->user();

        $company = Filament::getTenant();

        return $actor instanceof User
            && $company instanceof Company
            && S::canConfigure($actor, $company)
            && Schema::hasTable('hr_access_policies')
            && Schema::hasTable('hr_access_events');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess()
            && BexiaMenuRuntime::shouldRegister(
                'pages.attendanceaccesssettings',
                fn (): bool => true
            );
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->pageCompanyId =
            (int) Filament::getTenant()->id;

        $this->restoreRememberedScope();

        $this->loadPolicy();
    }

    private function identity(): array
    {
        abort_unless(static::canAccess(), 403);

        $company = Filament::getTenant();

        abort_unless(
            (int) $company->id === $this->pageCompanyId,
            403
        );

        return [auth()->user(), $company];
    }

    private function scopeSessionKey(): string
    {
        [$actor, $company] = $this->identity();

        return 'bexia.rrhh.access_settings.scope.'
            . (int) $actor->id
            . '.'
            . (int) $company->id;
    }

    private function restoreRememberedScope(): void
    {
        $key = $this->scopeSessionKey();

        $remembered = session()->get(
            $key,
            'company'
        );

        if ($remembered === 'company') {
            $this->scope = 'company';

            return;
        }

        if (
            is_string($remembered)
            && ctype_digit($remembered)
            && (int) $remembered > 0
            && array_key_exists(
                (int) $remembered,
                $this->branches()
            )
        ) {
            $this->scope =
                (string) (int) $remembered;

            return;
        }

        session()->forget($key);

        $this->scope = 'company';
    }

    private function rememberScope(): void
    {
        $key = $this->scopeSessionKey();

        // Validate the scope again before persisting it.
        $this->branchId();

        session()->put(
            $key,
            $this->scope
        );
    }

    private function branches(): array
    {
        $this->identity();

        return DB::table('branches')
            ->where('company_id', $this->pageCompanyId)
            ->where('active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    private function branchId(): ?int
    {
        if ($this->scope === 'company') {
            return null;
        }

        abort_unless(
            ctype_digit($this->scope)
            && (int) $this->scope > 0
            && array_key_exists(
                (int) $this->scope,
                $this->branches()
            ),
            403
        );

        return (int) $this->scope;
    }

    private function loadPolicy(): void
    {
        $this->identity();

        $branchId = $this->branchId();

        $state = S::read(
            $this->pageCompanyId,
            $branchId
        );

        $this->clearCoverage();

        $this->loadedScope = $this->scope;
        $this->policyToken = $state['token'];
        $this->policySource = $state['source'];
        $this->policyId = $state['policy_id'];

        $this->originalValues = $state['values'];

        $this->policy = $state['values'];

        $this->changeReason = '';
        $this->confirmedValues = null;

        $this->resetValidation();

        // Refresh coverage whenever a saved branch policy
        // is loaded: mount, scope change, reload or save.
        if ($branchId !== null) {
            $this->calculateCoverage();
        }
    }

    public function updatedScope(): void
    {
        $this->identity();

        $this->dispatch(
            'close-modal',
            id: 'hr-access-policy-confirm'
        );

        $this->loadPolicy();

        $this->rememberScope();
    }

    public function reloadPolicy(): void
    {
        $this->identity();

        $this->dispatch(
            'close-modal',
            id: 'hr-access-policy-confirm'
        );

        $this->loadPolicy();

        Notification::make()
            ->title('Configuracion actualizada')
            ->body('Se cargaron los valores guardados.')
            ->success()
            ->send();
    }

    private function normalizedInput(): array
    {
        $rules = [
            'policy' => [
                'required',
                'array:' . implode(',', S::fields()),
            ],
        ];

        foreach (S::BOOLEAN_FIELDS as $field) {
            $rules['policy.' . $field] = [
                'required',
                'boolean',
            ];
        }

        foreach (S::MINUTE_FIELDS as $field) {
            $rules['policy.' . $field] = [
                'present',
                'nullable',
                'integer',
                'min:0',
                'max:1440',
            ];
        }

        $validated = $this->validate($rules);

        $data = $validated['policy'];

        foreach (S::BOOLEAN_FIELDS as $field) {
            $data[$field] = (bool) $data[$field];
        }

        foreach (S::MINUTE_FIELDS as $field) {

            $value = $data[$field];

            $data[$field] =
                $value === null || $value === ''
                    ? null
                    : (int) $value;
        }

        return S::normalize($data);
    }

    private function clearCoverage(): void
    {
        $this->coverageResult = null;
        $this->coverageValues = null;
        $this->coverageFilter = 'all';
        $this->coverageSearch = '';
    }

    public function updatedPolicy(): void
    {
        $this->identity();
        $this->clearCoverage();
        $this->confirmedValues = null;
        $this->changeReason = '';
    }

    public function calculateCoverage(): void
    {
        $this->identity();

        abort_unless(
            $this->scope === $this->loadedScope,
            403
        );

        $branchId = $this->branchId();

        $this->clearCoverage();

        if ($branchId === null) {

            Notification::make()
                ->title('Selecciona una sucursal')
                ->body(
                    'La cobertura se evalua por '
                    . 'sucursal de ingreso.'
                )
                ->warning()
                ->send();

            return;
        }

        $data = $this->normalizedInput();

        try {

            $result = AttendanceAccessCoverage::report(
                $this->pageCompanyId,
                $branchId,
                $data
            );

        } catch (AuthorizationException) {

            abort(403);

        } catch (Throwable $e) {

            report($e);

            Notification::make()
                ->title('No se pudo calcular la cobertura')
                ->warning()
                ->send();

            return;
        }

        $this->coverageValues = $data;

        $this->coverageResult = [
            'company_id' => $result['company_id'],
            'branch_id' => $result['branch_id'],
            'summary' => $result['summary'],
            'elapsed_ms' => $result['elapsed_ms'],
            'calculated_at' => now(
                'America/Mexico_City'
            )->format('d/m/Y H:i:s'),
            'rows' => array_map(
                static fn (array $row): array => [
                    'employee_id' => $row['employee_id'],
                    'employee_name' => $row['employee_name'],
                    'scope' => $row['scope'],
                    'qr_enabled' => $row['qr_enabled'],
                    'manager_name' => $row['manager_name'],
                    'status' => $row['status'],
                    'reason_code' => $row['reason_code'],
                    'assignment_source' =>
                        $row['assignment_source'],
                ],
                $result['rows']
            ),
        ];
    }

    private function filteredCoverageRows(): array
    {
        if (
            $this->coverageResult === null
            || $this->coverageValues === null
            || $this->loadedScope !== $this->scope
        ) {
            return [];
        }

        $filter = $this->coverageFilter;

        $search = mb_strtolower(
            mb_substr(
                trim($this->coverageSearch),
                0,
                100
            )
        );

        return array_values(array_filter(
            $this->coverageResult['rows'],
            static function (array $row) use (
                $filter,
                $search
            ): bool {

                $matches = match ($filter) {
                    'covered' =>
                        $row['status'] === 'covered',

                    'uncovered' =>
                        $row['status'] === 'uncovered',

                    'without_user' =>
                        $row['reason_code']
                            === 'manager_without_user',

                    'without_branch' =>
                        $row['scope'] === 'without_branch',

                    'qr_uncovered' =>
                        $row['qr_enabled']
                        && $row['status'] === 'uncovered',

                    'all' => true,

                    default => false,
                };

                if (!$matches) {
                    return false;
                }

                if ($search === '') {
                    return true;
                }

                $text = mb_strtolower(
                    $row['employee_name'] . ' '
                    . ($row['manager_name'] ?? '')
                );

                return str_contains($text, $search);
            }
        ));
    }

    private function warn(string $message): void
    {
        $label = match ($message) {
            'RETENTION_ACTIVATION_NOT_READY' =>
                'La activacion de retenciones aun no esta habilitada.',

            'ABSENCE_INTEGRATION_PENDING' =>
                'La integracion de ausencias esta pendiente.',

            'AT_LEAST_ONE_AUTHORITY_REQUIRED' =>
                'Selecciona al menos un tipo de autorizador.',

            'STALE_POLICY_RELOAD_REQUIRED' =>
                'La politica cambio. Recarga antes de guardar.',

            'PENDING_ACCESS_REQUESTS_EXIST' =>
                'Existen solicitudes pendientes de resolucion.',

            'INVALID_CHANGE_REASON' =>
                'Indica un motivo valido de 5 a 1000 caracteres.',

            default =>
                'No fue posible guardar la configuracion.',
        };

        Notification::make()
            ->title($label)
            ->warning()
            ->send();
    }

    public function prepareSave(): void
    {
        $this->identity();
        $this->branchId();

        abort_unless(
            $this->scope === $this->loadedScope,
            403
        );

        $this->confirmedValues = null;

        $data = $this->normalizedInput();

        try {

            S::validateActivation($data);

        } catch (\RuntimeException $e) {

            $this->warn($e->getMessage());

            return;
        }

        if (
            $this->policyId !== null
            && $data === $this->originalValues
        ) {

            Notification::make()
                ->title('No hay cambios por guardar')
                ->info()
                ->send();

            return;
        }

        $this->confirmedValues = $data;
        $this->changeReason = '';

        $this->resetValidation();

        $this->dispatch(
            'open-modal',
            id: 'hr-access-policy-confirm'
        );
    }

    public function cancelSave(): void
    {
        $this->identity();

        $this->confirmedValues = null;
        $this->changeReason = '';

        $this->resetValidation();

        $this->dispatch(
            'close-modal',
            id: 'hr-access-policy-confirm'
        );
    }

    public function savePolicy(): void
    {
        $this->identity();

        $branchId = $this->branchId();

        abort_unless(
            $this->confirmedValues !== null
            && $this->scope === $this->loadedScope,
            403
        );

        $this->validate([
            'changeReason' => [
                'required',
                'string',
                'min:5',
                'max:1000',
            ],
        ]);

        $data = $this->normalizedInput();

        if ($data !== $this->confirmedValues) {

            $this->cancelSave();

            Notification::make()
                ->title('La configuracion del formulario cambio')
                ->body('Revisa los valores y confirma nuevamente.')
                ->warning()
                ->send();

            return;
        }

        try {

            $result = S::save(
                $this->pageCompanyId,
                $branchId,
                $this->confirmedValues,
                $this->policyToken,
                trim($this->changeReason)
            );

        } catch (AuthorizationException) {

            abort(403);

        } catch (\RuntimeException $e) {

            $known = [
                'RETENTION_ACTIVATION_NOT_READY',
                'ABSENCE_INTEGRATION_PENDING',
                'AT_LEAST_ONE_AUTHORITY_REQUIRED',
                'STALE_POLICY_RELOAD_REQUIRED',
                'PENDING_ACCESS_REQUESTS_EXIST',
            ];

            if (!in_array(
                $e->getMessage(),
                $known,
                true
            )) {
                report($e);
            }

            $this->warn($e->getMessage());

            return;

        } catch (\InvalidArgumentException $e) {

            $this->warn($e->getMessage());

            return;

        } catch (Throwable $e) {

            report($e);

            $this->warn('UNKNOWN_ERROR');

            return;
        }

        $this->cancelSave();

        $this->loadPolicy();

        Notification::make()
            ->title(
                $result['changed']
                    ? 'Configuracion guardada'
                    : 'Sin cambios'
            )
            ->success()
            ->send();
    }

    protected function getViewData(): array
    {
        [, $company] = $this->identity();

        $branches = $this->branches();

        $labels = [
            'company' => 'Configuracion general',
            'branch' => 'Configuracion propia',
            'inherited' => 'Configuracion heredada',
            'default' => 'Sin configuracion guardada',
        ];

        return [
            'companyName' => $company->name,
            'branches' => $branches,
            'coverageRows' =>
                $this->filteredCoverageRows(),
            'sourceLabel' =>
                $labels[$this->policySource]
                    ?? 'Sin configuracion',
            'scopeLabel' => $this->scope === 'company'
                ? 'Toda la empresa'
                : ($branches[(int) $this->scope] ?? ''),
        ];
    }
}
