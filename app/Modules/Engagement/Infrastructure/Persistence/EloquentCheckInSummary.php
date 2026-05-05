<?php

namespace App\Modules\Engagement\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

class EloquentCheckInSummary extends Model
{
    protected $table = 'check_in_summaries';

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'member_id',
        'checked_in_at',
        'quote_body',
        'quote_author',
        'created_at',
    ];

    protected $casts = [
        'checked_in_at' => 'datetime',
        'created_at' => 'datetime',
    ];
}
