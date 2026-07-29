<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function sprintf;

use const PREG_NO_ERROR;

/**
 * Validates proxy_url_allowlist policy and regex patterns (REQ-SEC-004).
 *
 * - When proxy_enabled and proxy_url_allowlist_required: empty allowlist fails compilation.
 * - Invalid #regex entries always fail compilation (not only in debug).
 */
final class ProxyUrlAllowlistValidationPass implements CompilerPassInterface
{
    private const PARAM_ALLOWLIST          = 'nowo_pdf_signable.proxy_url_allowlist';
    private const PARAM_ALLOWLIST_REQUIRED = 'nowo_pdf_signable.proxy_url_allowlist_required';
    private const PARAM_PROXY_ENABLED      = 'nowo_pdf_signable.proxy_enabled';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter(self::PARAM_ALLOWLIST)) {
            return;
        }

        /** @var list<string> $allowlist */
        $allowlist    = $container->getParameter(self::PARAM_ALLOWLIST);
        $proxyEnabled = $container->hasParameter(self::PARAM_PROXY_ENABLED)
            ? (bool) $container->getParameter(self::PARAM_PROXY_ENABLED)
            : true;
        $allowlistRequired = $container->hasParameter(self::PARAM_ALLOWLIST_REQUIRED)
            && (bool) $container->getParameter(self::PARAM_ALLOWLIST_REQUIRED);

        if ($proxyEnabled && $allowlistRequired && $allowlist === []) {
            throw new InvalidConfigurationException('nowo_pdf_signable.proxy_url_allowlist_required is true but proxy_url_allowlist is empty. Add host patterns (or set proxy_url_allowlist_required: false for local demos only).');
        }

        foreach ($allowlist as $pattern) {
            if ($pattern === '' || !str_starts_with($pattern, '#')) {
                continue;
            }
            @preg_match($pattern, '');
            if (preg_last_error() !== PREG_NO_ERROR) {
                throw new InvalidConfigurationException(sprintf('nowo_pdf_signable.proxy_url_allowlist has invalid regex: %s (preg error: %d). Fix or remove the pattern.', $pattern, preg_last_error()));
            }
        }
    }
}
