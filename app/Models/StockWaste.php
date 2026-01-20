<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockWaste extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'branch_id',
        'waste_date',
        'note',
    ];

    protected $casts = [
        'waste_date' => 'date',
    ];

    public function items()
    {
        return $this->hasMany(StockWasteItem::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
