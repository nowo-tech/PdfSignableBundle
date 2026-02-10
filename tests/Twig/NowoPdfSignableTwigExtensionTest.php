<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Twig;

use Nowo\PdfSignableBundle\Twig\NowoPdfSignableTwigExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests for NowoPdfSignableTwigExtension (nowo_pdf_signable_include_assets).
 */
final class NowoPdfSignableTwigExtensionTest extends TestCase
{
    public function testGetFunctionsExposesIncludeAssets(): void
    {
        $requestStack = new RequestStack();
        $extension = new NowoPdfSignableTwigExtension($requestStack);
        $functions = $extension->getFunctions();
        self::assertCount(1, $functions);
        self::assertSame('nowo_pdf_signable_include_assets', $functions[0]->getName());
    }

    public function testShouldIncludeAssetsReturnsTrueWhenNoRequest(): void
    {
        $requestStack = new RequestStack();
        $extension = new NowoPdfSignableTwigExtension($requestStack);
        self::assertTrue($extension->shouldIncludeAssets());
        self::assertTrue($extension->shouldIncludeAssets(), 'Without request, always true');
    }

    public function testShouldIncludeAssetsReturnsTrueFirstTimeThenFalse(): void
    {
        $request = Request::create('/test');
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $extension = new NowoPdfSignableTwigExtension($requestStack);
        self::assertTrue($extension->shouldIncludeAssets(), 'First call should return true');
        self::assertFalse($extension->shouldIncludeAssets(), 'Second call should return false');
        self::assertFalse($extension->shouldIncludeAssets(), 'Third call should still return false');
    }

    public function testShouldIncludeAssetsSetsRequestAttribute(): void
    {
        $request = Request::create('/test');
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $extension = new NowoPdfSignableTwigExtension($requestStack);
        self::assertFalse($request->attributes->has('_nowo_pdf_signable_assets_included'));
        $extension->shouldIncludeAssets();
        self::assertTrue($request->attributes->get('_nowo_pdf_signable_assets_included'));
    }
}
