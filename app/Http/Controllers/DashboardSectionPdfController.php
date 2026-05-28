<?php

namespace App\Http\Controllers;

use App\Support\Dashboard\DashboardSectionData;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class DashboardSectionPdfController extends Controller
{
    public function show(Request $request, int|string $tenant, string $section): Response
    {
        abort_unless(auth()->check(), 403);

        $companyId = (int) $tenant;
        $section = Str::lower($section);

        $dataService = app(DashboardSectionData::class);

        $payload = match ($section) {
            'rrhh' => [
                'section' => 'rrhh',
                'title' => 'Recursos Humanos',
                'theme' => 'purple',
                'data' => $dataService->hr($companyId),
            ],
            'contabilidad' => [
                'section' => 'contabilidad',
                'title' => 'Contabilidad',
                'theme' => 'blue',
                'data' => $dataService->accounting($companyId),
            ],
            'tesoreria' => [
                'section' => 'tesoreria',
                'title' => 'Tesorería / Efectivo',
                'theme' => 'green',
                'data' => $dataService->treasury($companyId),
            ],
            default => abort(404),
        };

        $html = view('dashboard.section-pdf', $payload)->render();

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $pdf = new Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('letter', 'landscape');
        $pdf->render();

        $filename = 'bexia-' . $section . '-' . now()->format('Ymd-His') . '.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }
}
