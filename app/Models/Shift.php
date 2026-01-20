<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'branch_id',
        'user_id',
        'start_time',
        'end_time',
        'starting_cash',
        'ending_cash',
        'actual_cash',
        'status', // open, closed
    ];

    protected $casts = [
        'starting_cash' => 'decimal:2',
        'ending_cash' => 'decimal:2',
        'actual_cash' => 'decimal:2',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
