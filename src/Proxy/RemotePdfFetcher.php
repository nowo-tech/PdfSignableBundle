<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Proxy;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

use function in_array;
use function is_array;
use function is_string;
use function max;
use function parse_url;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function strlen;
use function strrpos;
use function strtolower;
use function substr;
use function trim;

use const PHP_URL_HOST;
use const PHP_URL_SCHEME;

/**
 * Fetches a remote PDF with SSRF hardening shared by the proxy and AcroForm endpoints.
 *
 * - Every hop (initial URL and each redirect target) is re-checked against the allowlist and the SSRF rules.
 * - The connection is pinned to the validated IP (HttpClient "resolve") and the client is wrapped in
 *   {@see NoPrivateNetworkHttpClient}, so DNS rebinding between check and connect is rejected.
 * - Redirects are followed manually (HttpClient max_redirects = 0).
 * - The body is streamed and aborted as soon as it exceeds the configured maximum size.
 *
 * @internal
 */
final readonly class RemotePdfFetcher
{
    public const MAX_REDIRECTS = 5;

    private HttpClientInterface $client;

    public function __construct(
        private ProxyUrlValidator $validator,
        ?HttpClientInterface $httpClient = null,
        private float $timeout = 30.0,
    ) {
        $this->client = new NoPrivateNetworkHttpClient($httpClient ?? HttpClient::create());
    }

    /**
     * @param array<string, string> $headers Request headers
     *
     * @throws RemotePdfFetchException
     *
     * @return array{content: string, content_type: string|null, url: string}
     */
    public function fetch(string $url, int $maxBytes, array $headers = []): array
    {
        $timeout = max(0.1, $this->timeout);
        for ($hop = 0;; ++$hop) {
            if (!$this->validator->isAllowedByAllowlist($url)) {
                throw new RemotePdfFetchException(RemotePdfFetchException::REASON_NOT_ALLOWED, sprintf('URL not allowed by allowlist: %s', $url));
            }
            $ip = $this->validator->resolvePublicIp($url);
            if ($ip === null) {
                throw new RemotePdfFetchException(RemotePdfFetchException::REASON_NOT_ALLOWED, sprintf('URL targets a non-public address: %s', $url));
            }
            $host    = strtolower((string) parse_url($url, PHP_URL_HOST));
            $options = [
                'timeout'       => $timeout,
                'max_duration'  => $timeout,
                'max_redirects' => 0,
                'headers'       => $headers,
            ];
            if (!str_starts_with($host, '[') && $host !== $ip) {
                $options['resolve'] = [$host => $ip];
            }

            try {
                $response    = $this->client->request('GET', $url, $options);
                $statusCode  = $response->getStatusCode();
                $respHeaders = $response->getHeaders(false);
            } catch (Throwable $e) {
                throw new RemotePdfFetchException(RemotePdfFetchException::REASON_UPSTREAM, $e->getMessage(), $e);
            }

            if ($statusCode >= 300 && $statusCode < 400 && isset($respHeaders['location'][0])) {
                $response->cancel();
                if ($hop >= self::MAX_REDIRECTS) {
                    throw new RemotePdfFetchException(RemotePdfFetchException::REASON_UPSTREAM, 'Too many redirects');
                }
                $next = $this->resolveLocation($url, $respHeaders['location'][0]);
                if ($next === null) {
                    throw new RemotePdfFetchException(RemotePdfFetchException::REASON_NOT_ALLOWED, 'Invalid redirect target');
                }
                $url = $next;
                continue;
            }
            if ($statusCode < 200 || $statusCode >= 300) {
                $response->cancel();
                throw new RemotePdfFetchException(RemotePdfFetchException::REASON_UPSTREAM, sprintf('Upstream returned HTTP %d', $statusCode));
            }
            if (isset($respHeaders['content-length'][0]) && (int) $respHeaders['content-length'][0] > $maxBytes) {
                $response->cancel();
                throw new RemotePdfFetchException(RemotePdfFetchException::REASON_TOO_LARGE, 'Upstream PDF exceeds maximum size');
            }

            $content  = '';
            $tooLarge = false;
            try {
                foreach ($this->client->stream($response) as $chunk) {
                    $content .= $chunk->getContent();
                    if (strlen($content) > $maxBytes) {
                        $tooLarge = true;
                        break;
                    }
                }
            } catch (Throwable $e) {
                throw new RemotePdfFetchException(RemotePdfFetchException::REASON_UPSTREAM, $e->getMessage(), $e);
            }
            if ($tooLarge) {
                $response->cancel();
                throw new RemotePdfFetchException(RemotePdfFetchException::REASON_TOO_LARGE, 'Upstream PDF exceeds maximum size');
            }

            return [
                'content'      => $content,
                'content_type' => $respHeaders['content-type'][0] ?? null,
                'url'          => $url,
            ];
        }
    }

    /**
     * Returns true when the payload looks like a PDF (%PDF- header within the first 1024 bytes, as readers accept).
     */
    public static function looksLikePdf(string $content): bool
    {
        return str_contains(substr($content, 0, 1024), '%PDF-');
    }

    /**
     * Resolves a Location header against the current URL; only http(s) targets are accepted.
     */
    private function resolveLocation(string $base, string $location): ?string
    {
        $parts = parse_url(trim($location));
        if (!is_array($parts)) {
            return null;
        }
        if (isset($parts['scheme'])) {
            $target = trim($location);
        } else {
            // $base was validated before the request: it always has a scheme and a host.
            $b      = (array) parse_url($base);
            $scheme = (string) ($b['scheme'] ?? 'https');
            $origin = $scheme . '://' . ($b['host'] ?? '') . (isset($b['port']) ? ':' . $b['port'] : '');
            $path   = (string) ($parts['path'] ?? '');
            $query  = isset($parts['query']) ? '?' . $parts['query'] : '';
            if (isset($parts['host'])) {
                $target = $scheme . ':' . trim($location);
            } elseif (str_starts_with($path, '/')) {
                $target = $origin . $path . $query;
            } else {
                $basePath = (string) ($b['path'] ?? '/');
                $target   = $origin . (substr($basePath, 0, (int) strrpos($basePath, '/') + 1) ?: '/') . $path . $query;
            }
        }
        $scheme = parse_url($target, PHP_URL_SCHEME);

        return is_string($scheme) && in_array(strtolower($scheme), ['http', 'https'], true) ? $target : null;
    }
}
