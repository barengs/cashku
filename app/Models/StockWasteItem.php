<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockWasteItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'stock_waste_id',
        'ingredient_id',
        'quantity',
        'reason',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
    ];

    public function waste()
    {
        return $this->belongsTo(StockWaste::class, 'stock_waste_id');
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }
}
