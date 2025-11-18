<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly string $materialName,
        public readonly float $available,
        public readonly float $required
    ) {
        parent::__construct("Not enough {$materialName}: required {$required}, available {$available}");
    }
}
