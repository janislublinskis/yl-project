<?php

namespace Yl\Products\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Yl\Helper\Traits\HasTimestampScopes;
use Yl\Helper\Traits\LogsActivity;
use Yl\Products\Database\Factories\ProductFactory;

/**
 * Product Model
 *
 * Represents a sellable product in the catalogue.
 *
 * @property int         $id
 * @property string      $name
 * @property string|null $description
 * @property int         $price         Price in smallest units
 * @property int         $stock         Unit count in inventory
 * @property string      $status        'active' | 'inactive' | 'archived'
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class Product extends Model
{
    use HasFactory;
    use SoftDeletes;
    use LogsActivity;       // auto-logs created / updated / deleted
    use HasTimestampScopes; // scopeRecent(), scopeCreatedAfter() etc.

    protected $table = 'products';

    protected $fillable = [
        'name',
        'description',
        'price',
        'stock',
        'status',
    ];

    protected $casts = [
        'price' => 'integer',
        'stock' => 'integer',
    ];

    public const STATUSES = ['active', 'inactive', 'archived'];

    /**
     * Point Laravel's factory resolution to ProductFactory.
     * Required because the factory lives inside the package,
     * not in the host app's database/factories directory.
     */
    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }

    // ── Scopes ────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeInStock($query)
    {
        return $query->where('stock', '>', 0);
    }
}
