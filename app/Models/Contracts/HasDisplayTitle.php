<?php

declare(strict_types=1);

namespace App\Models\Contracts;

/**
 * Resolves a human-readable title for a model, for callers holding only a polymorphic reference
 * (activity log subject, global search result).
 */
interface HasDisplayTitle {
    public function getDisplayTitle(): string;
}
