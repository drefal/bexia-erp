<?php

namespace App\Http\Controllers\Service;

use App\Http\Controllers\Controller;
use App\Models\RepairOrder;
use App\Support\Service\ServiceAccess;
use Illuminate\Support\Facades\DB;

/*
 * BEXIA_ATC_REPAIR_EXIT_DOCUMENT_PDF_V5_83_4C5G3
 */
class ServiceRepairExitDocumentController extends Controller
{
    public function __invoke(
        int|string $tenant,
        int|string $record
    ) {
        $repair =
            RepairOrder::query()
                ->findOrFail(
                    (int) $record
                );

        abort_unless(
            (int) $repair->company_id
                === (int) $tenant,
            404
        );

        $canView =
            ServiceAccess::can(
                'service.repairs.view'
            )
            || ServiceAccess::can(
                'service.cases.view'
            )
            || ServiceAccess::
                hasServiceRole([
                    'Servicio - Recepción',
                    'Servicio - Encargado de Técnicos',
                    'Servicio - Supervisor',
                ]);

        abort_unless(
            $canView,
            403
        );

        abort_unless(
            ServiceAccess::
                hasAuthorizedRepairExitDocument(
                    $repair
                ),
            404
        );

        $document =
            ServiceAccess::
                repairExitDocument(
                    $repair
                );

        $case = null;

        if (
            filled(
                $repair->service_case_id
            )
        ) {
            $case =
                DB::table(
                    'service_cases'
                )
                    ->where(
                        'id',
                        $repair->
                            service_case_id
                    )
                    ->first();
        }

        $company =
            DB::table('companies')
                ->where(
                    'id',
                    $repair->company_id
                )
                ->first();

        if (! app()->bound('dompdf.wrapper')) {
            throw new \RuntimeException(
                'No está disponible el motor PDF.'
            );
        }

        $pdf =
            app('dompdf.wrapper')
                ->loadView(
                    'service.repair-exit-document-pdf',
                    [
                        'repair' =>
                            $repair,

                        'case' =>
                            $case,

                        'company' =>
                            $company,

                        'document' =>
                            $document,
                    ]
                )
                ->setPaper(
                    'letter',
                    'portrait'
                );

        $filename =
            preg_replace(
                '/[^A-Za-z0-9\-_]/',
                '-',
                (string) (
                    $document['folio']
                    ?? 'SAL-ATC'
                )
            )
            . '.pdf';

        return $pdf->stream(
            $filename
        );
    }
}
