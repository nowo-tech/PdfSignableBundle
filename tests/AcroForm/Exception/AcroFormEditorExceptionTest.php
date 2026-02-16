<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\AcroForm\Exception;

use Nowo\PdfSignableBundle\AcroForm\Exception\AcroFormEditorException;
use PHPUnit\Framework\TestCase;

final class AcroFormEditorExceptionTest extends TestCase
{
    public function testExtendsRuntimeException(): void
    {
        $e = new AcroFormEditorException('PDF has no form');
        self::assertInstanceOf(\RuntimeException::class, $e);
    }

    public function testGetMessage(): void
    {
        $e = new AcroFormEditorException('Field not found');
        self::assertSame('Field not found', $e->getMessage());
    }
}
