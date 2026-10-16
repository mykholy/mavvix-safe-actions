<?php

declare(strict_types=1);

namespace Mavvix\SafeActions\Selection;

final readonly class SelectionInspection
{
    public function __construct(
        public int $recordCount,
        public string $selectionDigest,
    ) {}
}
