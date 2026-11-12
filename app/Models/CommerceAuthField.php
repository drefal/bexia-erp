<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommerceAuthField extends Model
{
    protected static function booted(): void
    {
        static::saving(function (self $field): void {
            $versionId = (int) $field->commerce_connector_version_id;

            $version = CommerceConnectorVersion::query()
                ->whereKey($versionId)
                ->first(['id', 'status', 'is_enabled']);

            if (! $version
                || ! in_array(
                    (string) $version->status,
                    ['draft', 'testing'],
                    true
                )
                || (bool) $version->is_enabled) {
                throw new \DomainException(
                    'La version del conector no admite cambios en sus campos.'
                );
            }

            if ($field->exists) {
                if ((int) $field->getOriginal(
                    'commerce_connector_version_id'
                ) !== $versionId) {
                    throw new \DomainException(
                        'No se puede mover un campo a otra version.'
                    );
                }

                if ((string) $field->getOriginal('field_key')
                    !== (string) $field->field_key) {
                    throw new \DomainException(
                        'No se puede cambiar la clave tecnica del campo.'
                    );
                }
            }
        });

        static::deleting(function (): void {
            throw new \DomainException(
                'Los campos deben desactivarse, no eliminarse.'
            );
        });
    }

    protected $fillable = [
        'commerce_connector_version_id',
        'field_key',
        'label',
        'field_type',
        'credential_owner',
        'is_required',
        'is_secret',
        'is_active',
        'sort_order',
        'help_text',
        'validation_rules',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_secret' => 'boolean',
        'is_active' => 'boolean',
        'validation_rules' => 'array',
    ];

    public function connectorVersion(): BelongsTo
    {
        return $this->belongsTo(
            CommerceConnectorVersion::class,
            'commerce_connector_version_id'
        );
    }
}
