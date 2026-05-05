<?php

namespace App\Modules\Engagement\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

class EloquentDailyMotivation extends Model
{
    protected $table = 'daily_motivations';

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'check_in_id',
        'member_id',
        'quote_id',
        'quote_body',
        'quote_author',
        'assigned_at',
        'created_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'created_at' => 'datetime',
    ];
}
