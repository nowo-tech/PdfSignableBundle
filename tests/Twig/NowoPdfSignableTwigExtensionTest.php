<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Twig;

use Nowo\PdfSignableBundle\Twig\NowoPdfSignableTwigExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Tests for NowoPdfSignableTwigExtension (nowo_pdf_signable_include_assets).
 */
final class NowoPdfSignableTwigExtensionTest extends TestCase
{
    private function createExtension(RequestStack $requestStack): NowoPdfSignableTwigExtension
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new NowoPdfSignableTwigExtension(
            $requestStack,
            $translator,
        );
    }

    public function testGetFunctionsExposesIncludeAssetsAcroformStringsAndConfig(): void
    {
        $requestStack = new RequestStack();
        $extension = $this->createExtension($requestStack);
        $functions = $extension->getFunctions();
        self::assertCount(3, $functions);
        self::assertSame('nowo_pdf_signable_include_assets', $functions[0]->getName());
        self::assertSame('nowo_pdf_signable_acroform_strings', $functions[1]->getName());
        self::assertSame('nowo_pdf_signable_acroform_editor_config', $functions[2]->getName());
    }

    public function testGetAcroformStringsReturnsTranslatedKeys(): void
    {
        $requestStack = new RequestStack();
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id): string => $id);
        $extension = new NowoPdfSignableTwigExtension($requestStack, $translator);
        $strings = $extension->getAcroformStrings();
        self::assertIsArray($strings);
        self::assertArrayHasKey('modal_edit_title', $strings);
        self::assertArrayHasKey('modal_field_name', $strings);
        self::assertArrayHasKey('list_label', $strings);
        self::assertArrayHasKey('msg_draft_loaded', $strings);
    }

    public function testShouldIncludeAssetsReturnsTrueWhenNoRequest(): void
    {
        $requestStack = new RequestStack();
        $extension = $this->createExtension($requestStack);
        self::assertTrue($extension->shouldIncludeAssets());
        self::assertTrue($extension->shouldIncludeAssets(), 'Without request, always true');
    }

    public function testShouldIncludeAssetsReturnsTrueFirstTimeThenFalse(): void
    {
        $request = Request::create('/test');
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $extension = $this->createExtension($requestStack);
        self::assertTrue($extension->shouldIncludeAssets(), 'First call should return true');
        self::assertFalse($extension->shouldIncludeAssets(), 'Second call should return false');
        self::assertFalse($extension->shouldIncludeAssets(), 'Third call should still return false');
    }

    public function testShouldIncludeAssetsSetsRequestAttribute(): void
    {
        $request = Request::create('/test');
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $extension = $this->createExtension($requestStack);
        self::assertFalse($request->attributes->has('_nowo_pdf_signable_assets_included'));
        $extension->shouldIncludeAssets();
        self::assertTrue($request->attributes->get('_nowo_pdf_signable_assets_included'));
    }
}
