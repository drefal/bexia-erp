<?php

namespace App\Filament\Pages;

use App\Models\Employee;
use Illuminate\Support\Facades\Storage;
use Filament\Facades\Filament;
use Filament\Pages\Page;

class EmployeeOrganizationChart extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'RRHH';

    protected static ?string $navigationLabel = 'Organigrama';

    protected static ?string $title = 'Organigrama';

    protected static ?int $navigationSort = 20;

    protected static string $view = 'filament.pages.employee-organization-chart';

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return (bool) ($user->is_system_admin ?? false)
            || ($user->email ?? null) === 'admin@bexiaerp.com'
            || $user->can('rrhh.organigrama.ver');
    }

    public function chartPayload(): array
    {
        $tenantId = (int) (Filament::getTenant()?->getKey() ?? 0);

        if ($tenantId < 1) {
            return ['nodes' => [], 'areas' => [], 'total' => 0];
        }

        $employees = Employee::query()
            ->with(['hrDepartment', 'hrJobPosition'])
            ->where('company_id', $tenantId)
            ->where('active', true)
            ->get();

        $people = [];

        foreach ($employees as $employee) {
            $id = (int) $employee->id;

            $people[$id] = [
                'id' => $id,
                'name' => trim((string) $employee->name),
                'position' => (string) (
                    $employee->hrJobPosition?->name
                    ?: $employee->position
                    ?: 'Sin puesto registrado'
                ),
                'department' => (string) (
                    $employee->hrDepartment?->name
                    ?: $employee->department
                    ?: 'Sin departamento'
                ),
                'avatar_url' => filled($employee->avatar_path)
                    && ! str_contains((string) $employee->avatar_path, '..')
                    ? Storage::disk('public')->url(
                        ltrim((string) $employee->avatar_path, '/')
                    )
                    : null,
                'manager_id' => $employee->manager_employee_id
                    ? (int) $employee->manager_employee_id
                    : null,
            ];
        }

        $children = [];
        $areaCounts = [];

        foreach ($people as $person) {
            $area = $person['department'];
            $areaCounts[$area] = ($areaCounts[$area] ?? 0) + 1;

            $managerId = $person['manager_id'];

            if ($managerId === $person['id']
                || ! isset($people[$managerId ?? 0])) {
                $managerId = null;
            }

            $children[$managerId ?? 0][] = $person['id'];
        }

        $sortIds = function (array &$ids) use ($people): void {
            usort($ids, function (int $a, int $b) use ($people): int {
                return strcasecmp(
                    $people[$a]['name'],
                    $people[$b]['name']
                );
            });
        };

        foreach ($children as &$ids) {
            $sortIds($ids);
        }
        unset($ids);

        $nodes = [];
        $visited = [];

        $walk = function (
            int $id,
            array $ancestors = []
        ) use (&$walk, &$nodes, &$visited, $people, $children): void {
            if (isset($visited[$id])) {
                return;
            }

            $visited[$id] = true;

            $person = $people[$id];
            $person['depth'] = count($ancestors);
            $person['ancestors'] = $ancestors;
            $person['has_children'] = ! empty($children[$id] ?? []);

            $managerId = $person['manager_id'];
            $person['manager_name'] = $managerId
                && isset($people[$managerId])
                    ? $people[$managerId]['name']
                    : null;

            $nodes[] = $person;

            foreach ($children[$id] ?? [] as $childId) {
                $walk(
                    $childId,
                    array_merge($ancestors, [$id])
                );
            }
        };

        foreach ($children[0] ?? [] as $rootId) {
            $walk($rootId);
        }

        // Mantener visibles registros desconectados o con ciclos.
        foreach (array_keys($people) as $id) {
            if (! isset($visited[$id])) {
                $walk($id);
            }
        }

        arsort($areaCounts, SORT_NUMERIC);

        $areas = [];

        foreach ($areaCounts as $name => $count) {
            $areas[] = [
                'name' => $name,
                'count' => $count,
            ];
        }

        return [
            'nodes' => $nodes,
            'areas' => $areas,
            'total' => count($people),
        ];
    }

    public function downloadPdf(array $state = [])
    {
        $payload = $this->chartPayload();
        $allRows = $payload['nodes'];

        // ORG03 FIX1: estado enviado por la vista Alpine actual.
        // Se valida contra los empleados del tenant; nunca se confian
        // nombres, jefaturas ni datos de empleados enviados por JS.
        $byId = [];
        foreach ($allRows as $person) {
            $byId[(int) $person['id']] = $person;
        }

        $collapsed = [];
        foreach (array_slice(
            is_array($state['collapsed'] ?? null)
                ? $state['collapsed'] : [],
            0,
            10000
        ) as $value) {
            if ((is_int($value) || ctype_digit((string) $value))
                && isset($byId[(int) $value])) {
                $collapsed[(int) $value] = true;
            }
        }

        $focusId = filter_var(
            $state['focusId'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $focusId = $focusId && isset($byId[$focusId])
            ? (int) $focusId : null;

        $area = is_string($state['area'] ?? null)
            ? mb_substr($state['area'], 0, 200) : '';
        $query = is_string($state['query'] ?? null)
            ? trim(mb_substr($state['query'], 0, 200)) : '';

        $focusMode = $focusId !== null;
        $selected = [];

        if ($focusMode) {
            // La vista enfocada presenta al responsable,
            // colaboradores directos y subequipos inmediatos.
            $selected[$focusId] = true;
            foreach ($allRows as $person) {
                $ancestors = array_map(
                    'intval', $person['ancestors'] ?? []
                );
                if (in_array($focusId, $ancestors, true)
                    && count($ancestors)
                        <= count($byId[$focusId]['ancestors'] ?? []) + 2) {
                    $selected[(int) $person['id']] = true;
                }
            }
        } else {
            $filtering = $area !== '' || $query !== '';
            $matching = [];

            $normalize = static function (string $value): string {
                return strtolower(
                    \Illuminate\Support\Str::ascii($value)
                );
            };

            if ($filtering) {
                foreach ($allRows as $person) {
                    $haystack = $normalize(
                        ($person['name'] ?? '') . ' ' .
                        ($person['position'] ?? '') . ' ' .
                        ($person['department'] ?? '') . ' ' .
                        ($person['manager_name'] ?? '')
                    );

                    $matchesArea = $area === ''
                        || ($person['department'] ?? '') === $area;
                    $matchesQuery = $query === ''
                        || str_contains(
                            $haystack, $normalize($query)
                        );

                    if ($matchesArea && $matchesQuery) {
                        $matching[(int) $person['id']] = true;
                    }
                }
            }

            $filterVisible = [];
            if ($filtering) {
                foreach ($allRows as $person) {
                    $id = (int) $person['id'];
                    if (! isset($matching[$id])) {
                        continue;
                    }
                    $filterVisible[$id] = true;
                    foreach ($person['ancestors'] ?? [] as $ancestor) {
                        $filterVisible[(int) $ancestor] = true;
                    }
                }
            }

            foreach ($allRows as $person) {
                $id = (int) $person['id'];

                if ($filtering && ! isset($filterVisible[$id])) {
                    continue;
                }

                $hidden = false;
                foreach ($person['ancestors'] ?? [] as $ancestor) {
                    if (isset($collapsed[(int) $ancestor])) {
                        $hidden = true;
                        break;
                    }
                }

                if (! $hidden) {
                    $selected[$id] = true;
                }
            }
        }

        $rows = array_values(array_filter(
            $allRows,
            static fn (array $person): bool =>
                isset($selected[(int) $person['id']])
        ));

        // Insertar imagenes locales validas como data URI para
        // que Dompdf no dependa de peticiones HTTP externas.
        foreach ($rows as &$row) {
            $row['avatar_data'] = null;

            $employee = Employee::query()
                ->where('company_id', (int) (
                    Filament::getTenant()?->getKey() ?? 0
                ))
                ->whereKey($row['id'])
                ->first(['id', 'avatar_path']);

            $path = (string) ($employee?->avatar_path ?? '');

            if ($path === '' || str_contains($path, '..')) {
                continue;
            }

            $disk = Storage::disk('public');
            $path = ltrim($path, '/');

            if (! $disk->exists($path)) {
                continue;
            }

            $mime = $disk->mimeType($path);

            if (! in_array($mime, ['image/jpeg', 'image/png'], true)) {
                continue;
            }

            if ($disk->size($path) > 250000) {
                continue;
            }

            $row['avatar_data'] = 'data:' . $mime . ';base64,'
                . base64_encode($disk->get($path));
        }
        unset($row);

        // Construir grupos jerarquicos para PDF en tres columnas.
        $visibleById = [];
        $visibleChildren = [];
        foreach ($rows as $person) {
            $visibleById[(int) $person['id']] = $person;
        }

        foreach ($rows as $person) {
            $parent = (int) ($person['manager_id'] ?? 0);
            if (isset($visibleById[$parent])) {
                $visibleChildren[$parent][] = $person;
            }
        }

        $sections = [];
        foreach ($rows as $person) {
            $id = (int) $person['id'];
            $direct = $visibleChildren[$id] ?? [];

            if ($focusMode && $id !== $focusId) {
                continue;
            }

            $parentVisible = isset(
                $visibleById[(int) ($person['manager_id'] ?? 0)]
            );

            if (! $focusMode && $parentVisible && empty($direct)) {
                continue;
            }

            $subteams = [];
            if ($focusMode) {
                foreach ($direct as $child) {
                    $childId = (int) $child['id'];
                    $subteams[$childId] =
                        $visibleChildren[$childId] ?? [];
                }
            }

            $sections[] = [
                'manager' => $person,
                'children' => $direct,
                'subteams' => $subteams,
                'depth' => (int) ($person['depth'] ?? 0),
            ];
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView(
            'filament.pages.employee-organization-pdf',
            [
                'rows' => $rows,
                'sections' => $sections,
                'focusMode' => $focusMode,
                'total' => count($rows),
                'generatedAt' => now()->format('d/m/Y H:i'),
            ]
        )->setPaper('a4', 'landscape');

        $filename = 'organigrama-gl7-'
            . now()->format('Ymd-His') . '.pdf';

        return response()->streamDownload(
            static function () use ($pdf): void {
                echo $pdf->output();
            },
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }

}
