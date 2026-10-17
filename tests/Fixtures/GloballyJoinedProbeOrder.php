<?php

declare(strict_types=1);

namespace Mavvix\SafeActions\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class GloballyJoinedProbeOrder extends Model
{
    protected $table = 'probe_orders';

    protected $guarded = [];

    protected static function booted(): void
    {
        self::addGlobalScope('duplicate-tenant-join', static function (Builder $builder): void {
            $builder->join('probe_orders as related_orders', 'related_orders.tenant_id', '=', 'probe_orders.tenant_id');
        });
    }
}
