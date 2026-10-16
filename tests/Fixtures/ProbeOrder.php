<?php

declare(strict_types=1);

namespace Mavvix\SafeActions\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

final class ProbeOrder extends Model
{
    protected $table = 'probe_orders';

    protected $guarded = [];
}
