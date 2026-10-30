<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Integration\FormKit;

use Nowo\PdfSignableBundle\NowoPdfSignableBundle;
use Nowo\PdfSignableBundle\Tests\Integration\Routing\OptInRoutesKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;

/**
 * Real kernel with FormKit registered: the bundle profile is prepended, the form types get the FormKit merger
 * and the signature page renders translated labels (no raw keys) with the profile classes (REQ-TWIG-005 rule 11).
 */
final class FormKitPagesTest extends TestCase
{
    protected function setUp(): void
    {
        (new Filesystem())->remove(OptInRoutesKernel::cacheRoot());
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove(OptInRoutesKernel::cacheRoot());
    }

    public function testSignaturePageRendersFormKitFields(): void
    {
        $kernel = new OptInRoutesKernel(true);
        $kernel->boot();

        try {
            $profiles = $kernel->getContainer()->getParameter('nowo_form_kit.profiles');
            self::assertIsArray($profiles);
            self::assertArrayHasKey(NowoPdfSignableBundle::FORM_KIT_PROFILE, $profiles);
            self::assertSame('NowoPdfSignableBundle', $profiles[NowoPdfSignableBundle::FORM_KIT_PROFILE]['translation_domain']);

            $response = $kernel->handle(Request::create('/pdf-signable/'));
            self::assertSame(200, $response->getStatusCode());
            $html = (string) $response->getContent();

            self::assertStringContainsString('pdf-url-input form-control', $html);
            self::assertStringContainsString('unit-selector form-select', $html);
            self::assertDoesNotMatchRegularExpression('/>\s*(signature_coordinates_type|signature_box_type|acroform_editor|page)\.[a-z_.]+\s*</', $html);
            self::assertDoesNotMatchRegularExpression('/(placeholder|title|aria-label)="(signature_coordinates_type|signature_box_type|acroform_editor)\.[a-z_.]+"/', $html);
            self::assertDoesNotMatchRegularExpression('/\b[a-z_]+\.[a-z_]+\.(label|help|placeholder)\b/', strip_tags($html));
        } finally {
            $kernel->shutdown();
        }
    }
}
