<?php

namespace App\Modules\AccessControl\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

class EloquentCheckIn extends Model
{
    protected $table = 'check_ins';

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'member_id',
        'checked_in_at',
        'created_at',
    ];

    protected $casts = [
        'checked_in_at' => 'datetime',
        'created_at' => 'datetime',
    ];
}
