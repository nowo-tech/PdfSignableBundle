<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\AcroForm\Storage;

use Nowo\PdfSignableBundle\AcroForm\AcroFormOverrides;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\HttpFoundation\RequestStack;

use function is_array;

/**
 * Session-based storage for AcroForm overrides.
 *
 * Uses session key: nowo_pdf_signable.acroform_overrides.{documentKey}.
 * Default implementation when acroform.overrides_storage is "session".
 * The app can override the alias in config with a custom service id.
 */
#[AsAlias(id: AcroFormOverridesStorageInterface::class, public: false)]
final class SessionAcroFormOverridesStorage implements AcroFormOverridesStorageInterface
{
    private const SESSION_KEY_PREFIX = 'nowo_pdf_signable.acroform_overrides.';

    /**
     * @param RequestStack $requestStack Used to access the current request session
     */
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function get(string $documentKey): ?AcroFormOverrides
    {
        $session = $this->requestStack->getCurrentRequest()?->getSession();
        if (!$session) {
            return null;
        }
        $key  = self::SESSION_KEY_PREFIX . $this->sanitizeKey($documentKey);
        $data = $session->get($key);
        if (!is_array($data)) {
            return null;
        }

        return AcroFormOverrides::fromArray($data);
    }

    public function set(string $documentKey, AcroFormOverrides $overrides): void
    {
        $session = $this->requestStack->getCurrentRequest()?->getSession();
        if (!$session) {
            return;
        }
        $key = self::SESSION_KEY_PREFIX . $this->sanitizeKey($documentKey);
        $session->set($key, $overrides->toArray());
    }

    public function remove(string $documentKey): void
    {
        $session = $this->requestStack->getCurrentRequest()?->getSession();
        if (!$session) {
            return;
        }
        $session->remove(self::SESSION_KEY_PREFIX . $this->sanitizeKey($documentKey));
    }

    /**
     * Sanitizes the document key for use as a session key (alphanumeric, underscore, hyphen, dot).
     *
     * @return string Sanitized key
     */
    private function sanitizeKey(string $documentKey): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $documentKey) ?? $documentKey;
    }
}
