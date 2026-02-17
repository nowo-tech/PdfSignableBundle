<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\EventListener;

use Nowo\PdfSignableBundle\Checker\DependencyCheckerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

use function is_string;

/**
 * When debug is enabled, runs dependency checks before rendering bundle form pages (GET)
 * and stores failures/warnings in the request so templates can display them.
 *
 * Only runs for GET requests whose route name starts with "nowo_pdf_signable_".
 */
#[AsEventListener(event: KernelEvents::CONTROLLER, priority: 0)]
final class DependencyCheckListener
{
    private const ROUTE_PREFIX = 'nowo_pdf_signable_';

    public function __construct(
        private readonly DependencyCheckerInterface $checker,
        #[Autowire(param: 'nowo_pdf_signable.debug')]
        private readonly bool $debug = false,
    ) {
    }

    public function __invoke(ControllerEvent $event): void
    {
        if (!$this->debug) {
            return;
        }

        $request = $event->getRequest();
        if (!$request->isMethod('GET')) {
            return;
        }

        $route = $request->attributes->get('_route');
        if (!is_string($route) || !str_starts_with($route, self::ROUTE_PREFIX)) {
            return;
        }

        $cacheKey = 'nowo_pdf_signable_dependency_check_result';
        if ($request->attributes->has($cacheKey)) {
            $result = $request->attributes->get($cacheKey);
        } else {
            $result = $this->checker->check();
            $request->attributes->set($cacheKey, $result);
        }
        $request->attributes->set('nowo_pdf_signable_dependency_failures', $result['failures']);
        $request->attributes->set('nowo_pdf_signable_dependency_warnings', $result['warnings']);
    }
}
