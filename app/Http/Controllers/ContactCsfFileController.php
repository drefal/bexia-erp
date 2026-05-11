<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ContactCsfFileController extends Controller
{
    public function show(Contact $contact): BinaryFileResponse
    {
        $this->authorizeBasicAccess($contact);

        $path = $this->resolvePath($contact);
        $filename = $contact->csf_source_filename ?: basename($path);

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . addslashes($filename) . '"',
        ]);
    }

    public function download(Contact $contact): BinaryFileResponse
    {
        $this->authorizeBasicAccess($contact);

        $path = $this->resolvePath($contact);
        $filename = $contact->csf_source_filename ?: basename($path);

        return response()->download($path, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    protected function resolvePath(Contact $contact): string
    {
        $relativePath = trim((string) $contact->csf_pdf_path);

        abort_if($relativePath === '', Response::HTTP_NOT_FOUND, 'El contacto no tiene Constancia SAT guardada.');

        abort_unless(Storage::disk('local')->exists($relativePath), Response::HTTP_NOT_FOUND, 'No se encontró el archivo de la Constancia SAT.');

        return Storage::disk('local')->path($relativePath);
    }

    protected function authorizeBasicAccess(Contact $contact): void
    {
        $user = auth()->user();

        abort_unless($user, Response::HTTP_FORBIDDEN);

        // Seguridad básica multiempresa:
        // si el usuario tiene company_id y el contacto también, deben coincidir.
        // Super admin normalmente no tiene company_id fijo o puede operar por tenant.
        if (
            isset($user->company_id)
            && filled($user->company_id)
            && filled($contact->company_id)
            && (int) $user->company_id !== (int) $contact->company_id
        ) {
            abort(Response::HTTP_FORBIDDEN);
        }
    }
}
