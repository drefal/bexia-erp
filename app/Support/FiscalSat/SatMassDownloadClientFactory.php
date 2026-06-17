<?php

namespace App\Support\FiscalSat;

use App\Models\SatCompanyCredential;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\Fiel;
use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\FielRequestBuilder;
use PhpCfdi\SatWsDescargaMasiva\Service;
use PhpCfdi\SatWsDescargaMasiva\WebClient\GuzzleWebClient;
use RuntimeException;

class SatMassDownloadClientFactory
{
    public function createForCredential(SatCompanyCredential $credential): Service
    {
        $fiel = $this->createFiel($credential);

        if (! $fiel->isValid()) {
            throw new RuntimeException('La e.firma/FIEL no es válida para descarga masiva SAT. Verifica que no sea CSD y que esté vigente.');
        }

        return new Service(
            new FielRequestBuilder($fiel),
            new GuzzleWebClient()
        );
    }

    public function createFiel(SatCompanyCredential $credential): Fiel
    {
        if (! $credential->cer_file_path || ! $credential->key_file_path || ! $credential->password_encrypted) {
            throw new RuntimeException('Falta configurar .cer, .key o contraseña e.firma.');
        }

        if (! Storage::disk('local')->exists($credential->cer_file_path)) {
            throw new RuntimeException('No se encontró el archivo .cer en storage privado.');
        }

        if (! Storage::disk('local')->exists($credential->key_file_path)) {
            throw new RuntimeException('No se encontró el archivo .key en storage privado.');
        }

        $cerContent = Storage::disk('local')->get($credential->cer_file_path);
        $keyContent = Storage::disk('local')->get($credential->key_file_path);
        $password = Crypt::decryptString((string) $credential->password_encrypted);

        return Fiel::create(
            (string) $cerContent,
            (string) $keyContent,
            $password
        );
    }
}
