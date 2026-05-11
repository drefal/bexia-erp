<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (! Schema::hasTable('contacts')) {
    dump('No existe tabla contacts.');
    return;
}

$hasDeletedAt = Schema::hasColumn('contacts', 'deleted_at');
$now = now();

$duplicates = DB::table('contacts')
    ->select(
        'company_id',
        DB::raw('upper(trim(rfc)) as rfc_key'),
        DB::raw('count(*) as total')
    )
    ->whereNull('parent_contact_id')
    ->where('address_type', 'main')
    ->whereNotNull('rfc')
    ->whereRaw("trim(rfc) <> ''")
    ->when($hasDeletedAt, fn ($query) => $query->whereNull('deleted_at'))
    ->groupBy('company_id', DB::raw('upper(trim(rfc))'))
    ->havingRaw('count(*) > 1')
    ->get();

dump([
    'duplicados_detectados' => $duplicates->count(),
]);

foreach ($duplicates as $duplicate) {
    $ids = DB::table('contacts')
        ->whereNull('parent_contact_id')
        ->where('address_type', 'main')
        ->where('company_id', $duplicate->company_id)
        ->whereRaw('upper(trim(rfc)) = ?', [$duplicate->rfc_key])
        ->when($hasDeletedAt, fn ($query) => $query->whereNull('deleted_at'))
        ->orderBy('id')
        ->pluck('id')
        ->all();

    $keepId = array_shift($ids);

    dump([
        'rfc' => $duplicate->rfc_key,
        'mantener_id' => $keepId,
        'archivar_ids' => $ids,
    ]);

    foreach ($ids as $id) {
        $contact = DB::table('contacts')->where('id', $id)->first();

        $note = trim((string) ($contact->internal_notes ?? ''));
        $note = trim($note . "\n\n---\nArchivado automático por RFC duplicado. Se conservó activo el contacto ID: {$keepId}. Fecha: {$now->format('Y-m-d H:i:s')}");

        $update = [
            'is_active' => false,
            'internal_notes' => $note,
            'updated_at' => $now,
        ];

        if ($hasDeletedAt) {
            $update['deleted_at'] = $now;
        }

        DB::table('contacts')
            ->where('id', $id)
            ->update($update);
    }
}
