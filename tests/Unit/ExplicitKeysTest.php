<?php

declare(strict_types=1);

namespace Mavvix\SafeActions\Tests\Unit;

use Mavvix\SafeActions\Exceptions\UnsafeSelection;
use Mavvix\SafeActions\Selection\ExplicitKeys;
use Mavvix\SafeActions\Tests\Fixtures\ProbeOrder;
use PHPUnit\Framework\TestCase;

final class ExplicitKeysTest extends TestCase
{
    public function test_key_order_does_not_change_digest(): void
    {
        $model = new ProbeOrder;
        $first = ExplicitKeys::forModel($model, [2, 1], 3);
        $second = ExplicitKeys::forModel($model, ['1', '2'], 3);

        self::assertSame($first->digest(), $second->digest());
    }

    public function test_mixed_type_duplicates_are_rejected(): void
    {
        $this->expectException(UnsafeSelection::class);

        ExplicitKeys::forModel(new ProbeOrder, [1, '1'], 3);
    }

    public function test_noncanonical_integer_keys_are_rejected(): void
    {
        $this->expectException(UnsafeSelection::class);

        ExplicitKeys::forModel(new ProbeOrder, ['0002'], 3);
    }

    public function test_bounded_limits_are_enforced(): void
    {
        $this->expectException(UnsafeSelection::class);

        ExplicitKeys::forModel(new ProbeOrder, [1, 2], 1);
    }
}
