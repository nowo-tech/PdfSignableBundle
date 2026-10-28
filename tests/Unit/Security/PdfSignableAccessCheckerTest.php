<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Unit\Security;

use Nowo\PdfSignableBundle\Security\AllowAllPdfSignableAccessChecker;
use Nowo\PdfSignableBundle\Security\ConfigurablePdfSignableAccessChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class PdfSignableAccessCheckerTest extends TestCase
{
    public function testAnyConfiguredRoleGrantsAccess(): void
    {
        $authorization = $this->createMock(AuthorizationCheckerInterface::class);
        $authorization->method('isGranted')->willReturnCallback(static fn (mixed $attribute): bool => $attribute === 'ROLE_SIGNER');

        self::assertTrue((new ConfigurablePdfSignableAccessChecker($authorization, ['ROLE_USER', 'ROLE_SIGNER']))->canAccess());
    }

    public function testNoMatchingRoleOrEmptyListDenies(): void
    {
        $authorization = $this->createMock(AuthorizationCheckerInterface::class);
        $authorization->method('isGranted')->willReturn(false);

        self::assertFalse((new ConfigurablePdfSignableAccessChecker($authorization, ['ROLE_USER']))->canAccess());
        self::assertFalse((new ConfigurablePdfSignableAccessChecker($this->createMock(AuthorizationCheckerInterface::class), []))->canAccess());
    }

    public function testAllowAllGrantsEverybody(): void
    {
        self::assertTrue((new AllowAllPdfSignableAccessChecker())->canAccess());
    }
}
