<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Model;

use Nowo\PdfSignableBundle\Model\AuditMetadata;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function constant;
use function defined;

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
                defined(AuditMetadata::class . '::' . $name),
                "Constant AuditMetadata::{$name} should be defined",
            );
            $value = constant(AuditMetadata::class . '::' . $name);
            self::assertIsString($value, "AuditMetadata::{$name} should be string");
            self::assertNotSame('', $value, "AuditMetadata::{$name} should not be empty");
        }
    }

    public function testConstantValuesAreSnakeCase(): void
    {
        self::assertStringContainsString('submitted_at', AuditMetadata::SUBMITTED_AT);
        self::assertStringContainsString('ip', AuditMetadata::IP);
        self::assertStringContainsString('user_agent', AuditMetadata::USER_AGENT);
        self::assertStringContainsString('user_id', AuditMetadata::USER_ID);
        self::assertStringContainsString('session_id', AuditMetadata::SESSION_ID);
        self::assertStringContainsString('tsa_token', AuditMetadata::TSA_TOKEN);
        self::assertStringContainsString('signing_method', AuditMetadata::SIGNING_METHOD);
    }

    public function testClassIsNotInstantiable(): void
    {
        $ref = new ReflectionClass(AuditMetadata::class);
        $constructor = $ref->getConstructor();
        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPrivate());
    }
}
