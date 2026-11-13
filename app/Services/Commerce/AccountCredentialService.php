<?php

namespace App\Services\Commerce;

use App\Filament\Resources\CommerceAccountResource;
use App\Models\CommerceAccount;
use App\Models\CommerceAccountCredential;
use App\Models\CommerceAuthField;
use App\Models\CommerceConnectorVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountCredentialService
{
    /**
     * Guarda o sustituye una credencial de una cuenta.
     *
     * No devuelve el secreto y no activa conexiones.
     */
    public function replace(
        int $accountId,
        int $versionId,
        string $fieldKey,
        string $secret
    ): void {
        if (! CommerceAccountResource::canManageCompany()) {
            abort(403);
        }

        $companyId =
            CommerceAccountResource::currentCompanyId();

        if ($companyId === null || $companyId <= 0) {
            abort(403);
        }

        if (
            trim($secret) === ''
            || strlen($secret) > 4096
            || trim($fieldKey) === ''
        ) {
            throw ValidationException::withMessages([
                'secret' => 'Credencial inválida.',
            ]);
        }

        DB::transaction(function () use (
            $companyId,
            $accountId,
            $versionId,
            $fieldKey,
            $secret
        ): void {
            $account = CommerceAccount::query()
                ->where('company_id', $companyId)
                ->whereKey($accountId)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                ! CommerceAccountResource::canEdit($account)
                || (bool) $account->is_active
            ) {
                abort(403);
            }

            $version = CommerceConnectorVersion::query()
                ->whereKey($versionId)
                ->where(
                    'commerce_channel_id',
                    $account->commerce_channel_id
                )
                ->where('status', 'ready')
                ->firstOrFail();

            $field = CommerceAuthField::query()
                ->where(
                    'commerce_connector_version_id',
                    $version->getKey()
                )
                ->where('field_key', $fieldKey)
                ->where('credential_owner', 'account')
                ->where('is_secret', true)
                ->where('is_active', true)
                ->firstOrFail();

            // La consulta del campo valida que la credencial
            // esta permitida para esta version concreta.
            // No utilizar mass assignment para secret_value.
            $credential = CommerceAccountCredential::query()
                ->where(
                    'commerce_account_id',
                    $account->getKey()
                )
                ->where('field_key', $field->field_key)
                ->lockForUpdate()
                ->first();

            if (! $credential) {
                $credential = new CommerceAccountCredential();
                $credential->commerce_account_id =
                    $account->getKey();
                $credential->field_key = $field->field_key;
            }

            // El cast encrypted cifra antes de persistir.
            $credential->secret_value = $secret;
            $credential->save();
        });
    }
}
