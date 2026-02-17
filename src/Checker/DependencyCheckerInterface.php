<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Checker;

/**
 * Contract for dependency checks (PHP, extensions, AcroForm scripts, assets).
 *
 * @see DependencyChecker
 */
interface DependencyCheckerInterface
{
    /**
     * Runs all checks.
     *
     * @return array{failures: list<string>, warnings: list<string>}
     */
    public function check(): array;
}
