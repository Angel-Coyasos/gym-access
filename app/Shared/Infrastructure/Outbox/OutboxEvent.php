<?php

namespace App\Shared\Infrastructure\Outbox;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class OutboxEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'id',
        'aggregate_type',
        'aggregate_id',
        'event_type',
        'payload',
        'published_at',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'published_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (OutboxEvent $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
            $model->created_at = now();
        });
    }

    public function scopePending($query)
    {
        return $query->whereNull('published_at')->orderBy('created_at');
    }
}
