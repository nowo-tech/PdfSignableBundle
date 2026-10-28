<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Unit\EventSubscriber;

use Nowo\PdfSignableBundle\Controller\AcroFormOverridesController;
use Nowo\PdfSignableBundle\Controller\SignatureController;
use Nowo\PdfSignableBundle\EventSubscriber\PdfSignableAccessSubscriber;
use Nowo\PdfSignableBundle\Security\PdfSignableAccessCheckerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class PdfSignableAccessSubscriberTest extends TestCase
{
    public function testSubscribesToKernelController(): void
    {
        self::assertSame([KernelEvents::CONTROLLER => ['onKernelController', 0]], PdfSignableAccessSubscriber::getSubscribedEvents());
    }

    public function testAcroFormEndpointsAndProxyAreDeniedWithoutAccess(): void
    {
        $subscriber = new PdfSignableAccessSubscriber($this->checker(false));

        foreach ([[AcroFormOverridesController::class, 'process'], [SignatureController::class, 'proxyPdf']] as [$class, $method]) {
            try {
                $subscriber->onKernelController($this->event($class, $method));
                self::fail($class . '::' . $method . ' must be guarded');
            } catch (AccessDeniedHttpException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testGrantedUsersAndUnguardedActionsPass(): void
    {
        (new PdfSignableAccessSubscriber($this->checker(true)))->onKernelController($this->event(AcroFormOverridesController::class, 'apply'));

        $never = $this->createMock(PdfSignableAccessCheckerInterface::class);
        $never->expects(self::never())->method('canAccess');
        $subscriber = new PdfSignableAccessSubscriber($never);
        $subscriber->onKernelController($this->event(SignatureController::class, 'index'));
        $subscriber->onKernelController(new ControllerEvent($this->createStub(HttpKernelInterface::class), static fn (): null => null, new Request(), HttpKernelInterface::MAIN_REQUEST));
    }

    private function checker(bool $granted): PdfSignableAccessCheckerInterface
    {
        $checker = $this->createStub(PdfSignableAccessCheckerInterface::class);
        $checker->method('canAccess')->willReturn($granted);

        return $checker;
    }

    /**
     * @param class-string $class
     */
    private function event(string $class, string $method): ControllerEvent
    {
        $controller = [(new ReflectionClass($class))->newInstanceWithoutConstructor(), $method];
        self::assertIsCallable($controller);

        return new ControllerEvent($this->createStub(HttpKernelInterface::class), $controller, new Request(), HttpKernelInterface::MAIN_REQUEST);
    }
}
