<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle;

use Nowo\PdfSignableBundle\DependencyInjection\PdfSignableExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Symfony bundle for PDF signable form field.
 *
 * Provides a form field that receives a PDF URL, renders it on screen
 * and allows defining signature coordinates by click (and drag/resize).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 */
final class NowoPdfSignableBundle extends Bundle
{
    /**
     * Returns the container extension (PdfSignableExtension).
     *
     * @return ExtensionInterface|null
     */
    public function getContainerExtension(): ?ExtensionInterface
    {
        return $this->extension ??= new PdfSignableExtension();
    }
}
