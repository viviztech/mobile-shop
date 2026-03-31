<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sale extends Model
{
    protected $fillable = [
        'user_id',
        'cash_session_id',
        'product_id',
        'sale_date',
        'category',
        'brand',
        'network_type',
        'product_name',
        'variant',
        'service_product',
        'customer_name',
        'customer_mobile',
        'quantity',
        'total_amount',
        'gift',
        'remarks',
    ];

    /**
     * Get the session associated with the sale.
     */
    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    protected $casts = [
        'sale_date' => 'date',
        'total_amount' => 'decimal:2',
        'quantity' => 'integer',
        'product_id' => 'integer',
    ];

    /**
     * Get the product associated with the sale.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the user that owns the sale.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
            'ACCESSORY'
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
            'ACCESSORY',
            'SERVICE'
        ];
    }

    /**
     * Service product options
     */
    public static function serviceProducts(): array
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
     * Gift options
     */
    public static function gifts(): array
    {
        return [
            'BOAT NECKBAND',
            'TREAMS NECKBAND',
            'KDM NECKBAND',
            'KDM EARPODS',
            'BOAT EARBUDS',
            'FLASK',
            'TEMPERED',
            'BACK CASE',
            'NO GIFT'
        ];
    }
}
