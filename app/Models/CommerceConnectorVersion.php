<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommerceConnectorVersion extends Model
{
    protected static function booted(): void
    {
        static::updating(function (self $version): void {
            $originalStatus = (string) $version->getOriginal('status');
            $newStatus = (string) $version->status;

            $originallyEnabled = (bool) $version->getOriginal('is_enabled');

            if ($originallyEnabled) {
                throw new \DomainException(
                    'Un conector habilitado no puede modificarse desde este modelo.'
                );
            }

            if (! in_array(
                $originalStatus,
                ['ready', 'suspended', 'deprecated'],
                true
            )) {
                return;
            }

            if (! in_array(
                $newStatus,
                ['ready', 'suspended', 'deprecated'],
                true
            )) {
                throw new \DomainException(
                    'No se puede regresar una version publicada a borrador o pruebas.'
                );
            }

            foreach (array_keys($version->getDirty()) as $field) {
                if (! in_array($field, ['status', 'updated_at'], true)) {
                    throw new \DomainException(
                        'La configuracion de una version publicada es inmutable.'
                    );
                }
            }
        });

        static::deleting(function (): void {
            throw new \DomainException(
                'Las versiones de conectores deben conservar su historial.'
            );
        });
    }

    protected $fillable = [
        'commerce_channel_id',
        'version',
        'adapter_key',
        'api_version',
        'auth_type',
        'status',
        'capabilities',
        'settings_schema',
        'connection_instructions',
        'is_enabled',
    ];

    protected $casts = [
        'capabilities' => 'array',
        'settings_schema' => 'array',
        'is_enabled' => 'boolean',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(
            CommerceChannel::class,
            'commerce_channel_id'
        );
    }

    public function authFields(): HasMany
    {
        return $this->hasMany(
            CommerceAuthField::class,
            'commerce_connector_version_id'
        );
    }
}
