<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\EventListener;

use Nowo\PdfSignableBundle\AcroForm\AcroFormFieldPatch;
use Nowo\PdfSignableBundle\Event\AcroFormApplyRequestEvent;
use Nowo\PdfSignableBundle\EventListener\AcroFormApplyScriptListener;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AcroFormApplyScriptListenerTest extends TestCase
{
    public function testReturnsEarlyWhenEventHasResponse(): void
    {
        $listener = new AcroFormApplyScriptListener(null, 'python3');
        $event    = new AcroFormApplyRequestEvent('%PDF', []);
        $event->setModifiedPdf('%PDF modified');

        $listener($event);

        self::assertSame('%PDF modified', $event->getModifiedPdf());
    }

    public function testReturnsEarlyWhenApplyScriptIsNull(): void
    {
        $listener = new AcroFormApplyScriptListener(null, 'python3');
        $event    = new AcroFormApplyRequestEvent('%PDF', []);

        $listener($event);

        self::assertNull($event->getModifiedPdf());
        self::assertNull($event->getError());
    }

    public function testReturnsEarlyWhenApplyScriptIsEmpty(): void
    {
        $listener = new AcroFormApplyScriptListener('  ', 'python3');
        $event    = new AcroFormApplyRequestEvent('%PDF', []);

        $listener($event);

        self::assertNull($event->getModifiedPdf());
        self::assertNull($event->getError());
    }

    public function testSetsErrorWhenScriptFileDoesNotExist(): void
    {
        $listener = new AcroFormApplyScriptListener('/nonexistent/script.py', 'python3');
        $event    = new AcroFormApplyRequestEvent('%PDF', []);

        $listener($event);

        self::assertNotNull($event->getError());
        self::assertStringContainsString('not found', $event->getError()->getMessage());
        self::assertNull($event->getModifiedPdf());
    }

    /**
     * When script path is a directory (is_file false), listener sets error.
     */
    public function testSetsErrorWhenScriptPathIsDirectory(): void
    {
        $dirPath  = sys_get_temp_dir();
        $listener = new AcroFormApplyScriptListener($dirPath, 'python3');
        $event    = new AcroFormApplyRequestEvent('%PDF', []);

        $listener($event);

        self::assertNotNull($event->getError());
        self::assertStringContainsString('not found', $event->getError()->getMessage());
    }

    /**
     * Deterministic success path: a tiny Python script writes PDF bytes to stdout.
     */
    public function testRunsApplyScriptAndSetsModifiedPdf(): void
    {
        $script = sys_get_temp_dir() . '/pdf_apply_success_' . getmypid() . '.py';
        file_put_contents(
            $script,
            <<<'PY'
                import sys
                # Emit a tiny PDF-like payload (listener checks %PDF prefix)
                sys.stdout.buffer.write(b"%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF")
                PY
        );

        try {
            $patch = AcroFormFieldPatch::fromArray(['fieldId' => 'p1-0']);
            $event = new AcroFormApplyRequestEvent('%PDF-1.4', [$patch]);

            $listener = new AcroFormApplyScriptListener($script, 'python3');
            $listener($event);

            self::assertNull($event->getError(), 'Error: ' . ($event->getErrorDetail() ?? ''));
            self::assertNotNull($event->getModifiedPdf());
            self::assertStringStartsWith('%PDF', $event->getModifiedPdf());
        } finally {
            @unlink($script);
        }
    }

    public function testSetsErrorWhenScriptExitsNonZero(): void
    {
        $script = sys_get_temp_dir() . '/pdf_apply_exit1_' . getmypid() . '.py';
        file_put_contents($script, "import sys\nsys.exit(1)\n");
        try {
            $listener = new AcroFormApplyScriptListener($script, 'python3');
            $event    = new AcroFormApplyRequestEvent('%PDF-1.4', []);

            $listener($event);

            self::assertNotNull($event->getError());
            self::assertStringContainsString('failed', $event->getError()->getMessage());
            self::assertNull($event->getModifiedPdf());
        } finally {
            @unlink($script);
        }
    }

    public function testSetsErrorWhenScriptOutputEmpty(): void
    {
        $script = sys_get_temp_dir() . '/pdf_apply_empty_' . getmypid() . '.py';
        file_put_contents($script, "import sys\n# no output\n");
        try {
            $listener = new AcroFormApplyScriptListener($script, 'python3');
            $event    = new AcroFormApplyRequestEvent('%PDF-1.4', []);

            $listener($event);

            self::assertNotNull($event->getError());
            self::assertStringContainsString('no output', $event->getError()->getMessage());
            self::assertNull($event->getModifiedPdf());
        } finally {
            @unlink($script);
        }
    }

    public function testSetsErrorWhenScriptOutputNotPdf(): void
    {
        $script = sys_get_temp_dir() . '/pdf_apply_text_' . getmypid() . '.py';
        file_put_contents(
            $script,
            <<<'PY'
                import sys
                # Simulate script that prints text instead of PDF
                print("Hello world", file=sys.stdout)
                PY
        );
        try {
            $listener = new AcroFormApplyScriptListener($script, 'python3');
            $event    = new AcroFormApplyRequestEvent('%PDF-1.4', []);

            $listener($event);

            self::assertNotNull($event->getError());
            self::assertStringContainsString('valid PDF', $event->getError()->getMessage());
            self::assertNull($event->getModifiedPdf());
        } finally {
            @unlink($script);
        }
    }

    public function testSetsValidationResultWhenValidateOnlyAndScriptReturnsJson(): void
    {
        $script = sys_get_temp_dir() . '/pdf_apply_dry_' . getmypid() . '.py';
        file_put_contents(
            $script,
            <<<'PY'
                import sys, json
                # Dry-run: output JSON
                json.dump({"success": True, "patches_count": 0}, sys.stdout)
                PY
        );
        try {
            $listener = new AcroFormApplyScriptListener($script, 'python3');
            $event    = new AcroFormApplyRequestEvent('%PDF-1.4', [], true);

            $listener($event);

            self::assertNull($event->getError());
            self::assertIsArray($event->getValidationResult());
            self::assertTrue($event->getValidationResult()['success'] ?? false);
        } finally {
            @unlink($script);
        }
    }

    public function testSetsErrorWhenValidateOnlyAndScriptReturnsNonJson(): void
    {
        $script = sys_get_temp_dir() . '/pdf_apply_dry_invalid_' . getmypid() . '.py';
        file_put_contents($script, "print('not json')\n");
        try {
            $listener = new AcroFormApplyScriptListener($script, 'python3');
            $event    = new AcroFormApplyRequestEvent('%PDF-1.4', [], true);

            $listener($event);

            self::assertNotNull($event->getError());
            self::assertStringContainsString('JSON', $event->getError()->getMessage());
        } finally {
            @unlink($script);
        }
    }

    public function testReturnsEarlyWhenEventHasValidationResult(): void
    {
        $listener = new AcroFormApplyScriptListener('/nonexistent/script.py', 'python3');
        $event    = new AcroFormApplyRequestEvent('%PDF', []);
        $event->setValidationResult(['success' => true]);

        $listener($event);

        self::assertNull($event->getError());
        self::assertSame(['success' => true], $event->getValidationResult());
    }

    public function testReturnsEarlyWhenEventHasError(): void
    {
        $listener = new AcroFormApplyScriptListener('/nonexistent/script.py', 'python3');
        $event    = new AcroFormApplyRequestEvent('%PDF', []);
        $event->setError(new RuntimeException('Previous error'));

        $listener($event);

        $error = $event->getError();
        self::assertNotNull($error);
        self::assertSame('Previous error', $error->getMessage());
    }

    /** When script fails with "python" and "not found" in stderr, listener sets specific error message. */
    public function testSetsSpecificErrorWhenScriptCommandNotFound(): void
    {
        $script = sys_get_temp_dir() . '/pdf_apply_dummy_' . getmypid() . '.py';
        file_put_contents($script, "import sys\nsys.exit(0)\n");
        try {
            $listener = new AcroFormApplyScriptListener($script, 'python999nonexistent');
            $event    = new AcroFormApplyRequestEvent('%PDF-1.4', []);

            $listener($event);

            self::assertNotNull($event->getError());
            self::assertStringContainsString('Python 3 is not installed', $event->getError()->getMessage());
            self::assertNull($event->getModifiedPdf());
        } finally {
            @unlink($script);
        }
    }

    public function testSetsErrorWhenTempFilesCannotBeCreated(): void
    {
        $script = sys_get_temp_dir() . '/pdf_apply_dummy_' . getmypid() . '.py';
        file_put_contents($script, "import sys\nsys.exit(0)\n");
        try {
            $listener = new AcroFormApplyScriptListener(
                $script,
                'python3',
                null,
                static fn (string $prefix) => false,
            );
            $event = new AcroFormApplyRequestEvent('%PDF-1.4', []);

            $listener($event);

            self::assertNotNull($event->getError());
            self::assertStringContainsString('Failed to create temp files', $event->getError()->getMessage());
        } finally {
            @unlink($script);
        }
    }

    public function testSetsErrorWhenWritingTempPdfFails(): void
    {
        $script = sys_get_temp_dir() . '/pdf_apply_dummy_' . getmypid() . '.py';
        file_put_contents($script, "import sys\nsys.exit(0)\n");
        try {
            $listener = new AcroFormApplyScriptListener(
                $script,
                'python3',
                null,
                null,
                static fn (string $path, string $contents) => false,
            );
            $event = new AcroFormApplyRequestEvent('%PDF-1.4', []);

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Failed to write temp PDF');
            $listener($event);
        } finally {
            @unlink($script);
        }
    }

    public function testSetsErrorWhenWritingTempPatchesFails(): void
    {
        $script = sys_get_temp_dir() . '/pdf_apply_dummy_' . getmypid() . '.py';
        file_put_contents($script, "import sys\nsys.exit(0)\n");
        $calls = 0;
        try {
            $listener = new AcroFormApplyScriptListener(
                $script,
                'python3',
                null,
                null,
                static function (string $path, string $contents) use (&$calls) {
                    ++$calls;
                    if ($calls === 1) {
                        return 1;
                    }

                    return false;
                },
            );
            $event = new AcroFormApplyRequestEvent('%PDF-1.4', []);

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Failed to write temp patches');
            $listener($event);
        } finally {
            @unlink($script);
        }
    }
}
