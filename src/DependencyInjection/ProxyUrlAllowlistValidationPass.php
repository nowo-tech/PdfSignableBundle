<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function sprintf;

use const E_USER_WARNING;
use const PREG_NO_ERROR;

/**
 * In dev, validates regex patterns in proxy_url_allowlist (entries starting with #)
 * and triggers a warning if any pattern is invalid.
 */
final class ProxyUrlAllowlistValidationPass implements CompilerPassInterface
{
    private const PARAM_ALLOWLIST = 'nowo_pdf_signable.proxy_url_allowlist';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->getParameter('kernel.debug')) {
            return;
        }

        if (!$container->hasParameter(self::PARAM_ALLOWLIST)) {
            return;
        }

        /** @var list<string> $allowlist */
        $allowlist = $container->getParameter(self::PARAM_ALLOWLIST);

        foreach ($allowlist as $pattern) {
            if ($pattern === '' || !str_starts_with($pattern, '#')) {
                continue;
            }
            @preg_match($pattern, '');
            if (preg_last_error() !== PREG_NO_ERROR) {
                trigger_error(
                    sprintf(
                        '[NowoPdfSignableBundle] Invalid regex in proxy_url_allowlist: %s (preg error: %d). Fix or remove the pattern.',
                        $pattern,
                        preg_last_error(),
                    ),
                    E_USER_WARNING,
                );
            }
        }
    }
}
