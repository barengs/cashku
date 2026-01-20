<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ingredient extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'unit',
        'cost_per_unit',
        'minimum_stock',
    ];

    protected $casts = [
        'cost_per_unit' => 'decimal:2',
    ];

    public function stocks()
    {
        return $this->hasMany(BranchStock::class);
    }

    public function stockForBranch($branchId)
    {
        return $this->stocks()->where('branch_id', $branchId)->first()?->quantity ?? 0;
    }
}
