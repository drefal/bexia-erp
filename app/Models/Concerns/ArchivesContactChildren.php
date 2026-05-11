<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Schema;

trait ArchivesContactChildren
{
    protected static function bootArchivesContactChildren(): void
    {
        static::deleting(function ($contact): void {
            if (
                method_exists($contact, 'isForceDeleting')
                && $contact->isForceDeleting()
            ) {
                return;
            }

            if (! Schema::hasColumn('contacts', 'deleted_at')) {
                $contact->childContacts()
                    ->update([
                        'is_active' => false,
                    ]);

                return;
            }

            $contact->childContacts()
                ->whereNull('deleted_at')
                ->get()
                ->each(function ($child): void {
                    $child->delete();
                });
        });

        static::restoring(function ($contact): void {
            if (! Schema::hasColumn('contacts', 'deleted_at')) {
                return;
            }

            static::query()
                ->withoutGlobalScopes()
                ->where('parent_contact_id', $contact->id)
                ->whereNotNull('deleted_at')
                ->get()
                ->each(function ($child): void {
                    if (method_exists($child, 'restore')) {
                        $child->restore();
                    }
                });
        });
    }
}
