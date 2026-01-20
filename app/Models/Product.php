<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'category_id',
        'name',
        'description',
        'price',
        'image',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected $appends = ['cogs'];

    public function category()
    {
        return $this->belongsTo(ProductCategory::class);
    }

    public function recipes()
    {
        return $this->hasMany(ProductRecipe::class);
    }

    // Calculate Cost of Goods Sold based on current ingredient costs
    public function getCogsAttribute()
    {
        // Avoid N+1 issues by loading relations if needed, but for single access:
        $totalCost = 0;
        foreach ($this->recipes as $recipe) {
            $costPerUnit = $recipe->ingredient->cost_per_unit ?? 0;
            $totalCost += $recipe->quantity * $costPerUnit;
        }
        return round($totalCost, 2);
    }
}
