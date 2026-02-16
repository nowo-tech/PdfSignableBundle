<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\AcroForm;

/**
 * Builds a clean environment for Python subprocesses (apply script, extractor, process script).
 *
 * Strips PYTHONPATH, PYTHONHOME, VIRTUAL_ENV, PYTHONUSERBASE, PYTHONNOUSERSITE so that
 * site-packages resolution is predictable (e.g. under FrankenPHP or FPM where the parent
 * env might point at a different Python or venv).
 *
 * @internal
 */
final class PythonProcessEnv
{
    /**
     * Returns an env map suitable for Symfony Process, with Python-related vars cleared.
     *
     * PATH is prepended with /usr/local/bin:/usr/bin:/bin so system Python is findable.
     *
     * @return array<string, string> Environment variables for the subprocess
     */
    public static function build(): array
    {
        $env = getenv();
        if (!\is_array($env)) {
            return [];
        }
        unset(
            $env['PYTHONPATH'],
            $env['PYTHONHOME'],
            $env['VIRTUAL_ENV'],
            $env['PYTHONUSERBASE'],
            $env['PYTHONNOUSERSITE']
        );
        $env['PATH'] = '/usr/local/bin:/usr/bin:/bin'.(isset($env['PATH']) && '' !== $env['PATH'] ? ':'.$env['PATH'] : '');

        return array_filter($env, static fn ($v): bool => false !== $v);
    }
}
