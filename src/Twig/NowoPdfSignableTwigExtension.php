<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Twig;

use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension for PdfSignableBundle.
 *
 * Exposes nowo_pdf_signable_include_assets() so the form theme can include
 * CSS and JS only once per request when multiple SignatureCoordinatesType widgets are rendered.
 *
 * @see src/Resources/views/form/theme.html.twig Uses this function in signature_coordinates_widget
 */
final class NowoPdfSignableTwigExtension extends AbstractExtension
{
    private const REQUEST_ATTR = '_nowo_pdf_signable_assets_included';

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('nowo_pdf_signable_include_assets', [$this, 'shouldIncludeAssets']),
        ];
    }

    /**
     * Returns true only the first time per request; then false.
     * Use in the form theme to output the CSS link and scripts only once.
     */
    public function shouldIncludeAssets(): bool
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return true;
        }
        if ($request->attributes->get(self::REQUEST_ATTR)) {
            return false;
        }
        $request->attributes->set(self::REQUEST_ATTR, true);

        return true;
    }
}
