<?php

namespace Yl\Helper\Traits;

use Illuminate\Support\Facades\Log;

/**
 * LogsActivity
 *
 * Attach to any Eloquent model to get automatic activity logging
 * on the three most important lifecycle events.
 *
 * Usage:
 *   class Product extends Model
 *   {
 *       use LogsActivity;
 *   }
 *
 * Produces log entries like:
 *   [info] Product created {"id": 5}
 *   [info] Product updated {"id": 5, "changes": {"price": "19.99"}}
 *   [info] Product deleted {"id": 5}
 */
trait LogsActivity
{
    /**
     * Boot the trait.
     * Laravel automatically calls boot{TraitName}() when a model is booted.
     */
    public static function bootLogsActivity(): void
    {
        static::created(function ($model) {
            Log::info(class_basename($model) . ' created', [
                'id' => $model->getKey(),
            ]);
        });

        static::updated(function ($model) {
            Log::info(class_basename($model) . ' updated', [
                'id'      => $model->getKey(),
                // getDirty() returns only the changed attributes
                'changes' => $model->getDirty(),
            ]);
        });

        static::deleted(function ($model) {
            Log::info(class_basename($model) . ' deleted', [
                'id' => $model->getKey(),
            ]);
        });
    }
}
