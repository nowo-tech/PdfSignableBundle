<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Integration\Worker;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

use function gc_collect_cycles;
use function json_decode;
use function memory_get_usage;

use const JSON_THROW_ON_ERROR;

/**
 * REQ-RUNTIME-002 (3.11.0): ONE kept-alive kernel, the services resetter never called (`http_kernel` driven directly,
 * as a FrankenPHP worker host without the resetter does). Two users alternate over the real controllers: the
 * signature form (POST, JSON answer, submission event with audit metadata) and the PDF proxy (MockHttpClient
 * upstream). Each response and each event carries only that request's user data, and memory stays flat.
 */
final class KeptAliveKernelWorkerTest extends TestCase
{
    /** Iterations; each runs one sign POST and one proxy GET (2 x 500 = 1,000 requests). */
    private const ITERATIONS = 500;

    private const MEMORY_MARGIN = 4 * 1024 * 1024;

    private const USER_IPS = [
        'alice' => '198.51.100.11',
        'bob'   => '198.51.100.22',
    ];

    private ?WorkerHostKernel $kernel = null;

    protected function setUp(): void
    {
        (new Filesystem())->remove(WorkerHostKernel::cacheRoot());
    }

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
        (new Filesystem())->remove(WorkerHostKernel::cacheRoot());
    }

    public function testAlternatingUsersNeverSeeEachOtherAndMemoryStaysFlat(): void
    {
        $this->kernel = new WorkerHostKernel();
        $this->kernel->boot();
        $container = $this->kernel->getContainer();
        $http      = $container->get('http_kernel');
        self::assertInstanceOf(HttpKernelInterface::class, $http);
        self::assertInstanceOf(TerminableInterface::class, $http);
        $recorder = $container->get(SubmissionRecorder::class);
        self::assertInstanceOf(SubmissionRecorder::class, $recorder);

        $baseline = 0;
        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            $user = $i % 2 === 0 ? 'alice' : 'bob';
            $ip   = self::USER_IPS[$user];
            $pdf  = 'https://' . $user . '.example/contract-' . $i . '.pdf';

            // Signature form: the JSON answer and the submission event hold this user's data only.
            $response = $this->handle($http, Request::create('/pdf-signable/', 'POST', [
                'signature_coordinates' => [
                    'pdfUrl'         => $pdf,
                    'unit'           => 'mm',
                    'origin'         => 'bottom_left',
                    'signatureBoxes' => [0 => ['name' => $user, 'page' => 1, 'x' => 10.0 + $i, 'y' => 20.0, 'width' => 100.0, 'height' => 30.0]],
                ],
            ], [], [], $this->server($user, $i) + ['HTTP_ACCEPT' => 'application/json']));
            self::assertSame(200, $response->getStatusCode(), 'sign ' . $i . ': ' . $response->getContent());
            $json = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
            self::assertTrue($json['success']);
            self::assertCount(1, $json['coordinates'], 'sign ' . $i . ' carries no box of an earlier request');
            self::assertSame($user, $json['coordinates'][0]['name']);
            self::assertEquals(10.0 + $i, $json['coordinates'][0]['x']);
            self::assertSame([[
                'pdfUrl'    => $pdf,
                'signers'   => [$user],
                'ip'        => $ip,
                'auditIp'   => $ip,
                'userAgent' => $user . '-agent/' . $i,
            ]], $recorder->seen, 'sign ' . $i . ': one event, this user only');
            $recorder->seen = [];

            // PDF proxy (role check on the authenticated user, allowlist, SSRF, MockHttpClient upstream).
            $doc      = 'https://' . $user . '.example/doc-' . $i . '.pdf';
            $response = $this->handle($http, Request::create('/pdf-signable/proxy', 'GET', ['url' => $doc], [], [], $this->server($user, $i)));
            self::assertSame(200, $response->getStatusCode(), 'proxy ' . $i . ': ' . $response->getContent());
            self::assertSame('%PDF-1.4 ' . $user . '.example/doc-' . $i . '.pdf', $response->getContent(), 'proxy ' . $i . ' serves this user\'s document');
            self::assertSame('application/pdf', $response->headers->get('Content-Type'));

            if ($i === 50) {
                $baseline = $this->collectedMemory();
            }
        }

        self::assertLessThan(self::MEMORY_MARGIN, $this->collectedMemory() - $baseline, 'memory stays flat over many requests');
    }

    private function handle(HttpKernelInterface&TerminableInterface $http, Request $request): Response
    {
        $response = $http->handle($request, HttpKernelInterface::MAIN_REQUEST, true);
        $http->terminate($request, $response);

        return $response;
    }

    /**
     * @return array<string, string>
     */
    private function server(string $user, int $i): array
    {
        return [
            'PHP_AUTH_USER'   => $user,
            'PHP_AUTH_PW'     => WorkerHostKernel::credentialOf($user),
            'REMOTE_ADDR'     => self::USER_IPS[$user],
            'HTTP_USER_AGENT' => $user . '-agent/' . $i,
        ];
    }

    /**
     * Memory after a cycle collection (request / event objects form cycles freed only when the collector runs).
     */
    private function collectedMemory(): int
    {
        gc_collect_cycles();

        return memory_get_usage();
    }
}
