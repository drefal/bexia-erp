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

    public function downloadPdf()
    {
        $payload = $this->chartPayload();
        $rows = $payload['nodes'];

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

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView(
            'filament.pages.employee-organization-pdf',
            [
                'rows' => $rows,
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
