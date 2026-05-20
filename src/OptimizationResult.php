<?php

namespace Sennik\AssetOptimizer;

class OptimizationResult
{
    public function __construct(
        public readonly bool $changed,
        public readonly string $reason,
        public readonly int $originalBytes = 0,
        public readonly int $newBytes = 0,
        public readonly int $originalWidth = 0,
        public readonly int $originalHeight = 0,
        public readonly int $newWidth = 0,
        public readonly int $newHeight = 0,
        public readonly ?string $error = null,
    ) {
    }

    public static function skipped(string $reason): self
    {
        return new self(changed: false, reason: $reason);
    }

    public static function failed(string $error): self
    {
        return new self(changed: false, reason: 'error', error: $error);
    }
}
