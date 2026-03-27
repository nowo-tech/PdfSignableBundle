<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\AcroForm\Storage;

use Nowo\PdfSignableBundle\AcroForm\AcroFormOverrides;
use Nowo\PdfSignableBundle\AcroForm\Storage\SessionAcroFormOverridesStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class SessionAcroFormOverridesStorageTest extends TestCase
{
    public function testGetReturnsNullWhenNoSession(): void
    {
        $requestStack = new RequestStack();
        $storage      = new SessionAcroFormOverridesStorage($requestStack);

        self::assertNull($storage->get('doc1'));
    }

    public function testGetReturnsNullWhenKeyNotInSession(): void
    {
        $session = new \Symfony\Component\HttpFoundation\Session\Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $storage = new SessionAcroFormOverridesStorage($requestStack);

        self::assertNull($storage->get('doc1'));
    }

    public function testGetReturnsNullWhenSessionHasNonArrayData(): void
    {
        $session = new \Symfony\Component\HttpFoundation\Session\Session(new MockArraySessionStorage());
        $session->set('nowo_pdf_signable.acroform_overrides.doc1', 'not-an-array');
        $request = new Request();
        $request->setSession($session);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $storage = new SessionAcroFormOverridesStorage($requestStack);

        self::assertNull($storage->get('doc1'));
    }

    public function testSetDoesNothingWhenNoSession(): void
    {
        $requestStack = new RequestStack();
        $storage      = new SessionAcroFormOverridesStorage($requestStack);
        $overrides    = new AcroFormOverrides(['f1' => []], 'doc1');

        $storage->set('doc1', $overrides);

        self::assertNull($storage->get('doc1'));
    }

    public function testRemoveDoesNothingWhenNoSession(): void
    {
        $requestStack = new RequestStack();
        $storage      = new SessionAcroFormOverridesStorage($requestStack);

        $storage->remove('doc1');

        self::assertNull($storage->get('doc1'));
    }

    public function testSetAndGetRoundtrip(): void
    {
        $session = new \Symfony\Component\HttpFoundation\Session\Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $storage   = new SessionAcroFormOverridesStorage($requestStack);
        $overrides = new AcroFormOverrides(['f1' => ['label' => 'F1']], 'doc1');

        $storage->set('doc1', $overrides);

        $retrieved = $storage->get('doc1');
        self::assertInstanceOf(AcroFormOverrides::class, $retrieved);
        self::assertSame('doc1', $retrieved->documentKey);
        self::assertSame(['f1' => ['label' => 'F1']], $retrieved->overrides);
    }

    public function testRemove(): void
    {
        $session = new \Symfony\Component\HttpFoundation\Session\Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $storage = new SessionAcroFormOverridesStorage($requestStack);
        $storage->set('doc1', new AcroFormOverrides([], 'doc1'));

        self::assertNotNull($storage->get('doc1'));

        $storage->remove('doc1');

        self::assertNull($storage->get('doc1'));
    }

    public function testSanitizesDocumentKeyForSessionKey(): void
    {
        $session = new \Symfony\Component\HttpFoundation\Session\Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $storage = new SessionAcroFormOverridesStorage($requestStack);
        $storage->set('doc/with/slashes', new AcroFormOverrides(['f1' => []], 'doc/with/slashes'));

        $retrieved = $storage->get('doc/with/slashes');
        self::assertNotNull($retrieved);
        self::assertSame('doc/with/slashes', $retrieved->documentKey);
    }
}
