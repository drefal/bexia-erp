<?php

namespace App\Http\Controllers\Service;

use App\Http\Controllers\Controller;
use App\Support\Service\ServicePickupOrderService;
use App\Support\Service\ServicePickupPublicCaptureService;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PublicServicePickupController extends Controller
{
    /*
     * BEXIA_ATC_PICKUP_ORDER_PUBLIC_V5_83_4C4B1
     *
     * BEXIA_ATC_PICKUP_PDF_WHATSAPP_V5_83_4C4B3
     *
     * Orden publica de recoleccion.
     *
     * C4B3 agrega:
     * - PDF una hoja
     * - QR dentro PDF
     * - QR descargable
     * - WhatsApp
     * - copiar texto
     */
    public function show(
        string $token,
        ServicePickupOrderService $service
    ): Response {
        $case =
            $service->findByToken(
                $token
            );

        $data =
            $this->buildOrderData(
                $case,
                $token
            );

        $response =
            response()->view(
                'service.pickup-order-public',
                $data
            );

        $this->applyPrivateHeaders(
            $response
        );

        return $response;
    }

    /*
     * BEXIA_ATC_PUBLIC_PICKUP_POST_V5_83_4C4C
     *
     * Registro PUBLICO de la recoleccion.
     * No requiere login.
     */
    public function store(
        Request $request,
        string $token,
        ServicePickupOrderService $pickupOrderService,
        ServicePickupPublicCaptureService $captureService
    ) {
        $case =
            $pickupOrderService
                ->findByToken(
                    $token
                );

        $validated =
            $request->validate([
                'driver_name' => [
                    'required',
                    'string',
                    'max:150',
                ],

                'delivered_by' => [
                    'required',
                    'string',
                    'max:150',
                ],

                'pickup_location' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'observed_product_name' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'observed_serial_number' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'physical_condition' => [
                    'required',
                    'in:sin_danos_visibles,con_danos_visibles,desgaste_normal,otro',
                ],

                'accessories' => [
                    'required',
                    'array',
                    'min:1',
                ],

                'accessories.*' => [
                    'string',
                    'in:ninguno,cargador,cable,bateria,llaves,control,otro',
                ],

                'accessories_other' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'notes' => [
                    'nullable',
                    'string',
                    'max:2000',
                ],

                'photos' => [
                    'nullable',
                    'array',
                    'max:5',
                ],

                'photos.*' => [
                    'image',
                    'mimes:jpg,jpeg,png,webp',
                    'max:6144',
                ],

                'confirm_identity' => [
                    'accepted',
                ],

                'confirm_condition' => [
                    'accepted',
                ],

                'confirm_accessories' => [
                    'accepted',
                ],

                'confirm_authorization' => [
                    'accepted',
                ],

                'customer_signature' => [
                    'required',
                    'string',
                    'max:3000000',
                ],
            ]);

        $captureService->complete(
            $case,
            $token,
            $validated,
            (array) $request->file(
                'photos',
                []
            ),
            $request->ip(),
            $request->userAgent()
        );

        return redirect()
            ->route(
                'public.service.pickup.show',
                [
                    'token' =>
                        $token,
                ]
            )
            ->with(
                'pickup_completed',
                true
            );
    }

    /*
     * Evidencia protegida por:
     * - token
     * - service_case_id
     * - etapa permitida
     */
    public function evidence(
        string $token,
        int $attachment,
        ServicePickupOrderService $service
    ) {
        $case =
            $service->findByToken(
                $token
            );

        $file =
            DB::table(
                'service_attachments'
            )
                ->where(
                    'id',
                    $attachment
                )
                ->where(
                    'service_case_id',
                    $case->id
                )
                ->whereIn(
                    'stage',
                    [
                        'ticket_opening',
                        'pre_reception',
                    ]
                )
                ->first();

        if (! $file) {
            abort(404);
        }

        $path =
            trim(
                (string)
                    $file->file_path
            );

        if (
            $path === ''
            || ! Storage::disk(
                'public'
            )->exists(
                $path
            )
        ) {
            abort(404);
        }

        return Storage::disk(
            'public'
        )->response(
            $path,
            (string)
                $file->file_name,
            [
                'Content-Type' =>
                    (string) (
                        $file->mime_type
                        ?: 'application/octet-stream'
                    ),

                'Cache-Control' =>
                    'private, no-store, max-age=0',

                'X-Robots-Tag' =>
                    'noindex, nofollow, noarchive',
            ]
        );
    }

    /*
     * PDF DE UNA SOLA HOJA.
     *
     * La vista PDF:
     * - limita textos extensos
     * - limita evidencias embebidas a 2
     * - incluye QR y liga
     * - usa tamaño carta vertical
     */
    public function pdf(
        string $token,
        ServicePickupOrderService $service
    ) {
        if (! app()->bound(
            'dompdf.wrapper'
        )) {
            abort(
                500,
                'No hay motor PDF disponible.'
            );
        }

        $case =
            $service->findByToken(
                $token
            );

        $data =
            $this->buildOrderData(
                $case,
                $token
            );

        $pdfEvidence = [];

        foreach (
            $data['attachments']
                ->take(2)
            as $attachment
        ) {
            $mime =
                (string) (
                    $attachment
                        ->mime_type
                    ?? ''
                );

            $path =
                trim(
                    (string) (
                        $attachment
                            ->file_path
                        ?? ''
                    )
                );

            if (
                ! str_starts_with(
                    $mime,
                    'image/'
                )
                || $path === ''
                || ! Storage::disk(
                    'public'
                )->exists(
                    $path
                )
            ) {
                continue;
            }

            try {
                $binary =
                    Storage::disk(
                        'public'
                    )->get(
                        $path
                    );

                /*
                 * Evitar incrustar imagenes enormes.
                 */
                if (
                    strlen($binary)
                    > 4 * 1024 * 1024
                ) {
                    continue;
                }

                $pdfEvidence[] = [
                    'data_uri' =>
                        'data:'
                        . $mime
                        . ';base64,'
                        . base64_encode(
                            $binary
                        ),

                    'stage' =>
                        (string)
                            $attachment
                                ->stage,

                    'name' =>
                        (string)
                            $attachment
                                ->file_name,
                ];
            } catch (\Throwable) {
                /*
                 * El PDF debe seguir generando
                 * aun si una evidencia no puede
                 * incrustarse.
                 */
            }
        }

        $data['pdfEvidence'] =
            $pdfEvidence;

        $data['evidenceCount'] =
            $data['attachments']
                ->count();

        $pdf =
            app('dompdf.wrapper')
                ->loadView(
                    'service.pickup-order-pdf',
                    $data
                )
                ->setPaper(
                    'letter',
                    'portrait'
                );

        $filename =
            'Orden-recoleccion-'
            . preg_replace(
                '/[^A-Za-z0-9_-]+/',
                '-',
                (string)
                    $case->folio
            )
            . '.pdf';

        /*
         * Stream = abre directamente como PDF.
         * Desde movil se puede compartir o descargar.
         */
        $response =
            $pdf->stream(
                $filename
            );

        $this->applyPrivateHeaders(
            $response
        );

        return $response;
    }

    /*
     * QR DESCARGABLE COMO IMAGEN SVG.
     *
     * SVG funciona sin dependencia de Imagick.
     */
    public function qr(
        string $token,
        ServicePickupOrderService $service
    ): Response {
        $case =
            $service->findByToken(
                $token
            );

        $publicUrl =
            route(
                'public.service.pickup.show',
                [
                    'token' =>
                        $token,
                ]
            );

        $svg =
            $this->qrSvg(
                $publicUrl,
                700
            );

        $filename =
            'QR-Orden-recoleccion-'
            . preg_replace(
                '/[^A-Za-z0-9_-]+/',
                '-',
                (string)
                    $case->folio
            )
            . '.svg';

        $response =
            response(
                $svg,
                200,
                [
                    'Content-Type' =>
                        'image/svg+xml; charset=UTF-8',

                    'Content-Disposition' =>
                        'attachment; filename="'
                        . $filename
                        . '"',
                ]
            );

        $this->applyPrivateHeaders(
            $response
        );

        return $response;
    }

    protected function buildOrderData(
        $case,
        string $token
    ): array {
        $metadata =
            is_array($case->metadata)
                ? $case->metadata
                : [];

        $pickup =
            (array) (
                $metadata[
                    'pickup_order'
                ]
                ?? []
            );

        $snapshot =
            (array) (
                $pickup[
                    'reported_snapshot'
                ]
                ?? []
            );

        $publicUrl =
            route(
                'public.service.pickup.show',
                [
                    'token' =>
                        $token,
                ]
            );

        $pdfUrl =
            route(
                'public.service.pickup.pdf',
                [
                    'token' =>
                        $token,
                ]
            );

        $qrUrl =
            route(
                'public.service.pickup.qr',
                [
                    'token' =>
                        $token,
                ]
            );

        $qrSvg =
            $this->qrSvg(
                $publicUrl,
                300
            );

        $qrDataUri =
            'data:image/svg+xml;base64,'
            . base64_encode(
                $qrSvg
            );

        $attachments =
            DB::table(
                'service_attachments'
            )
                ->where(
                    'service_case_id',
                    $case->id
                )
                ->whereIn(
                    'stage',
                    [
                        'ticket_opening',
                        'pre_reception',
                    ]
                )
                ->orderBy('id')
                ->get();

        $folio =
            (string) (
                $snapshot['folio']
                ?? $case->folio
                ?? ''
            );

        $client =
            trim(
                (string) (
                    $snapshot[
                        'contact_name'
                    ]
                    ?? ''
                )
            );

        $place =
            trim(
                (string) (
                    $snapshot[
                        'pickup_place'
                    ]
                    ?? ''
                )
            );

        $shareLines = [
            'Orden de recolección '
                . $folio,
        ];

        if ($client !== '') {
            $shareLines[] =
                'Cliente: '
                . $client;
        }

        if ($place !== '') {
            $shareLines[] =
                'Lugar: '
                . $place;
        }

        $shareLines[] = '';
        $shareLines[] =
            'Abrir la orden y registrar la recolección:';

        $shareLines[] =
            $publicUrl;

        $whatsappText =
            implode(
                "\n",
                $shareLines
            );

        $whatsappUrl =
            'https://wa.me/?text='
            . rawurlencode(
                $whatsappText
            );

        return [
            'case' =>
                $case,

            'pickup' =>
                $pickup,

            'snapshot' =>
                $snapshot,

            'attachments' =>
                $attachments,

            'token' =>
                $token,

            'publicUrl' =>
                $publicUrl,

            'pdfUrl' =>
                $pdfUrl,

            'qrUrl' =>
                $qrUrl,

            'qrDataUri' =>
                $qrDataUri,

            'whatsappText' =>
                $whatsappText,

            'whatsappUrl' =>
                $whatsappUrl,
        ];
    }

    protected function qrSvg(
        string $value,
        int $size
    ): string {
        $renderer =
            new ImageRenderer(
                new RendererStyle(
                    $size
                ),
                new SvgImageBackEnd()
            );

        $writer =
            new Writer(
                $renderer
            );

        return $writer->writeString(
            $value
        );
    }

    protected function applyPrivateHeaders(
        $response
    ): void {
        $response->headers->set(
            'X-Robots-Tag',
            'noindex, nofollow, noarchive'
        );

        $response->headers->set(
            'Cache-Control',
            'private, no-store, max-age=0'
        );
    }
}
