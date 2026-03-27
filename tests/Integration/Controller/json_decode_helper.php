<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Controller;

if (!function_exists(__NAMESPACE__ . '\json_decode')) {
    /**
     * Local shim for tests: accepts Response::getContent() output (string|false).
     */
    function json_decode(string|false $json, ?bool $associative = null, int $depth = 512, int $flags = 0): mixed
    {
        $safeDepth = $depth > 0 ? $depth : 512;

        return \json_decode((string) $json, $associative, $safeDepth, $flags);
    }
}
