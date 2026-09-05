<?php

namespace App\Support\Attendance;

use App\Models\Employee;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class EmployeeCredentialPdfService
{
    public const CARD_WIDTH_MM = 52.00;

    public const CARD_HEIGHT_MM = 82.00;

    public function attendanceUrl(Employee $employee): string
    {
        $token = trim((string) $employee->attendance_qr_token);

        if ($token === '') {
            throw new RuntimeException(
                'El empleado no tiene token QR de asistencia.'
            );
        }

        return url('/asistencia/empleado/' . $token);
    }

    public function isEligible(Employee $employee): bool
    {
        return (bool) $employee->active
            && (bool) $employee->attendance_qr_enabled
            && trim((string) $employee->attendance_qr_token) !== '';
    }

    public function cardData(Employee $employee): array
    {
        if (! $this->isEligible($employee)) {
            throw new RuntimeException(
                'El empleado no esta activo o no tiene QR de asistencia habilitado.'
            );
        }

        $employee->loadMissing([
            'company',
            'branch',
            'hrJobPosition',
        ]);

        return [
            'employee_id' => (int) $employee->getKey(),

            'name' => trim(
                (string) $employee->name
            ),

            'employee_number' => trim(
                (string) ($employee->employee_number ?: '')
            ),

            'company' => trim(
                (string) ($employee->company?->name ?: 'BEXIA')
            ),

            'branch' => trim(
                (string) ($employee->branch?->name ?: '')
            ),

            'position' => trim(
                (string) (
                    $employee->hrJobPosition?->name
                    ?: $employee->position
                    ?: 'Colaborador'
                )
            ),

            'photo_data_uri' => $this->photoDataUri($employee),

            'qr_data_uri' => $this->qrDataUri(
                $this->attendanceUrl($employee)
            ),
        ];
    }

    public function renderIndividual(Employee $employee): string
    {
        $card = $this->cardData($employee);

        $pdf = Pdf::loadView(
            'attendance.employee-credential-individual',
            [
                'card' => $card,
            ]
        );

        /*
         * 52 x 82 mm.
         * Conversión: mm x 72 / 25.4
         */
        $pdf->setPaper([
            0,
            0,
            147.4016,
            232.4409,
        ]);

        return $pdf->output();
    }

    public function renderBulk(Collection $employees): string
    {
        $cards = $employees
            ->filter(
                fn (Employee $employee): bool =>
                    $this->isEligible($employee)
            )
            ->map(
                fn (Employee $employee): array =>
                    $this->cardData($employee)
            )
            ->values()
            ->all();

        if ($cards === []) {
            throw new RuntimeException(
                'No hay empleados elegibles para generar credenciales.'
            );
        }

        return Pdf::loadView(
            'attendance.employee-credentials-sheet',
            [
                'cards' => $cards,
            ]
        )
            ->setPaper('letter', 'portrait')
            ->output();
    }

    public function individualFilename(Employee $employee): string
    {
        $identifier = trim(
            (string) $employee->employee_number
        );

        if ($identifier === '') {
            $identifier = 'id-' . $employee->getKey();
        }

        $name = Str::slug(
            (string) $employee->name
        );

        $name = $name !== ''
            ? $name
            : 'empleado';

        return 'credencial-'
            . Str::slug($identifier)
            . '-'
            . $name
            . '.pdf';
    }

    public function bulkFilename(string $companyName): string
    {
        $company = Str::slug($companyName);

        $company = $company !== ''
            ? $company
            : 'empresa';

        return 'credenciales-'
            . $company
            . '-'
            . now()->format('Y-m-d')
            . '.pdf';
    }

    protected function qrDataUri(string $contents): string
    {
        /*
         * SVG de alta resolución lógica.
         * Impresión física: 25 x 25 mm.
         */
        $renderer = new ImageRenderer(
            new RendererStyle(
                340,
                3
            ),
            new SvgImageBackEnd()
        );

        $writer = new Writer($renderer);

        $svg = $writer->writeString($contents);

        return 'data:image/svg+xml;base64,'
            . base64_encode($svg);
    }

    protected function photoDataUri(Employee $employee): string
    {
        $path = ltrim(
            trim((string) $employee->avatar_path),
            '/'
        );

        if ($path === '') {
            return $this->genericAvatarDataUri();
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            return $this->genericAvatarDataUri();
        }

        try {
            $bytes = $disk->get($path);
        } catch (Throwable) {
            return $this->genericAvatarDataUri();
        }

        if ($bytes === '') {
            return $this->genericAvatarDataUri();
        }

        $thumbnail = $this->makeJpegThumbnail(
            $bytes,
            420,
            420
        );

        if ($thumbnail !== null) {
            return 'data:image/jpeg;base64,'
                . base64_encode($thumbnail);
        }

        try {
            $mime = (string) (
                $disk->mimeType($path)
                ?: 'image/jpeg'
            );
        } catch (Throwable) {
            $mime = 'image/jpeg';
        }

        if (! str_starts_with($mime, 'image/')) {
            return $this->genericAvatarDataUri();
        }

        return 'data:'
            . $mime
            . ';base64,'
            . base64_encode($bytes);
    }

    protected function makeJpegThumbnail(
        string $bytes,
        int $targetWidth,
        int $targetHeight
    ): ?string {
        if (
            ! extension_loaded('gd')
            || ! function_exists('imagecreatefromstring')
        ) {
            return null;
        }

        $source = @imagecreatefromstring($bytes);

        if ($source === false) {
            return null;
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);

        if (
            $sourceWidth < 1
            || $sourceHeight < 1
        ) {
            imagedestroy($source);
            return null;
        }

        $sourceRatio =
            $sourceWidth / $sourceHeight;

        $targetRatio =
            $targetWidth / $targetHeight;

        if ($sourceRatio > $targetRatio) {
            $cropHeight = $sourceHeight;

            $cropWidth = (int) round(
                $sourceHeight * $targetRatio
            );

            $sourceX = (int) round(
                ($sourceWidth - $cropWidth) / 2
            );

            $sourceY = 0;
        } else {
            $cropWidth = $sourceWidth;

            $cropHeight = (int) round(
                $sourceWidth / $targetRatio
            );

            $sourceX = 0;

            $sourceY = (int) round(
                ($sourceHeight - $cropHeight) / 2
            );
        }

        $target = imagecreatetruecolor(
            $targetWidth,
            $targetHeight
        );

        if ($target === false) {
            imagedestroy($source);
            return null;
        }

        $white = imagecolorallocate(
            $target,
            255,
            255,
            255
        );

        imagefill(
            $target,
            0,
            0,
            $white
        );

        imagecopyresampled(
            $target,
            $source,
            0,
            0,
            $sourceX,
            $sourceY,
            $targetWidth,
            $targetHeight,
            $cropWidth,
            $cropHeight
        );

        ob_start();

        imagejpeg(
            $target,
            null,
            84
        );

        $jpeg = ob_get_clean();

        imagedestroy($source);
        imagedestroy($target);

        return is_string($jpeg)
            && $jpeg !== ''
                ? $jpeg
                : null;
    }

    protected function genericAvatarDataUri(): string
    {
        $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="420" height="420" viewBox="0 0 420 420">
  <rect width="420" height="420" rx="26" fill="#f2f4f7"/>
  <circle cx="210" cy="142" r="75" fill="#9ca3af"/>
  <path d="M88 365c9-90 57-139 122-139s113 49 122 139" fill="#9ca3af"/>
  <text x="210" y="397" font-family="Arial, sans-serif" font-size="24" text-anchor="middle" fill="#4b5563">SIN FOTO</text>
</svg>
SVG;

        return 'data:image/svg+xml;base64,'
            . base64_encode($svg);
    }
}
