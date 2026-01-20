<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockAdjustmentItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'stock_adjustment_id',
        'ingredient_id',
        'system_stock',
        'actual_stock',
        'difference',
    ];

    protected $casts = [
        'system_stock' => 'decimal:2',
        'actual_stock' => 'decimal:2',
        'difference' => 'decimal:2',
    ];

    public function adjustment()
    {
        return $this->belongsTo(StockAdjustment::class, 'stock_adjustment_id');
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }
}
