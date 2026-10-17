<?php

declare(strict_types=1);

namespace Mavvix\SafeActions\Preflight;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property int $record_count
 * @property list<string> $record_keys
 * @property string $selection_digest
 * @property string|null $scope_digest
 * @property string|null $record_state_digest
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $consumed_at
 */
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
