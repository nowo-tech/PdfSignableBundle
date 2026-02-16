<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Model;

use Nowo\PdfSignableBundle\Model\AcroFormPageModel;
use PHPUnit\Framework\TestCase;

/**
 * Tests for AcroFormPageModel (pdfUrl and documentKey).
 */
final class AcroFormPageModelTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $model = new AcroFormPageModel();
        self::assertNull($model->getPdfUrl());
        self::assertNull($model->getDocumentKey());
    }

    public function testSetPdfUrl(): void
    {
        $model = new AcroFormPageModel();
        $url = 'https://example.com/document.pdf';
        $model->setPdfUrl($url);
        self::assertSame($url, $model->getPdfUrl());
    }

    public function testSetDocumentKey(): void
    {
        $model = new AcroFormPageModel();
        $model->setDocumentKey('my-doc-key');
        self::assertSame('my-doc-key', $model->getDocumentKey());
    }

    public function testSetPdfUrlReturnsSelf(): void
    {
        $model = new AcroFormPageModel();
        self::assertSame($model, $model->setPdfUrl('https://a.com/b.pdf'));
    }

    public function testSetDocumentKeyReturnsSelf(): void
    {
        $model = new AcroFormPageModel();
        self::assertSame($model, $model->setDocumentKey('key'));
    }

    public function testSetPdfUrlNull(): void
    {
        $model = new AcroFormPageModel();
        $model->setPdfUrl('https://example.com/doc.pdf');
        $model->setPdfUrl(null);
        self::assertNull($model->getPdfUrl());
    }

    public function testSetDocumentKeyNull(): void
    {
        $model = new AcroFormPageModel();
        $model->setDocumentKey('key');
        $model->setDocumentKey(null);
        self::assertNull($model->getDocumentKey());
    }

    public function testSetDocumentKeyEmptyString(): void
    {
        $model = new AcroFormPageModel();
        $model->setDocumentKey('');
        self::assertSame('', $model->getDocumentKey());
    }

    public function testSetPdfUrlEmptyString(): void
    {
        $model = new AcroFormPageModel();
        $model->setPdfUrl('');
        self::assertSame('', $model->getPdfUrl());
    }
}
