<?php

declare(strict_types=1);

namespace Mavvix\SafeActions\Preflight;

use Illuminate\Database\Eloquent\Model;

final class SafeActionPreflight extends Model
{
    protected $table = 'safe_action_preflights';

    protected $guarded = [];

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'record_keys' => 'array',
            'record_count' => 'integer',
            'expires_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
        ];
    }
}
