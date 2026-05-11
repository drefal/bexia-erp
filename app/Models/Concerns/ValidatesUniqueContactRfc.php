<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

trait ValidatesUniqueContactRfc
{
    protected static function bootValidatesUniqueContactRfc(): void
    {
        static::saving(function ($contact): void {
            $rfc = strtoupper(trim((string) $contact->rfc));

            if ($rfc === '') {
                return;
            }

            if ($contact->parent_contact_id !== null) {
                return;
            }

            if (($contact->address_type ?? 'main') !== 'main') {
                return;
            }

            $query = static::query()
                ->withoutGlobalScopes()
                ->whereNull('parent_contact_id')
                ->where('address_type', 'main')
                ->whereRaw('upper(trim(rfc)) = ?', [$rfc]);

            if ($contact->company_id) {
                $query->where('company_id', $contact->company_id);
            } else {
                $query->whereNull('company_id');
            }

            if ($contact->exists) {
                $query->where('id', '<>', $contact->id);
            }

            if (Schema::hasColumn('contacts', 'deleted_at')) {
                $query->whereNull('deleted_at');
            }

            if ($query->exists()) {
                throw ValidationException::withMessages([
                    'rfc' => 'Ya existe un contacto principal activo con este RFC en esta empresa.',
                ]);
            }
        });
    }
}
