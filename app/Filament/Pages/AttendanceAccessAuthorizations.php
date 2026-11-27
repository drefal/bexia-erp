<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Support\Attendance\AttendanceAccessAuthorization as A;
use App\Support\Attendance\AttendanceAccessPermissionResolver as P;
use App\Support\Attendance\AttendanceAccessPolicyResolver;
use App\Support\Attendance\AttendanceAccessPriorAuthorizationService;
use App\Support\Navigation\BexiaMenuRuntime;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Attributes\Locked;
use Carbon\CarbonImmutable;

class AttendanceAccessAuthorizations extends Page
{
    protected static ?string $navigationIcon =
        'heroicon-o-shield-check';

    protected static ?string $navigationGroup =
        'RRHH';

    protected static ?string $navigationLabel =
        'Autorizaciones de acceso';

    protected static ?string $title =
        'Autorizaciones de acceso';

    protected static ?int $navigationSort = 46;

    protected static ?string $slug =
        'autorizaciones-acceso';

    protected static string $view =
        'filament.pages.attendance-access-authorizations';

    #[Locked]
    public int $pageCompanyId = 0;

    public string $branchId = '';

    public string $employeeId = '';

    public string $validFrom = '';

    public string $validUntil = '';

    public string $reason = '';

    public string $search = '';

    public string $statusFilter = 'all';

    protected static function currentIdentity(): ?array
    {
        $actor = auth()->user();

        $tenant = Filament::getTenant();

        if (
            !$actor instanceof User
            || !$tenant instanceof Company
            || !$tenant->active
            || !$actor->canAccessTenant($tenant)
        ) {
            return null;
        }

        $permissions = P::permissionsFor(
            $actor,
            $tenant
        );

        if (
            !in_array(A::VIEW, $permissions, true)
            || !array_intersect(
                $permissions,
                [A::MANAGER, A::DEPARTMENT, A::HR]
            )
        ) {
            return null;
        }

        return [
            'actor' => $actor,
            'company' => $tenant,
            'permissions' => $permissions,
        ];
    }

    public static function canAccess(): bool
    {
        if (!static::currentIdentity()) {
            return false;
        }

        return Schema::hasTable('hr_access_policies')
            && Schema::hasTable(
                'hr_access_authorizations'
            );
    }

    public static function shouldRegisterNavigation(): bool
    {
        if (!static::canAccess()) {
            return false;
        }

        return BexiaMenuRuntime::shouldRegister(
            'pages.attendanceaccessauthorizations',
            fn (): bool => true
        );
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->pageCompanyId = (int)
            Filament::getTenant()->getKey();

        $now = CarbonImmutable::now(
            'America/Mexico_City'
        )->addMinutes(10);

        $this->validFrom =
            $now->format('Y-m-d\TH:i');

        $this->validUntil =
            $now->addHour()->format('Y-m-d\TH:i');
    }

    protected function authorizedIdentity(): array
    {
        $identity = static::currentIdentity();

        abort_unless(
            $identity !== null
            && $this->pageCompanyId > 0
            && (int) $identity['company']->getKey()
                === $this->pageCompanyId,
            403
        );

        abort_unless(
            Schema::hasTable('hr_access_policies')
            && Schema::hasTable(
                'hr_access_authorizations'
            ),
            403
        );

        return $identity;
    }

    protected function scopedEmployees(
        array $identity,
        ?array $policy = null
    ) {
        $companyId = (int)
            $identity['company']->getKey();

        $actorId = (int)
            $identity['actor']->getKey();

        $permissions = $identity['permissions'];

        $allowHr =
            in_array(A::HR, $permissions, true)
            && (
                $policy === null
                || $policy['allow_hr_approval']
            );

        $allowManager =
            in_array(A::MANAGER, $permissions, true)
            && (
                $policy === null
                || $policy['allow_manager_approval']
            );

        $allowDepartment =
            in_array(
                A::DEPARTMENT,
                $permissions,
                true
            )
            && (
                $policy === null
                || $policy['allow_department_approval']
            );

        $query = Employee::query()
            ->where('company_id', $companyId);

        if ($policy !== null) {

            $query->where('active', true);

            $query->where(function ($q) use ($actorId) {
                $q->whereNull('user_id')
                    ->orWhere('user_id', '!=', $actorId);
            });

            if (!$policy['policy_enabled']) {
                return $query->whereRaw('1 = 0');
            }
        }

        if ($allowHr) {
            return $query;
        }

        $managerIds = DB::table('employees')
            ->where('company_id', $companyId)
            ->where('user_id', $actorId)
            ->where('active', true)
            ->select('id');

        $query->where(function ($q) use (
            $allowManager,
            $allowDepartment,
            $managerIds,
            $companyId
        ) {
            $q->whereRaw('1 = 0');

            if ($allowManager) {
                $q->orWhereIn(
                    'manager_employee_id',
                    clone $managerIds
                );
            }

            if ($allowDepartment) {

                $departments = DB::table(
                    'hr_departments'
                )
                    ->where('company_id', $companyId)
                    ->where('is_active', true)
                    ->whereIn(
                        'manager_employee_id',
                        clone $managerIds
                    )
                    ->select('id');

                $q->orWhereIn(
                    'hr_department_id',
                    $departments
                );
            }
        });

        return $query;
    }

    protected function branchOptions(
        array $identity
    ): array {
        return DB::table('branches')
            ->where(
                'company_id',
                $identity['company']->getKey()
            )
            ->where('active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    protected function selectedPolicy(
        array $identity
    ): array {
        $branches = $this->branchOptions($identity);

        if (
            !ctype_digit($this->branchId)
            || !array_key_exists(
                (int) $this->branchId,
                $branches
            )
        ) {
            return AttendanceAccessPolicyResolver
                ::defaults();
        }

        return AttendanceAccessPolicyResolver::resolve(
            $this->pageCompanyId,
            (int) $this->branchId
        );
    }

    public function updatedBranchId(): void
    {
        $this->authorizedIdentity();

        $this->employeeId = '';
    }

    protected function getViewData(): array
    {
        $identity = $this->authorizedIdentity();

        $branches = $this->branchOptions(
            $identity
        );

        $policy = $this->selectedPolicy(
            $identity
        );

        $employeeOptions = $this->scopedEmployees(
            $identity,
            $policy
        )
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        // El alcance del historial se determina
        // mediante la jerarquia actual.
        $employeeScope = $this->scopedEmployees(
            $identity
        )->select('employees.id');

        $query = DB::table(
            'hr_access_authorizations as a'
        )
            ->join('employees as e', function ($join) {
                $join->on('e.id', '=', 'a.employee_id')
                    ->on(
                        'e.company_id',
                        '=',
                        'a.company_id'
                    );
            })
            ->leftJoin('branches as b', function ($join) {
                $join->on('b.id', '=', 'a.branch_id')
                    ->on(
                        'b.company_id',
                        '=',
                        'a.company_id'
                    );
            })
            ->where(
                'a.company_id',
                $this->pageCompanyId
            )
            ->whereIn(
                'a.employee_id',
                $employeeScope
            )
            // Las restricciones se administraran
            // en un flujo separado.
            ->where('a.type', 'allow');

        $search = mb_substr(
            trim($this->search),
            0,
            100
        );

        if ($search !== '') {
            $query->where(
                'e.name',
                'ilike',
                '%' . $search . '%'
            );
        }

        $now = CarbonImmutable::now();

        if ($this->statusFilter === 'current') {

            $query->where('a.status', 'approved')
                ->whereNull('a.revoked_at')
                ->where('a.valid_from', '<=', $now)
                ->where('a.valid_until', '>=', $now);

        } elseif ($this->statusFilter === 'future') {

            $query->where('a.status', 'approved')
                ->whereNull('a.revoked_at')
                ->where('a.valid_from', '>', $now);

        } elseif ($this->statusFilter === 'expired') {

            $query->where('a.status', 'approved')
                ->where('a.valid_until', '<', $now);

        } elseif ($this->statusFilter === 'revoked') {

            $query->where('a.status', 'revoked');
        }

        $rows = $query
            ->orderByDesc('a.id')
            ->limit(50)
            ->get([
                'a.id',
                'a.status',
                'a.valid_from',
                'a.valid_until',
                'a.revoked_at',
                'e.name as employee_name',
                'b.name as branch_name',
            ])
            ->map(function ($row) use ($now): array {

                $from = CarbonImmutable::parse(
                    $row->valid_from
                );

                $until = CarbonImmutable::parse(
                    $row->valid_until
                );

                $label = match (true) {
                    $row->status === 'revoked'
                        || $row->revoked_at !== null
                            => 'Revocada',

                    $row->status === 'rejected'
                            => 'Rechazada',

                    $row->status !== 'approved'
                            => 'Pendiente',

                    $until->lt($now)
                            => 'Vencida',

                    $from->gt($now)
                            => 'Programada',

                    default => 'Vigente',
                };

                return [
                    'id' => (int) $row->id,
                    'employee' => $row->employee_name,
                    'branch' => $row->branch_name
                        ?? 'General',

                    'from' => $from
                        ->timezone(
                            'America/Mexico_City'
                        )
                        ->format('d/m/Y H:i'),

                    'until' => $until
                        ->timezone(
                            'America/Mexico_City'
                        )
                        ->format('d/m/Y H:i'),

                    'status' => $label,
                ];
            })
            ->all();

        return [
            'companyName' => $identity['company']->name,
            'branches' => $branches,
            'employeeOptions' => $employeeOptions,
            'policyEnabled' =>
                $policy['policy_enabled'],
            'accessRows' => $rows,
        ];
    }

    public function createAuthorization(): void
    {
        $identity = $this->authorizedIdentity();

        $data = $this->validate([
            'branchId' => [
                'required',
                'integer',
                'min:1',
            ],
            'employeeId' => [
                'required',
                'integer',
                'min:1',
            ],
            'validFrom' => [
                'required',
                'date_format:Y-m-d\TH:i',
            ],
            'validUntil' => [
                'required',
                'date_format:Y-m-d\TH:i',
            ],
            'reason' => [
                'required',
                'string',
                'min:5',
                'max:1000',
            ],
        ]);

        $branches = $this->branchOptions(
            $identity
        );

        abort_unless(
            array_key_exists(
                (int) $data['branchId'],
                $branches
            ),
            403
        );

        $policy = $this->selectedPolicy(
            $identity
        );

        abort_unless(
            $this->scopedEmployees(
                $identity,
                $policy
            )
                ->whereKey(
                    (int) $data['employeeId']
                )
                ->exists(),
            403
        );

        try {

            $result = app(
                AttendanceAccessPriorAuthorizationService
                    ::class
            )->create(
                $this->pageCompanyId,
                (int) $data['branchId'],
                (int) $data['employeeId'],
                str_replace(
                    'T',
                    ' ',
                    $data['validFrom']
                ) . ':00',
                str_replace(
                    'T',
                    ' ',
                    $data['validUntil']
                ) . ':00',
                trim($data['reason'])
            );

        } catch (AuthorizationException $e) {

            Notification::make()
                ->title(
                    'No tienes facultades para autorizar'
                )
                ->body(
                    'Verifica la jerarquia, permisos '
                    . 'y restricciones vigentes.'
                )
                ->danger()
                ->send();

            return;

        } catch (\InvalidArgumentException $e) {

            Notification::make()
                ->title('Verifica la vigencia y el motivo')
                ->warning()
                ->send();

            return;

        } catch (\Throwable $e) {

            if (
                $e->getMessage()
                === 'OVERLAPPING_AUTHORIZATION_EXISTS'
            ) {
                Notification::make()
                    ->title(
                        'Ya existe una autorizacion '
                        . 'para ese periodo'
                    )
                    ->warning()
                    ->send();

                return;
            }

            report($e);

            Notification::make()
                ->title(
                    'No se pudo registrar la autorizacion'
                )
                ->danger()
                ->send();

            return;
        }

        $this->employeeId = '';
        $this->reason = '';

        Notification::make()
            ->title('Autorizacion registrada')
            ->body(
                'Folio #' .
                $result['authorization_id']
            )
            ->success()
            ->send();
    }
}
