<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    protected $fillable = [
        'user_id',
        'category',
        'brand',
        'network_type',
        'name',
        'variant',
        'service_type',
        'purchase_price',
        'selling_price',
        'stock_quantity',
        'min_stock_alert',
        'sku',
        'image_url',
        'description',
        'is_active',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'stock_quantity' => 'integer',
        'min_stock_alert' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Get the user that owns the product.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for active products
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for low stock products
     */
    public function scopeLowStock($query)
    {
        return $query->whereColumn('stock_quantity', '<=', 'min_stock_alert');
    }

    /**
     * Category options
     */
    public static function categories(): array
    {
        return ['MOBILE', 'ACCESSORY', 'SERVICE'];
    }

    /**
     * Brand options
     */
    public static function brands(): array
    {
        return [
            'VIVO',
            'OPPO',
            'POCO',
            'REDMI',
            'REALME',
            'ITEL',
            'LAVA',
            'NOTHING',
            'INFINIX',
            'TECNO',
            'SAMSUNG',
            'NOKIA',
            'LAVA PHONE',
            'KYTES',
            'OTHER'
        ];
    }

    /**
     * Network type options
     */
    public static function networkTypes(): array
    {
        return ['4G', '5G'];
    }

    /**
     * Variant options
     */
    public static function variants(): array
    {
        return [
            '2/32',
            '4/64',
            '4/128',
            '6/128',
            '8/128',
            '8/256',
            '12/256',
            'OTHER'
        ];
    }

    /**
     * Service type options
     */
    public static function serviceTypes(): array
    {
        return [
            'DISPLAY CHANGE',
            'CC BOARD',
            'BATTERY',
            'INNER STRIP/OUTER KEY',
            'OTHER SERVICES',
            'KEYPAD PHONE SERVICE'
        ];
    }

    /**
     * Get profit margin
     */
    public function getProfitMarginAttribute(): float
    {
        if ($this->purchase_price <= 0) {
            return 0;
        }
        return (($this->selling_price - $this->purchase_price) / $this->purchase_price) * 100;
    }

    /**
     * Get profit amount
     */
    public function getProfitAmountAttribute(): float
    {
        return $this->selling_price - $this->purchase_price;
    }

    /**
     * Check if stock is low
     */
    public function getIsLowStockAttribute(): bool
    {
        return $this->stock_quantity <= $this->min_stock_alert;
    }

    /**
     * Generate SKU
     */
    public static function generateSku(string $category, string $brand, string $name): string
    {
        $prefix = strtoupper(substr($category, 0, 3));
        $brandCode = strtoupper(substr($brand ?? 'OTH', 0, 3));
        $nameCode = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $name), 0, 4));
        $random = strtoupper(substr(uniqid(), -4));

        return "{$prefix}-{$brandCode}-{$nameCode}-{$random}";
    }
}
