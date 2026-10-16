<?php

declare(strict_types=1);

namespace Mavvix\SafeActions\Preflight;

use DateTimeImmutable;

final readonly class PreflightTicket
{
    public function __construct(
        public string $id,
        public int $recordCount,
        public DateTimeImmutable $expiresAt,
    ) {}
}
