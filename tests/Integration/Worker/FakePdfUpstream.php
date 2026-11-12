<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Integration\Worker;

use Symfony\Component\HttpClient\Response\MockResponse;

use function parse_url;

use const PHP_URL_HOST;
use const PHP_URL_PATH;

/**
 * MockHttpClient callback of the worker test: answers every proxied URL with a PDF that names its host and path, so a
 * response served to the wrong user is visible.
 */
final class FakePdfUpstream
{
    public function __invoke(string $method, string $url): MockResponse
    {
        return new MockResponse('%PDF-1.4 ' . parse_url($url, PHP_URL_HOST) . parse_url($url, PHP_URL_PATH), [
            'http_code'        => 200,
            'response_headers' => ['Content-Type' => 'application/pdf'],
        ]);
    }
}
