<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Submission;

use LogicException;
use WeakMap;

/**
 * Holds the request-derived and signature values of the submission VOs ({@see SignatureSubmission} audit metadata,
 * {@see SignatureBox} signature image, {@see PdfRequestContext} client IP and user agent) outside the objects, so no
 * dump of a VO (VarDumper / `dump()`, `var_export()`, `print_r()`, `(array)` cast, `json_encode()`) can reach them.
 *
 * Keyed by the VO in a {@see WeakMap}: an entry disappears with its VO, so nothing outlives the request in a FrankenPHP
 * worker (no reset needed, REQ-RUNTIME-002).
 *
 * @internal
 */
final class SensitiveValues
{
    /** @var WeakMap<object, array<string, mixed>>|null */
    private static ?WeakMap $values = null; // @phpstan-ignore frankenphp.worker.noMutableStaticProperty (WeakMap keyed by the per-request VO)

    /**
     * @param array<string, mixed> $values
     */
    public static function put(object $owner, array $values): void
    {
        // @igor-ignore - WeakMap keyed by the per-request VO; entries are collected with it.
        self::$values ??= new WeakMap(); // @phpstan-ignore frankenphp.worker.noMutableStaticProperty
        // @igor-ignore - WeakMap keyed by the per-request VO; entries are collected with it.
        self::$values[$owner] = $values; // @phpstan-ignore frankenphp.worker.noMutableStaticProperty
    }

    public static function get(object $owner, string $key): mixed
    {
        if (!isset(self::$values[$owner])) {
            // @codeCoverageIgnoreStart
            throw new LogicException('This value object has no data (it was cloned or unserialized).');
            // @codeCoverageIgnoreEnd
        }

        return self::$values[$owner][$key] ?? null;
    }

    public static function refuseSerialization(object $owner): LogicException
    {
        return new LogicException($owner::class . ' cannot be serialized: it carries signature or request data.');
    }
}
