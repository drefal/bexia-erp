<?php

namespace App\Http\Controllers\Service;

use App\Filament\Resources\ServiceCaseResource;
use App\Http\Controllers\Controller;
use App\Models\ServiceCase;
use App\Support\Service\ServiceCaseDirectAttentionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServiceCaseSolutionPrintController extends Controller
{
    /*
     * BEXIA_ATC_DIRECT_SOLUTION_PRINT_V5_82_P7H32D
     */
    public function __invoke(
        int|string $tenant,
        int|string $record
    ) {
        $case = ServiceCase::query()
            ->findOrFail((int) $record);

        abort_unless(
            (string) $case->company_id === (string) $tenant,
            403
        );

        abort_unless(
            (string) $case->attention_route === 'non_repair',
            404
        );

        abort_unless(
            ServiceCaseResource::canView($case),
            403
        );

        abort_unless(
            (string) $case->status === 'cerrado',
            409,
            'La constancia sólo está disponible cuando el ticket está cerrado.'
        );

        $response = DB::table('service_case_events')
            ->where('service_case_id', $case->id)
            ->where(
                'event_type',
                ServiceCaseDirectAttentionService::EVENT_RESPONSE
            )
            ->orderByDesc('id')
            ->first();

        abort_unless(
            $response,
            409,
            'El ticket no tiene una respuesta registrada.'
        );

        $validation = DB::table('service_case_events')
            ->where('service_case_id', $case->id)
            ->where(
                'event_type',
                ServiceCaseDirectAttentionService::EVENT_RESPONSE_VALIDATED
            )
            ->orderByDesc('id')
            ->get()
            ->first(function ($event) use ($response): bool {
                $values = json_decode(
                    (string) ($event->new_values ?? ''),
                    true
                ) ?: [];

                return (int) (
                    $values['response_event_id']
                    ?? 0
                ) === (int) $response->id;
            });

        abort_unless(
            $validation,
            409,
            'La última respuesta todavía no tiene validación.'
        );

        $validationValues = json_decode(
            (string) ($validation->new_values ?? ''),
            true
        ) ?: [];

        $company = $this->recordById(
            'companies',
            $case->company_id
        );

        $customer = $this->firstExistingRecord(
            ['contacts', 'customers', 'clients'],
            $case->customer_id ?? null
        );

        $product = $this->firstExistingRecord(
            ['products', 'items'],
            $case->product_id ?? null
        );

        $technician = $this->recordById(
            'employees',
            $case->assigned_employee_id ?? null
        );

        $responseUser = $this->recordById(
            'users',
            $response->performed_by ?? null
        );

        $validatorUser = $this->recordById(
            'users',
            $validationValues['validated_by']
                ?? $validation->performed_by
                ?? null
        );

        $closeEvent = DB::table('service_case_events')
            ->where('service_case_id', $case->id)
            ->where(
                'event_type',
                'ticket_resuelto_sin_reparacion'
            )
            ->orderByDesc('id')
            ->first();

        $closerUser = $this->recordById(
            'users',
            $case->closed_by
                ?? $closeEvent->performed_by
                ?? null
        );

        $resolutionType = (string) (
            $case->resolution_type
            ?? ''
        );

        $resolutionTypeLabel =
            ServiceCaseDirectAttentionService::RESOLUTION_TYPES[
                $resolutionType
            ]
            ?? $resolutionType
            ?: 'Sin clasificación';

        return view(
            'service.service-cases.solution-print',
            [
                'case' => $case,
                'company' => $company,
                'customer' => $customer,
                'product' => $product,
                'technician' => $technician,
                'response' => $response,
                'responseUser' => $responseUser,
                'validation' => $validation,
                'validationValues' => $validationValues,
                'validatorUser' => $validatorUser,
                'closeEvent' => $closeEvent,
                'closerUser' => $closerUser,
                'resolutionTypeLabel' => $resolutionTypeLabel,
                'logoUrl' => $this->logoUrl($company),
                'printedAt' => now(),
            ]
        );
    }

    protected function recordById(
        string $table,
        mixed $id
    ): ?object {
        if (! $id) {
            return null;
        }

        try {
            return DB::table($table)
                ->where('id', $id)
                ->first();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function firstExistingRecord(
        array $tables,
        mixed $id
    ): ?object {
        foreach ($tables as $table) {
            $record = $this->recordById(
                $table,
                $id
            );

            if ($record) {
                return $record;
            }
        }

        return null;
    }

    protected function logoUrl(
        ?object $company
    ): ?string {
        if (! $company) {
            return null;
        }

        foreach ([
            'logo_url',
            'logo_path',
            'logo',
        ] as $column) {
            $value = trim(
                (string) (
                    $company->{$column}
                    ?? ''
                )
            );

            if ($value === '') {
                continue;
            }

            if (
                Str::startsWith(
                    $value,
                    ['http://', 'https://']
                )
            ) {
                return $value;
            }

            if (
                Str::startsWith(
                    $value,
                    'storage/'
                )
            ) {
                return asset($value);
            }

            return asset(
                'storage/'
                . ltrim($value, '/')
            );
        }

        return null;
    }
}
