<?php

declare(strict_types=1);

namespace App\Support\Reports;

use DateTimeImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class AttendanceReportFilters
{
    public function normalize(
        int $companyId,
        array $input
    ): array {

        if ($companyId < 1) {
            throw ValidationException::withMessages([
                'company_id' => 'Empresa no valida.',
            ]);
        }

        if (
            isset($input['company_id'])
            && filter_var(
                $input['company_id'],
                FILTER_VALIDATE_INT
            ) !== $companyId
        ) {
            throw ValidationException::withMessages([
                'company_id' => 'Empresa no autorizada.',
            ]);
        }

        $validated = Validator::make(
            $input,
            [
                'from' => [
                    'required',
                    'date_format:Y-m-d',
                ],

                'to' => [
                    'required',
                    'date_format:Y-m-d',
                ],

                'employee_ids' => [
                    'sometimes',
                    'array',
                    'max:3000',
                ],

                'employee_ids.*' => [
                    'required',
                    'integer',
                    'min:1',
                ],

                'department_id' => [
                    'nullable',
                    'integer',
                    'min:1',
                ],

                'only_key_personnel' => [
                    'sometimes',
                    'boolean',
                ],

                'status' => [
                    'nullable',
                    Rule::in([
                        'present',
                        'late',
                        'early_leave',
                        'late_early_leave',
                        'absence',
                        'incomplete',
                        'rest_day',
                        'rest_day_worked',
                        'no_schedule',
                    ]),
                ],

                'group_by' => [
                    'required',
                    Rule::in([
                        'day',
                        'employee',
                    ]),
                ],
            ]
        )->validate();

        $from = $validated['from'];
        $to = $validated['to'];

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $start = new DateTimeImmutable($from);
        $end = new DateTimeImmutable($to);

        // Initial operational limit: maximum
        // 366 calendar days, inclusive.

        if ($start->diff($end)->days > 365) {
            throw ValidationException::withMessages([
                'to' =>
                    'Selecciona un periodo de hasta 366 dias.',
            ]);
        }

        $employeeIds = array_values(
            array_unique(
                array_map(
                    'intval',
                    $validated['employee_ids'] ?? []
                )
            )
        );

        sort($employeeIds, SORT_NUMERIC);

        return [
            'company_id' => $companyId,
            'from' => $from,
            'to' => $to,
            'employee_ids' => $employeeIds,

            'only_key_personnel' => (bool) (
                $validated['only_key_personnel']
                ?? false
            ),

            'department_id' => isset(
                $validated['department_id']
            )
                ? (int) $validated['department_id']
                : null,

            'status' => $validated['status'] ?? null,

            'group_by' => $validated['group_by'],
        ];
    }
}
