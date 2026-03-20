<?php

namespace Yl\Helper\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * HasTimestampScopes
 *
 * Provides reusable Eloquent query scopes for ordering and filtering
 * by the standard created_at / updated_at columns.
 *
 * Usage:
 *   Product::recent()->paginate(15);
 *   Post::createdAfter('2024-01-01')->get();
 */
trait HasTimestampScopes
{
    /**
     * Order by newest first.
     */
    public function scopeRecent(Builder $query): Builder
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Order by oldest first.
     * Note: Laravel's Model already defines scopeOldest(), so we prefix
     * ours to avoid a conflict — use scopeEarliest() instead.
     */
    public function scopeEarliest(Builder $query): Builder
    {
        return $query->orderBy('created_at', 'asc');
    }

    /**
     * Filter to records created on or after a given date.
     *
     * @param  string  $date  Any date string parseable by MySQL (e.g. '2024-01-01')
     */
    public function scopeCreatedAfter(Builder $query, string $date): Builder
    {
        return $query->where('created_at', '>=', $date);
    }

    /**
     * Filter to records created before a given date.
     */
    public function scopeCreatedBefore(Builder $query, string $date): Builder
    {
        return $query->where('created_at', '<', $date);
    }
}
