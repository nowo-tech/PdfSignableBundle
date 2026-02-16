<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Model;

use Nowo\PdfSignableBundle\Model\AuditMetadata;
use PHPUnit\Framework\TestCase;

/**
 * Tests for AuditMetadata constants (recommended keys for audit trail).
 */
final class AuditMetadataTest extends TestCase
{
    public function testAllConstantsAreDefinedAndNonEmpty(): void
    {
        $expected = [
            'SUBMITTED_AT',
            'IP',
            'USER_AGENT',
            'USER_ID',
            'SESSION_ID',
            'TSA_TOKEN',
            'SIGNING_METHOD',
        ];
        foreach ($expected as $name) {
            self::assertTrue(
                \defined(AuditMetadata::class.'::'.$name),
                "Constant AuditMetadata::{$name} should be defined"
            );
            $value = \constant(AuditMetadata::class.'::'.$name);
            self::assertIsString($value, "AuditMetadata::{$name} should be string");
            self::assertNotSame('', $value, "AuditMetadata::{$name} should not be empty");
        }
    }

    public function testConstantValuesAreSnakeCase(): void
    {
        self::assertSame('submitted_at', AuditMetadata::SUBMITTED_AT);
        self::assertSame('ip', AuditMetadata::IP);
        self::assertSame('user_agent', AuditMetadata::USER_AGENT);
        self::assertSame('user_id', AuditMetadata::USER_ID);
        self::assertSame('session_id', AuditMetadata::SESSION_ID);
        self::assertSame('tsa_token', AuditMetadata::TSA_TOKEN);
        self::assertSame('signing_method', AuditMetadata::SIGNING_METHOD);
    }

    public function testClassIsNotInstantiable(): void
    {
        $ref = new \ReflectionClass(AuditMetadata::class);
        self::assertTrue($ref->getConstructor()->isPrivate());
    }
}
