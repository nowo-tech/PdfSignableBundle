<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Event;

use LogicException;
use Nowo\PdfSignableBundle\Event\AcroFormModifiedPdfProcessedEvent;
use Nowo\PdfSignableBundle\Event\BatchSignRequestedEvent;
use Nowo\PdfSignableBundle\Event\PdfProxyRequestEvent;
use Nowo\PdfSignableBundle\Event\PdfProxyResponseEvent;
use Nowo\PdfSignableBundle\Event\PdfSignRequestEvent;
use Nowo\PdfSignableBundle\Event\SignatureCoordinatesSubmittedEvent;
use Nowo\PdfSignableBundle\Model\SignatureCoordinatesModel;
use Nowo\PdfSignableBundle\Submission\SignatureSubmission;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 3.10 (REQ-ARCH-001 rule 3): every event has a framework-free accessor that gives the same data as the deprecated
 * HttpFoundation / model accessor, and the response alternatives behave like setResponse().
 */
#[CoversClass(SignatureCoordinatesSubmittedEvent::class)]
#[CoversClass(BatchSignRequestedEvent::class)]
#[CoversClass(PdfSignRequestEvent::class)]
#[CoversClass(PdfProxyRequestEvent::class)]
#[CoversClass(PdfProxyResponseEvent::class)]
#[CoversClass(AcroFormModifiedPdfProcessedEvent::class)]
final class FrameworkFreeAccessorsTest extends TestCase
{
    public function testSubmissionEventsGiveTheSameDataAsTheModelAndRequest(): void
    {
        $model   = $this->model();
        $request = $this->request();

        foreach ([new SignatureCoordinatesSubmittedEvent($model, $request), new BatchSignRequestedEvent($model, $request, [0])] as $event) {
            self::assertSame($event->getCoordinates()->toArray(), $event->getSubmission()->toArray());
            $context = $event->getRequestContext();
            self::assertSame($event->getRequest()->getMethod(), $context->method);
            self::assertSame($event->getRequest()->getPathInfo(), $context->path);
            self::assertSame($event->getRequest()->getClientIp(), $context->getClientIp());
            self::assertSame($event->getRequest()->headers->get('User-Agent'), $context->getUserAgent());
            self::assertSame($event->getRequest()->getLocale(), $context->locale);
        }
    }

    public function testSubmissionReflectsChangesByEarlierListeners(): void
    {
        $model = $this->model();
        $event = new SignatureCoordinatesSubmittedEvent($model, $this->request());

        $model->getSignatureBoxes()[0]->setSignatureData('data:image/png;base64,AAAA');

        self::assertSame('data:image/png;base64,AAAA', $event->getSubmission()->boxes[0]->getSignatureData());
    }

    public function testSignRequestFromModelAndRequestIsUnchanged(): void
    {
        $model   = $this->model();
        $request = $this->request();
        $event   = new PdfSignRequestEvent($model, $request, ['signing_profile' => 'qes']);

        self::assertSame($model, $event->getCoordinates());
        self::assertSame($request, $event->getRequest());
        self::assertTrue($event->hasRequest());
        self::assertSame(['signing_profile' => 'qes'], $event->getOptions());
        self::assertSame($model->toArray(), $event->getSubmission()->toArray());
        self::assertSame('203.0.113.5', $event->getRequestContext()?->getClientIp());

        $response = new Response('redirect');
        $event->setResponse($response);
        self::assertSame($response, $event->getResponse());
    }

    public function testSignRequestFromSubmissionWithoutRequest(): void
    {
        $submission = SignatureSubmission::fromModel($this->model());
        $event      = new PdfSignRequestEvent($submission, options: ['box_indices' => [0]]);

        self::assertFalse($event->hasRequest());
        self::assertNull($event->getRequestContext());
        self::assertSame($submission->toArray(), $event->getSubmission()->toArray());
        self::assertSame($submission->toArray(), $event->getCoordinates()->toArray());
        self::assertNull($event->getResult());

        $event->setResult('signed/contract.pdf');
        self::assertSame('signed/contract.pdf', $event->getResult());

        $this->expectException(LogicException::class);
        $event->getRequest();
    }

    public function testProxyRequestRespondWithIsLikeSetResponse(): void
    {
        $old = new PdfProxyRequestEvent('https://example.com/a.pdf', $this->request());
        $old->setResponse(new Response('%PDF-cached', 200, ['Content-Type' => 'application/pdf']));

        $new = new PdfProxyRequestEvent('https://example.com/a.pdf', $this->request());
        self::assertFalse($new->hasResponse());
        $new->respondWith(200, '%PDF-cached', ['Content-Type' => 'application/pdf']);

        self::assertTrue($new->hasResponse());
        self::assertSame($old->getResponse()?->getStatusCode(), $new->getResponse()?->getStatusCode());
        self::assertSame($old->getResponse()?->getContent(), $new->getResponse()?->getContent());
        self::assertSame($old->getResponse()?->headers->get('Content-Type'), $new->getResponse()?->headers->get('Content-Type'));
        self::assertSame('203.0.113.5', $new->getRequestContext()->getClientIp());
        self::assertSame($new->getRequest()->getPathInfo(), $new->getRequestContext()->path);
    }

    public function testProxyRequestResponseReadersMatchTheDeprecatedGetResponse(): void
    {
        $event = new PdfProxyRequestEvent('https://example.com/a.pdf', $this->request());
        self::assertNull($event->getResponseStatusCode());
        self::assertNull($event->getResponseContent());
        self::assertNull($event->getResponseHeader('Content-Type'));

        $event->respondWith(203, '%PDF-cached', ['Content-Type' => 'application/pdf', 'X-Cache' => 'HIT']);
        self::assertSame($event->getResponse()?->getStatusCode(), $event->getResponseStatusCode());
        self::assertSame(203, $event->getResponseStatusCode());
        self::assertSame('%PDF-cached', $event->getResponseContent());
        self::assertSame('HIT', $event->getResponseHeader('X-Cache'));
        self::assertSame('application/pdf', $event->getResponseHeader('content-type'));
        self::assertNull($event->getResponseHeader('X-Missing'));

        // A streamed response (deprecated setResponse()) has no string body.
        $event->setResponse(new StreamedResponse(static function (): void {
        }, 200));
        self::assertTrue($event->hasResponse());
        self::assertSame(200, $event->getResponseStatusCode());
        self::assertNull($event->getResponseContent());
    }

    public function testProxyResponseContentAndHeaders(): void
    {
        $response = new Response('%PDF-1', 200, ['Content-Type' => 'application/pdf']);
        $event    = new PdfProxyResponseEvent('https://example.com/a.pdf', $this->request(), $response);

        self::assertSame('%PDF-1', $event->getContent());
        self::assertSame(200, $event->getStatusCode());
        $event->setContent('%PDF-2');
        $event->setHeader('X-Cache', 'HIT');

        self::assertSame('%PDF-2', $event->getResponse()->getContent());
        self::assertSame('HIT', $event->getResponse()->headers->get('X-Cache'));
        self::assertSame('Agent/2.0', $event->getRequestContext()->getUserAgent());
    }

    public function testAcroFormProcessedEventContext(): void
    {
        $event = new AcroFormModifiedPdfProcessedEvent('%PDF', 'doc-1', $this->request());

        self::assertSame('POST', $event->getRequestContext()->method);
        self::assertSame($event->getRequest()->getClientIp(), $event->getRequestContext()->getClientIp());
    }

    private function model(): SignatureCoordinatesModel
    {
        return SignatureCoordinatesModel::fromArray([
            'pdf_url'         => 'https://example.com/doc.pdf',
            'signature_boxes' => [['name' => 'buyer', 'page' => 1, 'x' => 5, 'y' => 6]],
            'signing_consent' => true,
            'audit_metadata'  => ['ip' => '203.0.113.5'],
        ]);
    }

    private function request(): Request
    {
        $request = Request::create('/pdf-signable', 'POST', server: ['REMOTE_ADDR' => '203.0.113.5', 'HTTP_USER_AGENT' => 'Agent/2.0']);
        $request->setLocale('fr');

        return $request;
    }
}
