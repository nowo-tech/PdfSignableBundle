<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Integration\DddHost;

use Nowo\PdfSignableBundle\Event\BatchSignRequestedEvent;
use Nowo\PdfSignableBundle\Event\SignatureCoordinatesSubmittedEvent;
use Nowo\PdfSignableBundle\Model\AuditMetadata;
use Nowo\PdfSignableBundle\Tests\Integration\DddHost\Fixtures\Domain\SignatureRequestLog;
use Nowo\PdfSignableBundle\Tests\Integration\DddHost\Fixtures\Infrastructure\StandardHostProbe;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;

use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * 3.10, REQ-ARCH-001: through the real controller, a DDD host listener that reads only the framework-free accessors
 * gets the same data a standard host listener reads from the deprecated model / request accessors, the audit metadata
 * the controller filled is in the submission, and the JSON response is unchanged.
 */
final class DddHostKernelTest extends TestCase
{
    private ?DddHostKernel $kernel = null;

    protected function setUp(): void
    {
        (new Filesystem())->remove(DddHostKernel::cacheRoot());
    }

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
        (new Filesystem())->remove(DddHostKernel::cacheRoot());
    }

    public function testDddListenerSeesTheSameSubmissionAsTheStandardListener(): void
    {
        $this->kernel = new DddHostKernel();
        $this->kernel->boot();
        $server = ['REMOTE_ADDR' => '198.51.100.23', 'HTTP_USER_AGENT' => 'HostAgent/3.1'];

        $page = $this->kernel->handle(Request::create('/pdf-signable/', 'GET', [], [], [], $server));
        self::assertSame(200, $page->getStatusCode(), (string) $page->getContent());

        $boxes = [
            0 => ['name' => 'buyer', 'page' => 1, 'x' => 50.0, 'y' => 100.0, 'width' => 150.0, 'height' => 40.0],
            1 => ['name' => 'seller', 'page' => 2, 'x' => 10.5, 'y' => 20.25, 'width' => 120.0, 'height' => 30.0],
        ];
        $response = $this->kernel->handle(Request::create('/pdf-signable/', 'POST', [
            'signature_coordinates' => [
                'pdfUrl'         => '/files/contract.pdf',
                'unit'           => 'mm',
                'origin'         => 'bottom_left',
                'signatureBoxes' => $boxes,
            ],
            'batch_sign' => '1',
        ], [], [], $server + ['HTTP_ACCEPT' => 'application/json']));

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        $container = $this->kernel->getContainer();
        $probe     = $container->get(StandardHostProbe::class);
        self::assertInstanceOf(StandardHostProbe::class, $probe);
        self::assertCount(2, $probe->seen);
        self::assertSame([BatchSignRequestedEvent::class, SignatureCoordinatesSubmittedEvent::class], array_column($probe->seen, 'event'));

        foreach ($probe->seen as $seen) {
            // Old versus new: the same coordinates, audit metadata and request data.
            self::assertSame($seen['model'], $seen['submission']);
            self::assertSame($seen['request'], $seen['context']);
        }
        $submitted = $probe->seen[1]['submission'];
        self::assertSame('/files/contract.pdf', $submitted['pdf_url']);
        self::assertSame('198.51.100.23', $submitted['audit_metadata'][AuditMetadata::IP] ?? null);
        self::assertSame('HostAgent/3.1', $submitted['audit_metadata'][AuditMetadata::USER_AGENT] ?? null);

        $log = $container->get(SignatureRequestLog::class);
        self::assertInstanceOf(SignatureRequestLog::class, $log);
        self::assertSame([[
            'document' => '/files/contract.pdf',
            'signers'  => ['buyer', 'seller'],
            'pages'    => [1, 2],
            'consent'  => false,
            'ip'       => '198.51.100.23',
            'auditIp'  => '198.51.100.23',
            'locale'   => 'en',
        ]], $log->entries);

        // The JSON response is built from the model, as before (JSON turns 50.0 into 50: compare loosely).
        $json = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($json['success']);
        self::assertSame('mm', $json['unit']);
        self::assertSame('bottom_left', $json['origin']);
        self::assertEquals([
            ['name' => 'buyer', 'page' => 1, 'x' => 50.0, 'y' => 100.0, 'width' => 150.0, 'height' => 40.0],
            ['name' => 'seller', 'page' => 2, 'x' => 10.5, 'y' => 20.25, 'width' => 120.0, 'height' => 30.0],
        ], $json['coordinates']);
    }
}
