<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/** Identificadores UUID v4 generados por la aplicación (api-contract §1). */
trait HasUuidV4
{
    public function initializeHasUuidV4(): void
    {
        $this->incrementing = false;
        $this->keyType = 'string';
    }

    protected static function bootHasUuidV4(): void
    {
        static::creating(function ($model): void {
            if (! $model->getKey()) {
                $model->setAttribute($model->getKeyName(), (string) Str::uuid());
            }
        });
    }
}
