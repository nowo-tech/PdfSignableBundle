<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Controller;

use Nowo\PdfSignableBundle\AcroForm\AcroFormOverrides;
use Nowo\PdfSignableBundle\AcroForm\Exception\AcroFormEditorException;
use Nowo\PdfSignableBundle\AcroForm\PdfAcroFormEditorInterface;
use Nowo\PdfSignableBundle\AcroForm\Storage\AcroFormOverridesStorageInterface;
use Nowo\PdfSignableBundle\Controller\AcroFormOverridesController;
use Nowo\PdfSignableBundle\Event\AcroFormApplyRequestEvent;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Unit tests for AcroFormOverridesController: overrides GET/POST/DELETE and apply endpoint.
 */
final class AcroFormOverridesControllerTest extends TestCase
{
    /**
     * @param array<string> $proxyUrlAllowlist When non-empty, pdf_url must match one entry (substring or regex #...)
     */
    private function createController(
        bool $enabled = true,
        ?AcroFormOverridesStorageInterface $storage = null,
        bool $allowPdfModify = false,
        ?PdfAcroFormEditorInterface $editor = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        array $proxyUrlAllowlist = [],
        int $maxPatches = 500,
        ?string $fieldsExtractorScript = null,
        ?string $processScript = null,
        string $processScriptCommand = 'python3',
        bool $debug = false,
        ?LoggerInterface $logger = null,
    ): AcroFormOverridesController {
        $storage ??= $this->createMock(AcroFormOverridesStorageInterface::class);
        /** @var EventDispatcherInterface&\PHPUnit\Framework\MockObject\MockObject $dispatcher */
        $dispatcher = $eventDispatcher ?? $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(static function (object $event): object {
            return $event;
        });
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new AcroFormOverridesController(
            $enabled,
            $storage,
            $allowPdfModify,
            $editor,
            $dispatcher,
            $translator,
            $proxyUrlAllowlist,
            20 * 1024 * 1024,
            $maxPatches,
            $fieldsExtractorScript,
            $processScript,
            $processScriptCommand,
            $debug,
            $logger,
        );
    }

    public function testGetOverridesWhenDisabledReturns404(): void
    {
        $controller = $this->createController(enabled: false);
        $request = Request::create('/pdf-signable/acroform/overrides', 'GET', ['document_key' => 'doc1']);

        $response = $controller->getOverrides($request);

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testGetOverridesMissingDocumentKeyReturns400(): void
    {
        $controller = $this->createController();
        $request = Request::create('/pdf-signable/acroform/overrides', 'GET');

        $response = $controller->getOverrides($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('document_key required', $data['error']);
    }

    public function testGetOverridesNotFoundReturns404(): void
    {
        $storage = $this->createMock(AcroFormOverridesStorageInterface::class);
        $storage->method('get')->with('doc1')->willReturn(null);
        $controller = $this->createController(storage: $storage);
        $request = Request::create('/pdf-signable/acroform/overrides', 'GET', ['document_key' => 'doc1']);

        $response = $controller->getOverrides($request);

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Not found', $data['error']);
    }

    public function testGetOverridesReturns200WithOverrides(): void
    {
        $overrides = new AcroFormOverrides(['f1' => ['defaultValue' => 'x']], 'doc1');
        $storage = $this->createMock(AcroFormOverridesStorageInterface::class);
        $storage->method('get')->with('doc1')->willReturn($overrides);
        $controller = $this->createController(storage: $storage);
        $request = Request::create('/pdf-signable/acroform/overrides', 'GET', ['document_key' => 'doc1']);

        $response = $controller->getOverrides($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('overrides', $data);
        self::assertSame(['f1' => ['defaultValue' => 'x']], $data['overrides']);
        self::assertSame('doc1', $data['document_key']);
    }

    public function testSaveOverridesWhenDisabledReturns404(): void
    {
        $controller = $this->createController(enabled: false);
        $request = Request::create('/pdf-signable/acroform/overrides', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['document_key' => 'doc1', 'overrides' => []], JSON_THROW_ON_ERROR));

        $response = $controller->saveOverrides($request);

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testSaveOverridesInvalidDocumentKeyReturns400(): void
    {
        $controller = $this->createController();
        $request = Request::create('/pdf-signable/acroform/overrides', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['document_key' => 'invalid key with spaces', 'overrides' => []], JSON_THROW_ON_ERROR));

        $response = $controller->saveOverrides($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertStringContainsString('document_key', $data['error']);
    }

    public function testSaveOverridesValidCallsStorageAndReturns200(): void
    {
        $storage = $this->createMock(AcroFormOverridesStorageInterface::class);
        $storage->expects(self::once())->method('set')->with('doc1', self::callback(function (AcroFormOverrides $o): bool {
            return 'doc1' === $o->documentKey && $o->overrides === ['f1' => ['label' => 'Field 1']];
        }));
        $controller = $this->createController(storage: $storage);
        $request = Request::create('/pdf-signable/acroform/overrides', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'document_key' => 'doc1',
            'overrides' => ['f1' => ['label' => 'Field 1']],
        ], JSON_THROW_ON_ERROR));

        $response = $controller->saveOverrides($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('doc1', $data['document_key']);
        self::assertSame(['f1' => ['label' => 'Field 1']], $data['overrides']);
    }

    public function testRemoveOverridesWhenDisabledReturns404(): void
    {
        $controller = $this->createController(enabled: false);
        $request = Request::create('/pdf-signable/acroform/overrides', 'DELETE', ['document_key' => 'doc1']);

        $response = $controller->removeOverrides($request);

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testRemoveOverridesMissingDocumentKeyReturns400(): void
    {
        $controller = $this->createController();
        $request = Request::create('/pdf-signable/acroform/overrides', 'DELETE');

        $response = $controller->removeOverrides($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testRemoveOverridesValidCallsStorageAndReturns204(): void
    {
        $storage = $this->createMock(AcroFormOverridesStorageInterface::class);
        $storage->expects(self::once())->method('remove')->with('doc1');
        $controller = $this->createController(storage: $storage);
        $request = Request::create('/pdf-signable/acroform/overrides', 'DELETE', ['document_key' => 'doc1']);

        $response = $controller->removeOverrides($request);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        self::assertSame('', $response->getContent());
    }

    /** removeOverrides can resolve document_key from request bag. */
    public function testRemoveOverridesWithDocumentKeyInRequestBag(): void
    {
        $storage = $this->createMock(AcroFormOverridesStorageInterface::class);
        $storage->expects(self::once())->method('remove')->with('key-from-request');
        $controller = $this->createController(storage: $storage);
        $request = Request::create('/pdf-signable/acroform/overrides', 'DELETE');
        $request->request->replace(['document_key' => 'key-from-request']);

        $response = $controller->removeOverrides($request);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testApplyWhenDisabledReturns404(): void
    {
        $controller = $this->createController(enabled: true, allowPdfModify: false);
        $request = Request::create('/pdf-signable/acroform/apply', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['pdf_content' => base64_encode('%PDF-1.4'), 'patches' => []], JSON_THROW_ON_ERROR));

        $response = $controller->apply($request);

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testApplyMissingPdfReturns400(): void
    {
        $controller = $this->createController(allowPdfModify: true);
        $request = Request::create('/pdf-signable/acroform/apply', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['patches' => []], JSON_THROW_ON_ERROR));

        $response = $controller->apply($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertStringContainsString('pdf_url', $data['error']);
    }

    public function testApplyTooManyPatchesReturns400(): void
    {
        $controller = $this->createController(allowPdfModify: true, maxPatches: 2);
        $request = Request::create('/pdf-signable/acroform/apply', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'pdf_content' => base64_encode('%PDF-1.4'),
            'patches' => [
                ['fieldId' => 'f1'],
                ['fieldId' => 'f2'],
                ['fieldId' => 'f3'],
            ],
        ], JSON_THROW_ON_ERROR));

        $response = $controller->apply($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Too many patches', $data['error']);
    }

    public function testApplyPatchesNotArrayReturns400(): void
    {
        $controller = $this->createController(allowPdfModify: true);
        $request = Request::create('/pdf-signable/acroform/apply', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['pdf_content' => base64_encode('%PDF-1.4'), 'patches' => 'not-array'], JSON_THROW_ON_ERROR));

        $response = $controller->apply($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertStringContainsString('patches', $data['error']);
    }

    public function testApplyInvalidBase64PdfContentReturns400(): void
    {
        $controller = $this->createController(allowPdfModify: true);
        $request = Request::create('/pdf-signable/acroform/apply', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['pdf_content' => '!!!invalid-base64!!!', 'patches' => []], JSON_THROW_ON_ERROR));

        $response = $controller->apply($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertStringContainsString('base64', $data['error']);
    }

    public function testApplyInvalidPatchMissingFieldIdReturns400(): void
    {
        $controller = $this->createController(allowPdfModify: true);
        $request = Request::create('/pdf-signable/acroform/apply', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'pdf_content' => base64_encode('%PDF-1.4'),
            'patches' => [['defaultValue' => 'x']],
        ], JSON_THROW_ON_ERROR));

        $response = $controller->apply($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertStringContainsString('fieldId', $data['error']);
    }

    public function testApplyEventSetsModifiedPdfReturns200(): void
    {
        $modifiedPdf = '%PDF-1.4 modified';
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(function (object $event) use ($modifiedPdf): object {
            if ($event instanceof AcroFormApplyRequestEvent) {
                $event->setModifiedPdf($modifiedPdf);
            }

            return $event;
        });
        $controller = $this->createController(allowPdfModify: true, eventDispatcher: $dispatcher);
        $request = Request::create('/pdf-signable/acroform/apply', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'pdf_content' => base64_encode('%PDF-1.4'),
            'patches' => [['fieldId' => 'f1', 'defaultValue' => 'test']],
        ], JSON_THROW_ON_ERROR));

        $response = $controller->apply($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame($modifiedPdf, $response->getContent());
        self::assertSame('application/pdf', $response->headers->get('Content-Type'));
        self::assertStringContainsString('document.pdf', $response->headers->get('Content-Disposition') ?? '');
    }

    public function testApplyEventSetsErrorReturns400(): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(function (object $event): object {
            if ($event instanceof AcroFormApplyRequestEvent) {
                $event->setError(new \RuntimeException('PDF has no form'));
            }

            return $event;
        });
        $controller = $this->createController(allowPdfModify: true, eventDispatcher: $dispatcher);
        $request = Request::create('/pdf-signable/acroform/apply', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'pdf_content' => base64_encode('%PDF-1.4'),
            'patches' => [['fieldId' => 'f1']],
        ], JSON_THROW_ON_ERROR));

        $response = $controller->apply($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('PDF has no form', $data['error']);
    }

    public function testApplyNoEditorNoEventResponseReturns501(): void
    {
        $controller = $this->createController(allowPdfModify: true, editor: null);
        $request = Request::create('/pdf-signable/acroform/apply', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'pdf_content' => base64_encode('%PDF-1.4'),
            'patches' => [['fieldId' => 'f1']],
        ], JSON_THROW_ON_ERROR));

        $response = $controller->apply($request);

        self::assertSame(Response::HTTP_NOT_IMPLEMENTED, $response->getStatusCode());
        self::assertStringContainsString('No editor', $response->getContent());
    }

    public function testApplyNoEditorNoEventResponseWithDebugLogsWarning(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(
                self::stringContains('AcroForm apply response: 501'),
                self::anything()
            );
        $controller = $this->createController(allowPdfModify: true, editor: null, debug: true, logger: $logger);
        $request = Request::create('/pdf-signable/acroform/apply', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'pdf_content' => base64_encode('%PDF-1.4'),
            'patches' => [['fieldId' => 'f1']],
        ], JSON_THROW_ON_ERROR));

        $response = $controller->apply($request);

        self::assertSame(Response::HTTP_NOT_IMPLEMENTED, $response->getStatusCode());
    }

    public function testApplyReturnsJsonWhenEventHasValidationResult(): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(function (object $event): object {
            if ($event instanceof AcroFormApplyRequestEvent) {
                $event->setValidationResult(['success' => true, 'patches_count' => 1, 'message' => 'Dry-run OK']);
            }

            return $event;
        });
        $controller = $this->createController(allowPdfModify: true, eventDispatcher: $dispatcher);
        $request = Request::create('/pdf-signable/acroform/apply', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'pdf_content' => base64_encode('%PDF-1.4'),
            'patches' => [['fieldId' => 'f1']],
        ], JSON_THROW_ON_ERROR));

        $response = $controller->apply($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('application/json', $response->headers->get('Content-Type'));
        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($data['success']);
        self::assertSame(1, $data['patches_count']);
    }

    public function testApplyWithEditorReturns200WithModifiedPdf(): void
    {
        $editor = $this->createMock(PdfAcroFormEditorInterface::class);
        $editor->method('applyPatches')->with(self::stringContains('%PDF'), self::isType('array'))
            ->willReturn('%PDF-1.4 edited');
        $controller = $this->createController(allowPdfModify: true, editor: $editor);
        $request = Request::create('/pdf-signable/acroform/apply', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'pdf_content' => base64_encode('%PDF-1.4'),
            'patches' => [['fieldId' => 'f1', 'defaultValue' => 'v']],
        ], JSON_THROW_ON_ERROR));

        $response = $controller->apply($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('%PDF-1.4 edited', $response->getContent());
        self::assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function testApplyWithEditorAndValidateOnlyReturnsJson(): void
    {
        $editor = $this->createMock(PdfAcroFormEditorInterface::class);
        $editor->method('applyPatches')->willReturn('%PDF-1.4');
        $controller = $this->createController(allowPdfModify: true, editor: $editor, debug: true);
        $request = Request::create('/pdf-signable/acroform/apply', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'pdf_content' => base64_encode('%PDF-1.4'),
            'patches' => [['fieldId' => 'f1']],
            'validate_only' => true,
        ], JSON_THROW_ON_ERROR));

        $response = $controller->apply($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($data['success']);
        self::assertSame(1, $data['patches_count']);
    }

    public function testApplyWithEditorValidateOnlyAndExceptionReturnsJsonError(): void
    {
        $editor = $this->createMock(PdfAcroFormEditorInterface::class);
        $editor->method('applyPatches')->willThrowException(new AcroFormEditorException('Invalid PDF'));
        $controller = $this->createController(allowPdfModify: true, editor: $editor, debug: true);
        $request = Request::create('/pdf-signable/acroform/apply', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'pdf_content' => base64_encode('%PDF-1.4'),
            'patches' => [['fieldId' => 'f1']],
            'validate_only' => true,
        ], JSON_THROW_ON_ERROR));

        $response = $controller->apply($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertFalse($data['success']);
        self::assertSame('Invalid PDF', $data['error']);
    }

    public function testSaveOverridesWithFieldsInBody(): void
    {
        $storage = $this->createMock(AcroFormOverridesStorageInterface::class);
        $storage->expects(self::once())->method('set')->with('doc1', self::callback(function (AcroFormOverrides $o): bool {
            return 'doc1' === $o->documentKey
                && $o->overrides === ['f1' => ['label' => 'x']]
                && $o->fields === [['id' => 'f1', 'rect' => [0, 0, 100, 20]]];
        }));
        $controller = $this->createController(storage: $storage);
        $request = Request::create('/pdf-signable/acroform/overrides', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'document_key' => 'doc1',
            'overrides' => ['f1' => ['label' => 'x']],
            'fields' => [['id' => 'f1', 'rect' => [0, 0, 100, 20]]],
        ], JSON_THROW_ON_ERROR));

        $response = $controller->saveOverrides($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('fields', $data);
    }

    public function testSaveOverridesWithNonArrayOverridesTreatedAsEmpty(): void
    {
        $storage = $this->createMock(AcroFormOverridesStorageInterface::class);
        $storage->expects(self::once())->method('set')->with('doc1', self::callback(function (AcroFormOverrides $o): bool {
            return [] === $o->overrides;
        }));
        $controller = $this->createController(storage: $storage);
        $request = Request::create('/pdf-signable/acroform/overrides', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['document_key' => 'doc1', 'overrides' => 'invalid'], JSON_THROW_ON_ERROR));

        $response = $controller->saveOverrides($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testSaveOverridesDocumentKeyFromQuery(): void
    {
        $storage = $this->createMock(AcroFormOverridesStorageInterface::class);
        $storage->expects(self::once())->method('set')->with('from-query', self::anything());
        $controller = $this->createController(storage: $storage);
        $request = Request::create('/pdf-signable/acroform/overrides?document_key=from-query', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['overrides' => []], JSON_THROW_ON_ERROR));

        $response = $controller->saveOverrides($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    /** When fields is present but not an array, it is ignored (stored as null). */
    public function testSaveOverridesWithFieldsNotArrayTreatedAsNull(): void
    {
        $storage = $this->createMock(AcroFormOverridesStorageInterface::class);
        $storage->expects(self::once())->method('set')->with('doc1', self::callback(function (AcroFormOverrides $o): bool {
            return $o->documentKey === 'doc1' && $o->overrides === [] && $o->fields === null;
        }));
        $controller = $this->createController(storage: $storage);
        $request = Request::create('/pdf-signable/acroform/overrides', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['document_key' => 'doc1', 'overrides' => [], 'fields' => 'invalid'], JSON_THROW_ON_ERROR));

        $response = $controller->saveOverrides($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testApplyEditorThrowsAcroFormEditorExceptionReturns400(): void
    {
        $editor = $this->createMock(PdfAcroFormEditorInterface::class);
        $editor->method('applyPatches')->willThrowException(new AcroFormEditorException('PDF has no form fields'));
        $controller = $this->createController(allowPdfModify: true, editor: $editor);
        $request = Request::create('/pdf-signable/acroform/apply', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'pdf_content' => base64_encode('%PDF-1.4'),
            'patches' => [['fieldId' => 'f1']],
        ], JSON_THROW_ON_ERROR));

        $response = $controller->apply($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('PDF has no form fields', $data['error']);
    }

    public function testLoadOverridesWhenDisabledReturns404(): void
    {
        $controller = $this->createController(enabled: false);
        $request = Request::create('/pdf-signable/acroform/overrides/load', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['document_key' => 'doc1'], JSON_THROW_ON_ERROR));

        $response = $controller->loadOverrides($request);

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testLoadOverridesMissingDocumentKeyReturns400(): void
    {
        $controller = $this->createController();
        $request = Request::create('/pdf-signable/acroform/overrides/load', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([], JSON_THROW_ON_ERROR));

        $response = $controller->loadOverrides($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('document_key required in body', $data['error']);
    }

    public function testLoadOverridesValidReturnsOverridesAndDocumentKey(): void
    {
        $overrides = new AcroFormOverrides(['f1' => ['label' => 'Field 1']], 'doc1');
        $storage = $this->createMock(AcroFormOverridesStorageInterface::class);
        $storage->method('get')->with('doc1')->willReturn($overrides);
        $controller = $this->createController(storage: $storage);
        $request = Request::create('/pdf-signable/acroform/overrides/load', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['document_key' => 'doc1'], JSON_THROW_ON_ERROR));

        $response = $controller->loadOverrides($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('doc1', $data['document_key']);
        self::assertSame(['f1' => ['label' => 'Field 1']], $data['overrides']);
    }

    public function testLoadOverridesWhenStorageReturnsNullReturnsEmptyOverrides(): void
    {
        $storage = $this->createMock(AcroFormOverridesStorageInterface::class);
        $storage->method('get')->with('doc1')->willReturn(null);
        $controller = $this->createController(storage: $storage);
        $request = Request::create('/pdf-signable/acroform/overrides/load', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['document_key' => 'doc1'], JSON_THROW_ON_ERROR));

        $response = $controller->loadOverrides($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('doc1', $data['document_key']);
        self::assertSame([], $data['overrides']);
    }

    public function testLoadOverridesInvalidDocumentKeyReturns400(): void
    {
        $controller = $this->createController();
        $request = Request::create('/pdf-signable/acroform/overrides/load', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['document_key' => 'x'.str_repeat('a', 300)], JSON_THROW_ON_ERROR));

        $response = $controller->loadOverrides($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertStringContainsString('document_key', $data['error']);
    }

    public function testLoadOverridesDocumentKeyNotStringTreatedAsEmpty(): void
    {
        $controller = $this->createController();
        $request = Request::create('/pdf-signable/acroform/overrides/load', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['document_key' => 123], JSON_THROW_ON_ERROR));

        $response = $controller->loadOverrides($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('document_key required in body', $data['error']);
    }

    public function testLoadOverridesWithFieldsInBodyReturnsMergedOverrides(): void
    {
        $overrides = new AcroFormOverrides(['f1' => ['defaultValue' => 'x']], 'doc1');
        $storage = $this->createMock(AcroFormOverridesStorageInterface::class);
        $storage->method('get')->with('doc1')->willReturn($overrides);
        $controller = $this->createController(storage: $storage);
        $fields = [
            ['id' => 'f1', 'rect' => [0, 0, 100, 20], 'fieldType' => 'text'],
            ['id' => 'f2', 'rect' => [0, 30, 80, 50], 'fieldType' => 'text'],
        ];
        $request = Request::create('/pdf-signable/acroform/overrides/load', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['document_key' => 'doc1', 'fields' => $fields], JSON_THROW_ON_ERROR));

        $response = $controller->loadOverrides($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('fields', $data);
        self::assertCount(2, $data['fields']);
        self::assertArrayHasKey('overrides', $data);
        self::assertArrayHasKey('f1', $data['overrides']);
        self::assertArrayHasKey('f2', $data['overrides']);
        self::assertSame('x', $data['overrides']['f1']['defaultValue'] ?? null);
    }

    public function testExtractFieldsWhenDisabledReturns404(): void
    {
        $controller = $this->createController(enabled: false);
        $request = Request::create('/pdf-signable/acroform/fields/extract', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['pdf_content' => base64_encode('%PDF-1.4')], JSON_THROW_ON_ERROR));

        $response = $controller->extractFields($request);

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testExtractFieldsMissingPdfReturns400(): void
    {
        $existingFile = __DIR__.'/../../composer.json';
        self::assertFileExists($existingFile, 'composer.json must exist for this test');
        $controller = $this->createController(fieldsExtractorScript: $existingFile);
        $request = Request::create('/pdf-signable/acroform/fields/extract', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([], JSON_THROW_ON_ERROR));

        $response = $controller->extractFields($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Provide pdf_url or pdf_content', $data['error']);
    }

    public function testProcessWhenDisabledReturns404(): void
    {
        $controller = $this->createController(enabled: false);
        $request = Request::create('/pdf-signable/acroform/process', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['pdf_content' => base64_encode('%PDF-1.4')], JSON_THROW_ON_ERROR));

        $response = $controller->process($request);

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testProcessWhenProcessScriptNullReturns404(): void
    {
        $controller = $this->createController();
        $request = Request::create('/pdf-signable/acroform/process', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['pdf_content' => base64_encode('%PDF-1.4')], JSON_THROW_ON_ERROR));

        $response = $controller->process($request);

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testProcessWhenProcessScriptEmptyAfterTrimReturns404(): void
    {
        $controller = $this->createController(processScript: '   ');
        $request = Request::create('/pdf-signable/acroform/process', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['pdf_content' => base64_encode('%PDF-1.4')], JSON_THROW_ON_ERROR));

        $response = $controller->process($request);

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testProcessWhenProcessScriptNotAFileReturns503(): void
    {
        $controller = $this->createController(processScript: '/nonexistent/process.py');
        $request = Request::create('/pdf-signable/acroform/process', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['pdf_content' => base64_encode('%PDF-1.4')], JSON_THROW_ON_ERROR));

        $response = $controller->process($request);

        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertStringContainsString('not configured', $data['error']);
    }

    public function testProcessMissingPdfContentReturns400(): void
    {
        $existingFile = __DIR__.'/../../composer.json';
        self::assertFileExists($existingFile);
        $controller = $this->createController(processScript: $existingFile);
        $request = Request::create('/pdf-signable/acroform/process', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['pdf_content' => 123], JSON_THROW_ON_ERROR));

        $response = $controller->process($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertStringContainsString('pdf_content', $data['error']);
    }

    public function testProcessInvalidBase64Returns400(): void
    {
        $existingFile = __DIR__.'/../../composer.json';
        self::assertFileExists($existingFile);
        $controller = $this->createController(processScript: $existingFile);
        $request = Request::create('/pdf-signable/acroform/process', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['pdf_content' => '!!!invalid!!!'], JSON_THROW_ON_ERROR));

        $response = $controller->process($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertStringContainsString('Invalid', $data['error']);
    }

    /** When process script runs but exits non-zero, controller returns 400. */
    public function testProcessWhenScriptExitsNonZeroReturns400(): void
    {
        $script = sys_get_temp_dir() . '/pdfsignable_process_exit1_' . getmypid() . '.py';
        file_put_contents($script, "import sys\nsys.exit(1)\n");
        try {
            $controller = $this->createController(processScript: $script);
            $request = Request::create('/pdf-signable/acroform/process', 'POST', [], [], [], [
                'CONTENT_TYPE' => 'application/json',
            ], json_encode(['pdf_content' => base64_encode('%PDF-1.4')], JSON_THROW_ON_ERROR));

            $response = $controller->process($request);

            self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
            $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
            self::assertStringContainsString('Process script failed', $data['error']);
        } finally {
            @unlink($script);
        }
    }

    /** When process script command is not in PATH, response includes Python install hint. */
    public function testProcessWhenProcessCommandNotFoundReturns400WithPythonMessage(): void
    {
        $script = sys_get_temp_dir() . '/pdfsignable_process_dummy_' . getmypid() . '.py';
        file_put_contents($script, "import sys\nsys.exit(0)\n");
        try {
            $controller = $this->createController(processScript: $script, processScriptCommand: 'python999nonexistent');
            $request = Request::create('/pdf-signable/acroform/process', 'POST', [], [], [], [
                'CONTENT_TYPE' => 'application/json',
            ], json_encode(['pdf_content' => base64_encode('%PDF-1.4')], JSON_THROW_ON_ERROR));

            $response = $controller->process($request);

            self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
            $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
            self::assertStringContainsString('Process script failed', $data['error']);
            self::assertStringContainsString('Python', $data['error']);
        } finally {
            @unlink($script);
        }
    }

    /** When process script does not write output file, controller returns 400. */
    public function testProcessWhenScriptProducesNoOutputFileReturns400(): void
    {
        $script = sys_get_temp_dir() . '/pdfsignable_process_noout_' . getmypid() . '.py';
        file_put_contents($script, "import argparse\nparser = argparse.ArgumentParser()\nparser.add_argument('--input')\nparser.add_argument('--output')\nparser.parse_args()\n# do not write to output\nimport sys\nsys.exit(0)\n");
        try {
            $controller = $this->createController(processScript: $script);
            $request = Request::create('/pdf-signable/acroform/process', 'POST', [], [], [], [
                'CONTENT_TYPE' => 'application/json',
            ], json_encode(['pdf_content' => base64_encode('%PDF-1.4')], JSON_THROW_ON_ERROR));

            $response = $controller->process($request);

            self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
            $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
            self::assertStringContainsString('no output file', $data['error']);
        } finally {
            @unlink($script);
        }
    }

    /** When Accept header contains application/pdf, process returns 200 with PDF body. */
    public function testProcessWhenAcceptPdfReturns200WithPdfContent(): void
    {
        $script = sys_get_temp_dir() . '/pdfsignable_process_copy_' . getmypid() . '.py';
        file_put_contents($script, <<<'PY'
import argparse
parser = argparse.ArgumentParser()
parser.add_argument('--input')
parser.add_argument('--output')
args = parser.parse_args()
with open(args.input, 'rb') as f:
    data = f.read()
with open(args.output, 'wb') as f:
    f.write(data)
PY
        );
        try {
            $controller = $this->createController(processScript: $script);
            $pdfB64 = base64_encode('%PDF-1.4 minimal');
            $request = Request::create('/pdf-signable/acroform/process', 'POST', [], [], [], [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/pdf',
            ], json_encode(['pdf_content' => $pdfB64], JSON_THROW_ON_ERROR));

            $response = $controller->process($request);

            self::assertSame(Response::HTTP_OK, $response->getStatusCode());
            self::assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
            self::assertSame('%PDF-1.4 minimal', $response->getContent());
        } finally {
            @unlink($script);
        }
    }

    public function testApplyPdfUrlEmptyReturns400(): void
    {
        $controller = $this->createController(allowPdfModify: true);
        $request = Request::create('/pdf-signable/acroform/apply', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['pdf_url' => '', 'patches' => []], JSON_THROW_ON_ERROR));

        $response = $controller->apply($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('error', $data);
    }

    public function testApplyPdfUrlInvalidUrlReturns400(): void
    {
        $controller = $this->createController(allowPdfModify: true);
        $request = Request::create('/pdf-signable/acroform/apply', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['pdf_url' => 'not-a-url', 'patches' => []], JSON_THROW_ON_ERROR));

        $response = $controller->apply($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('error', $data);
    }

    public function testApplyPdfUrlNotInAllowlistReturns403(): void
    {
        $controller = $this->createController(
            allowPdfModify: true,
            proxyUrlAllowlist: ['https://example.com'],
        );
        $request = Request::create('/pdf-signable/acroform/apply', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'pdf_url' => 'https://other-site.com/doc.pdf',
            'patches' => [],
        ], JSON_THROW_ON_ERROR));

        $response = $controller->apply($request);

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertStringContainsString('not allowed', $data['error'] ?? '');
    }

    /** When pdf_url matches allowlist substring, controller does not return 403 (allowlist check passes). */
    public function testApplyPdfUrlAllowedByAllowlistSubstringNotForbidden(): void
    {
        $controller = $this->createController(
            allowPdfModify: true,
            proxyUrlAllowlist: ['example.com'],
        );
        $request = Request::create('/pdf-signable/acroform/apply', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'pdf_url' => 'https://example.com/doc.pdf',
            'patches' => [],
        ], JSON_THROW_ON_ERROR));

        $response = $controller->apply($request);

        self::assertNotSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testApplyPdfUrlLocalhostBlockedBySsrfReturns403(): void
    {
        $controller = $this->createController(allowPdfModify: true);
        $request = Request::create('/pdf-signable/acroform/apply', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'pdf_url' => 'http://localhost/doc.pdf',
            'patches' => [],
        ], JSON_THROW_ON_ERROR));

        $response = $controller->apply($request);

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('error', $data);
    }

    public function testApplyEventSetsErrorDetailInPayload(): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(function (object $event): object {
            if ($event instanceof AcroFormApplyRequestEvent) {
                $event->setError(new \RuntimeException('Apply failed'));
                $event->setErrorDetail('stderr output here');
            }

            return $event;
        });
        $controller = $this->createController(allowPdfModify: true, eventDispatcher: $dispatcher);
        $request = Request::create('/pdf-signable/acroform/apply', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'pdf_content' => base64_encode('%PDF-1.4'),
            'patches' => [['fieldId' => 'f1']],
        ], JSON_THROW_ON_ERROR));

        $response = $controller->apply($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Apply failed', $data['error']);
        self::assertSame('stderr output here', $data['detail'] ?? null);
    }

    public function testApplyPdfTooLargeReturns400(): void
    {
        $controller = $this->createController(allowPdfModify: true);
        $largeContent = str_repeat('x', 21 * 1024 * 1024);
        $request = Request::create('/pdf-signable/acroform/apply', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'pdf_content' => base64_encode($largeContent),
            'patches' => [],
        ], JSON_THROW_ON_ERROR));

        $response = $controller->apply($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertStringContainsString('PDF too large', $data['error'] ?? '');
    }

    public function testExtractFieldsScriptNotFoundReturns503(): void
    {
        $controller = $this->createController(fieldsExtractorScript: '/nonexistent/extract.py');
        $request = Request::create('/pdf-signable/acroform/fields/extract', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['pdf_content' => base64_encode('%PDF-1.4')], JSON_THROW_ON_ERROR));

        $response = $controller->extractFields($request);

        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertStringContainsString('not found', $data['error']);
    }

    public function testExtractFieldsPdfTooLargeReturns400(): void
    {
        $existingFile = __DIR__ . '/../../composer.json';
        self::assertFileExists($existingFile);
        $controller = $this->createController(fieldsExtractorScript: $existingFile);
        $largeContent = str_repeat('x', 21 * 1024 * 1024);
        $request = Request::create('/pdf-signable/acroform/fields/extract', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['pdf_content' => base64_encode($largeContent)], JSON_THROW_ON_ERROR));

        $response = $controller->extractFields($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('PDF too large', $data['error']);
    }

    /** When extractor script exits non-zero, controller returns 500. */
    public function testExtractFieldsWhenExtractorScriptExitsNonZeroReturns500(): void
    {
        $script = sys_get_temp_dir() . '/pdfsignable_extract_exit1_' . getmypid() . '.py';
        file_put_contents($script, "import sys\nsys.exit(1)\n");
        try {
            $controller = $this->createController(fieldsExtractorScript: $script);
            $request = Request::create('/pdf-signable/acroform/fields/extract', 'POST', [], [], [], [
                'CONTENT_TYPE' => 'application/json',
            ], json_encode(['pdf_content' => base64_encode('%PDF-1.4')], JSON_THROW_ON_ERROR));

            $response = $controller->extractFields($request);

            self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
            $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
            self::assertStringContainsString('Fields extractor failed', $data['error']);
        } finally {
            @unlink($script);
        }
    }

    /** When extractor script output is not a JSON array (e.g. null), controller returns 500. */
    public function testExtractFieldsWhenScriptOutputNotArrayReturns500(): void
    {
        $script = sys_get_temp_dir() . '/pdfsignable_extract_invalid_' . getmypid() . '.py';
        file_put_contents($script, "print('null')\n");
        try {
            $controller = $this->createController(fieldsExtractorScript: $script);
            $request = Request::create('/pdf-signable/acroform/fields/extract', 'POST', [], [], [], [
                'CONTENT_TYPE' => 'application/json',
            ], json_encode(['pdf_content' => base64_encode('%PDF-1.4')], JSON_THROW_ON_ERROR));

            $response = $controller->extractFields($request);

            self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
            $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
            self::assertStringContainsString('Invalid extractor output', $data['error']);
        } finally {
            @unlink($script);
        }
    }

    public function testGetOverridesWithDocumentKeyInQuery(): void
    {
        $overrides = new AcroFormOverrides([], 'my-doc');
        $storage = $this->createMock(AcroFormOverridesStorageInterface::class);
        $storage->method('get')->with('my-doc')->willReturn($overrides);
        $controller = $this->createController(storage: $storage);
        $request = Request::create('/acroform/overrides', 'GET', ['document_key' => 'my-doc']);

        $response = $controller->getOverrides($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('my-doc', $data['document_key']);
    }

    /** document_key can be read from request bag (e.g. when sent in body for GET). */
    public function testGetOverridesWithDocumentKeyInRequestBag(): void
    {
        $overrides = new AcroFormOverrides(['f1' => []], 'from-request');
        $storage = $this->createMock(AcroFormOverridesStorageInterface::class);
        $storage->method('get')->with('from-request')->willReturn($overrides);
        $controller = $this->createController(storage: $storage);
        $request = Request::create('/acroform/overrides', 'GET');
        $request->request->replace(['document_key' => 'from-request']);

        $response = $controller->getOverrides($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('from-request', $data['document_key']);
    }

    public function testSaveOverridesMissingDocumentKeyReturns400(): void
    {
        $controller = $this->createController();
        $request = Request::create('/acroform/overrides', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['overrides' => []], JSON_THROW_ON_ERROR));

        $response = $controller->saveOverrides($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertStringContainsString('document_key', $data['error']);
    }
}
