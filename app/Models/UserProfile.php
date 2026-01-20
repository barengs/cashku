<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'phone_number',
        'address',
        'birth_date',
        'gender',
        'nik',
        'photo',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
