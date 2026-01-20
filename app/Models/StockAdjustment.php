<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockAdjustment extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'branch_id',
        'adjustment_date',
        'note',
        'status',
    ];

    protected $casts = [
        'adjustment_date' => 'date',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function items()
    {
        return $this->hasMany(StockAdjustmentItem::class);
    }
}
