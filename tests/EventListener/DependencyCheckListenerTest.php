<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\EventListener;

use Nowo\PdfSignableBundle\Checker\DependencyCheckerInterface;
use Nowo\PdfSignableBundle\EventListener\DependencyCheckListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class DependencyCheckListenerTest extends TestCase
{
    public function testDoesNothingWhenDebugFalse(): void
    {
        $checker = $this->createMock(DependencyCheckerInterface::class);
        $checker->expects(self::never())->method('check');

        $listener = new DependencyCheckListener($checker, false);
        $request  = Request::create('/', 'GET');
        $request->attributes->set('_route', 'nowo_pdf_signable_index');
        $event = new ControllerEvent(
            $this->createMock(HttpKernelInterface::class),
            static fn () => null,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );

        $listener($event);

        self::assertFalse($request->attributes->has('nowo_pdf_signable_dependency_failures'));
        self::assertFalse($request->attributes->has('nowo_pdf_signable_dependency_warnings'));
    }

    public function testDoesNothingWhenRequestIsPost(): void
    {
        $checker = $this->createMock(DependencyCheckerInterface::class);
        $checker->expects(self::never())->method('check');

        $listener = new DependencyCheckListener($checker, true);
        $request  = Request::create('/', 'POST');
        $request->attributes->set('_route', 'nowo_pdf_signable_index');
        $event = new ControllerEvent(
            $this->createMock(HttpKernelInterface::class),
            static fn () => null,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );

        $listener($event);

        self::assertFalse($request->attributes->has('nowo_pdf_signable_dependency_failures'));
    }

    public function testDoesNothingWhenRouteNotBundle(): void
    {
        $checker = $this->createMock(DependencyCheckerInterface::class);
        $checker->expects(self::never())->method('check');

        $listener = new DependencyCheckListener($checker, true);
        $request  = Request::create('/', 'GET');
        $request->attributes->set('_route', 'app_home');
        $event = new ControllerEvent(
            $this->createMock(HttpKernelInterface::class),
            static fn () => null,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );

        $listener($event);

        self::assertFalse($request->attributes->has('nowo_pdf_signable_dependency_failures'));
    }

    public function testDoesNothingWhenRouteMissing(): void
    {
        $checker = $this->createMock(DependencyCheckerInterface::class);
        $checker->expects(self::never())->method('check');

        $listener = new DependencyCheckListener($checker, true);
        $request  = Request::create('/', 'GET');
        $event    = new ControllerEvent(
            $this->createMock(HttpKernelInterface::class),
            static fn () => null,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );

        $listener($event);

        self::assertFalse($request->attributes->has('nowo_pdf_signable_dependency_failures'));
    }

    public function testRunsCheckerAndSetsAttributesWhenDebugTrueGetAndBundleRoute(): void
    {
        $checker = $this->createMock(DependencyCheckerInterface::class);
        $checker->expects(self::once())->method('check')->willReturn([
            'failures' => ['Some failure'],
            'warnings' => ['Some warning'],
        ]);

        $listener = new DependencyCheckListener($checker, true);
        $request  = Request::create('/', 'GET');
        $request->attributes->set('_route', 'nowo_pdf_signable_index');
        $event = new ControllerEvent(
            $this->createMock(HttpKernelInterface::class),
            static fn () => null,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );

        $listener($event);

        self::assertTrue($request->attributes->has('nowo_pdf_signable_dependency_failures'));
        self::assertTrue($request->attributes->has('nowo_pdf_signable_dependency_warnings'));
        self::assertSame(['Some failure'], $request->attributes->get('nowo_pdf_signable_dependency_failures'));
        self::assertSame(['Some warning'], $request->attributes->get('nowo_pdf_signable_dependency_warnings'));
    }

    public function testRunsCheckerForAcroFormOverridesRoute(): void
    {
        $checker = $this->createMock(DependencyCheckerInterface::class);
        $checker->expects(self::once())->method('check')->willReturn(['failures' => [], 'warnings' => []]);

        $listener = new DependencyCheckListener($checker, true);
        $request  = Request::create('/', 'GET');
        $request->attributes->set('_route', 'nowo_pdf_signable_acroform_overrides');
        $event = new ControllerEvent(
            $this->createMock(HttpKernelInterface::class),
            static fn () => null,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );

        $listener($event);

        self::assertSame([], $request->attributes->get('nowo_pdf_signable_dependency_failures'));
        self::assertSame([], $request->attributes->get('nowo_pdf_signable_dependency_warnings'));
    }

    public function testUsesCachedResultWhenRequestAlreadyHasCheckResult(): void
    {
        $checker = $this->createMock(DependencyCheckerInterface::class);
        $checker->expects(self::never())->method('check');

        $listener = new DependencyCheckListener($checker, true);
        $request  = Request::create('/', 'GET');
        $request->attributes->set('_route', 'nowo_pdf_signable_index');
        $request->attributes->set('nowo_pdf_signable_dependency_check_result', [
            'failures' => ['Cached failure'],
            'warnings' => ['Cached warning'],
        ]);
        $event = new ControllerEvent(
            $this->createMock(HttpKernelInterface::class),
            static fn () => null,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );

        $listener($event);

        self::assertSame(['Cached failure'], $request->attributes->get('nowo_pdf_signable_dependency_failures'));
        self::assertSame(['Cached warning'], $request->attributes->get('nowo_pdf_signable_dependency_warnings'));
    }
}
