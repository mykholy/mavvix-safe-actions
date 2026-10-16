<?php

declare(strict_types=1);

namespace Mavvix\SafeActions\Execution;

final readonly class ExecutionResult
{
    public function __construct(
        public string $preflightId,
        public int $affectedRecords,
    ) {}
}
