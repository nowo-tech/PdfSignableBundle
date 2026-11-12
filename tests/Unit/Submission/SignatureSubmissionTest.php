<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Unit\Submission;

use Error;
use LogicException;
use Nowo\PdfSignableBundle\Model\AuditMetadata;
use Nowo\PdfSignableBundle\Model\SignatureBoxModel;
use Nowo\PdfSignableBundle\Model\SignatureCoordinatesModel;
use Nowo\PdfSignableBundle\Submission\PdfRequestContext;
use Nowo\PdfSignableBundle\Submission\PdfRequestContextFactory;
use Nowo\PdfSignableBundle\Submission\SensitiveValues;
use Nowo\PdfSignableBundle\Submission\SignatureBox;
use Nowo\PdfSignableBundle\Submission\SignatureSubmission;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;

use function assert;
use function json_encode;
use function print_r;
use function serialize;
use function var_export;

/**
 * 3.10: the immutable submission is an exact copy of the model (old versus new: same toArray(), same round trip),
 * and its signature / request data does not leak through dumps or casts.
 */
#[CoversClass(SignatureSubmission::class)]
#[CoversClass(SignatureBox::class)]
#[CoversClass(PdfRequestContext::class)]
#[CoversClass(PdfRequestContextFactory::class)]
#[CoversClass(SensitiveValues::class)]
final class SignatureSubmissionTest extends TestCase
{
    private const SIGNATURE = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGNgYGD4DwABBAEAfbLI3wAAAABJRU5ErkJggg==';

    /**
     * @return iterable<string, array{SignatureCoordinatesModel}>
     */
    public static function models(): iterable
    {
        yield 'empty' => [new SignatureCoordinatesModel()];

        yield 'one box, no signature' => [SignatureCoordinatesModel::fromArray([
            'pdf_url'         => 'https://example.com/doc.pdf',
            'unit'            => SignatureCoordinatesModel::UNIT_PT,
            'origin'          => SignatureCoordinatesModel::ORIGIN_TOP_LEFT,
            'signature_boxes' => [['name' => 'signer_1', 'page' => 2, 'x' => 10.5, 'y' => 20.25, 'width' => 150, 'height' => 40]],
        ])];

        yield 'signed boxes, consent and audit' => [SignatureCoordinatesModel::fromArray([
            'pdf_url'         => '/files/contract.pdf',
            'unit'            => SignatureCoordinatesModel::UNIT_MM,
            'origin'          => SignatureCoordinatesModel::ORIGIN_BOTTOM_RIGHT,
            'signature_boxes' => [
                ['name' => 'buyer', 'page' => 1, 'x' => 1, 'y' => 2, 'width' => 3, 'height' => 4, 'angle' => 90, 'signature_data' => self::SIGNATURE, 'signed_at' => '2026-11-12T10:00:00+00:00'],
                ['name' => 'seller', 'page' => 3, 'x' => 0, 'y' => 0, 'width' => 120, 'height' => 30, 'angle' => -15.5, 'signature_data' => '', 'signed_at' => ''],
            ],
            'signing_consent' => true,
            'audit_metadata'  => [
                AuditMetadata::SUBMITTED_AT => '2026-11-12T10:00:01+00:00',
                AuditMetadata::IP           => '203.0.113.7',
                AuditMetadata::USER_AGENT   => 'Mozilla/5.0 (test)',
                AuditMetadata::USER_ID      => 'user-42',
                AuditMetadata::TSA_TOKEN    => 'tsa-token-sample',
                'custom'                    => ['nested' => [1, 2]],
            ],
        ])];

        // Form collections keep their keys (a removed row leaves a gap): the submission keeps them too.
        $gapped = (new SignatureCoordinatesModel())->setPdfUrl('/a.pdf')->setSignatureBoxes([
            0 => (new SignatureBoxModel())->setName('a'),
            2 => (new SignatureBoxModel())->setName('c')->setSignatureData(self::SIGNATURE),
        ]);
        yield 'gapped collection keys' => [$gapped];
    }

    #[DataProvider('models')]
    public function testSubmissionIsAnExactCopyOfTheModel(SignatureCoordinatesModel $model): void
    {
        $submission = SignatureSubmission::fromModel($model);

        self::assertSame($model->toArray(), $submission->toArray());
        self::assertSame($model->toArray(), $submission->toModel()->toArray());
        self::assertSame($model->getPdfUrl(), $submission->pdfUrl);
        self::assertSame($model->getUnit(), $submission->unit);
        self::assertSame($model->getOrigin(), $submission->origin);
        self::assertSame($model->getSigningConsent(), $submission->signingConsent);
        self::assertSame($model->getAuditMetadata(), $submission->getAuditMetadata());
        self::assertSame(array_keys($model->getSignatureBoxes()), array_keys($submission->boxes));

        foreach ($model->getSignatureBoxes() as $key => $box) {
            $copy = $submission->boxes[$key];
            self::assertSame($box->toArray(), $copy->toArray());
            self::assertSame($box->getSignatureData(), $copy->getSignatureData());
            self::assertSame(($box->getSignatureData() ?? '') !== '', $copy->hasSignatureData());
            self::assertSame($box->getSignedAt(), $copy->signedAt);
            self::assertSame($box->getAngle(), $copy->angle);
        }
    }

    public function testSnapshotDoesNotFollowLaterModelChanges(): void
    {
        $model      = SignatureCoordinatesModel::fromArray(['pdf_url' => '/a.pdf', 'signature_boxes' => [['name' => 'a']], 'audit_metadata' => ['k' => 'v']]);
        $submission = SignatureSubmission::fromModel($model);

        $model->setPdfUrl('/b.pdf')->setAuditMetadata(['k' => 'changed']);
        $model->getSignatureBoxes()[0]->setName('changed')->setSignatureData(self::SIGNATURE);

        self::assertSame('/a.pdf', $submission->pdfUrl);
        self::assertSame('v', $submission->getAuditValue('k'));
        self::assertNull($submission->getAuditValue('missing'));
        self::assertSame('a', $submission->boxes[0]->name);
        self::assertNull($submission->boxes[0]->getSignatureData());
    }

    public function testRequestContextFromRequest(): void
    {
        $request = Request::create('/pdf-signable?x=1', 'POST', server: ['REMOTE_ADDR' => '198.51.100.9', 'HTTP_USER_AGENT' => 'Agent/1.0']);
        $request->setLocale('es');

        $context = PdfRequestContextFactory::fromRequest($request);

        self::assertSame('POST', $context->method);
        self::assertSame('/pdf-signable', $context->path);
        self::assertSame('es', $context->locale);
        self::assertSame($request->getClientIp(), $context->getClientIp());
        self::assertSame('Agent/1.0', $context->getUserAgent());

        $bare = new PdfRequestContext('GET', '/', 'en');
        self::assertNull($bare->getClientIp());
        self::assertNull($bare->getUserAgent());
    }

    public function testNothingSensitiveLeaksThroughDumpsOrCasts(): void
    {
        $submission = SignatureSubmission::fromModel(SignatureCoordinatesModel::fromArray([
            'pdf_url'         => '/doc.pdf',
            'signature_boxes' => [['name' => 'buyer', 'signature_data' => self::SIGNATURE]],
            'audit_metadata'  => [AuditMetadata::IP => '203.0.113.77', AuditMetadata::USER_AGENT => 'SecretAgent/9'],
        ]));
        $context = new PdfRequestContext('POST', '/pdf-signable', 'en', '203.0.113.77', 'SecretAgent/9');

        foreach ([$submission, $submission->boxes[0], $context] as $vo) {
            $dump = (new CliDumper())->dump((new VarCloner())->cloneVar($vo), true);
            foreach ([var_export($vo, true), print_r($vo, true), (string) json_encode($vo), (string) $dump, print_r((array) $vo, true)] as $output) {
                self::assertStringNotContainsString('203.0.113.77', $output);
                self::assertStringNotContainsString('SecretAgent', $output);
                self::assertStringNotContainsString('iVBORw0KGgo', $output);
            }
        }

        // Exporting on purpose still works.
        self::assertSame(self::SIGNATURE, $submission->toArray()['signature_boxes'][0]['signature_data'] ?? null);
    }

    /**
     * @return iterable<string, array{object}>
     */
    public static function valueObjects(): iterable
    {
        yield 'submission' => [new SignatureSubmission('/a.pdf', 'mm', 'bottom_left', [])];
        yield 'box' => [new SignatureBox('a', 1, 0.0, 0.0, 10.0, 10.0)];
        yield 'context' => [new PdfRequestContext('GET', '/', 'en')];
    }

    #[DataProvider('valueObjects')]
    public function testCannotBeSerialized(object $vo): void
    {
        $this->expectException(LogicException::class);
        serialize($vo);
    }

    #[DataProvider('valueObjects')]
    public function testCannotBeUnserialized(object $vo): void
    {
        $this->expectException(LogicException::class);
        assert(method_exists($vo, '__unserialize'));
        $vo->__unserialize([]);
    }

    #[DataProvider('valueObjects')]
    public function testCannotBeCloned(object $vo): void
    {
        $this->expectException(Error::class);
        $copy = clone $vo;
        self::assertNotSame($vo, $copy);
    }
}
