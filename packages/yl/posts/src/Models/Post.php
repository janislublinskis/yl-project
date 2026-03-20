<?php

namespace Yl\Posts\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Yl\Helper\Traits\HasTimestampScopes;
use Yl\Helper\Traits\LogsActivity;
use Yl\Posts\Database\Factories\PostFactory;

/**
 * Post Model
 *
 * Represents a blog post or article in the Posts module.
 *
 * @property int              $id
 * @property string           $title
 * @property string           $slug           URL-safe, unique identifier
 * @property string           $body
 * @property string           $status         'draft' | 'published' | 'archived'
 * @property \Carbon\Carbon|null $published_at Set when status → published
 * @property \Carbon\Carbon   $created_at
 * @property \Carbon\Carbon   $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class Post extends Model
{
    use HasFactory;
    use SoftDeletes;
    use LogsActivity;
    use HasTimestampScopes;

    protected $table = 'posts';

    protected $fillable = [
        'title',
        'slug',
        'body',
        'status',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public const STATUSES = ['draft', 'published', 'archived'];

    protected static function newFactory(): PostFactory
    {
        return PostFactory::new();
    }

    // ── Lifecycle hooks ───────────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();

        // Auto-generate a URL-safe slug from the title on creation.
        // If a slug is provided explicitly in the payload, that takes precedence.
        static::creating(function (self $post) {
            if (empty($post->slug)) {
                $post->slug = Str::slug($post->title);
            }
        });
    }

    // ── Scopes ────────────────────────────────────────────────────

    public function scopePublished($query)
    {
        return $query->where('status', 'published')
                     ->whereNotNull('published_at');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }
}
