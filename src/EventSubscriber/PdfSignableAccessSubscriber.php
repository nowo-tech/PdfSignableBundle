<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\EventSubscriber;

use Nowo\PdfSignableBundle\Controller\AcroFormOverridesController;
use Nowo\PdfSignableBundle\Controller\SignatureController;
use Nowo\PdfSignableBundle\Security\PdfSignableAccessCheckerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

use function in_array;
use function is_array;

/**
 * Guards the AcroForm endpoints (they read client PDFs and run Python for extract / apply / process) and the PDF proxy
 * (it fetches remote PDFs) with {@see PdfSignableAccessCheckerInterface}, whatever the routes are named or mounted.
 */
final readonly class PdfSignableAccessSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private PdfSignableAccessCheckerInterface $accessChecker,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::CONTROLLER => ['onKernelController', 0]];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $controller = $event->getController();
        if (!is_array($controller)) {
            return;
        }

        [$object, $method] = $controller;
        // The signature form page dispatches host events with the submitted pdfUrl: same gate as the proxy (3.7.0).
        $guarded = $object instanceof AcroFormOverridesController
            || ($object instanceof SignatureController && in_array($method, ['proxyPdf', 'index'], true));

        if ($guarded && !$this->accessChecker->canAccess()) {
            throw new AccessDeniedHttpException('Access to the PDF signable endpoints is denied.');
        }
    }
}
