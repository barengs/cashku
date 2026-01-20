<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Table extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'branch_id',
        'number',
        'capacity',
        'status', // available, occupied, reserved
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
