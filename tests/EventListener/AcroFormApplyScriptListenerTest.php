<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\EventListener;

use Nowo\PdfSignableBundle\AcroForm\AcroFormFieldPatch;
use Nowo\PdfSignableBundle\Event\AcroFormApplyRequestEvent;
use Nowo\PdfSignableBundle\EventListener\AcroFormApplyScriptListener;
use PHPUnit\Framework\TestCase;

final class AcroFormApplyScriptListenerTest extends TestCase
{
    public function testReturnsEarlyWhenEventHasResponse(): void
    {
        $listener = new AcroFormApplyScriptListener(null, 'python3');
        $event = new AcroFormApplyRequestEvent('%PDF', []);
        $event->setModifiedPdf('%PDF modified');

        $listener($event);

        self::assertSame('%PDF modified', $event->getModifiedPdf());
    }

    public function testReturnsEarlyWhenApplyScriptIsNull(): void
    {
        $listener = new AcroFormApplyScriptListener(null, 'python3');
        $event = new AcroFormApplyRequestEvent('%PDF', []);

        $listener($event);

        self::assertNull($event->getModifiedPdf());
        self::assertNull($event->getError());
    }

    public function testReturnsEarlyWhenApplyScriptIsEmpty(): void
    {
        $listener = new AcroFormApplyScriptListener('  ', 'python3');
        $event = new AcroFormApplyRequestEvent('%PDF', []);

        $listener($event);

        self::assertNull($event->getModifiedPdf());
        self::assertNull($event->getError());
    }

    public function testSetsErrorWhenScriptFileDoesNotExist(): void
    {
        $listener = new AcroFormApplyScriptListener('/nonexistent/script.py', 'python3');
        $event = new AcroFormApplyRequestEvent('%PDF', []);

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
        $dirPath = sys_get_temp_dir();
        $listener = new AcroFormApplyScriptListener($dirPath, 'python3');
        $event = new AcroFormApplyRequestEvent('%PDF', []);

        $listener($event);

        self::assertNotNull($event->getError());
        self::assertStringContainsString('not found', $event->getError()->getMessage());
    }

    /**
     * Integration test: runs the real apply script when available.
     * Requires python3, pypdf and scripts/apply_acroform_patches.py.
     */
    public function testRunsApplyScriptAndSetsModifiedPdfWhenScriptAvailable(): void
    {
        $scriptPath = dirname(__DIR__, 2).'/scripts/apply_acroform_patches.py';
        if (!is_file($scriptPath)) {
            self::markTestSkipped('Apply script not found');
        }

        $minimalPdfPath = sys_get_temp_dir().'/pdf_signable_minimal_'.getmypid().'.pdf';
        $genScript = sys_get_temp_dir().'/pdf_signable_gen_'.getmypid().'.py';
        file_put_contents($genScript, "from pypdf import PdfWriter\nfrom io import BytesIO\nimport sys\nw=PdfWriter()\nw.add_blank_page(100,100)\nb=BytesIO()\nw.write(b)\nopen(sys.argv[1],'wb').write(b.getvalue())\n");
        $createResult = -1;
        exec(sprintf('python3 %s %s 2>/dev/null', escapeshellarg($genScript), escapeshellarg($minimalPdfPath)), $_, $createResult);
        @unlink($genScript);
        if (0 !== $createResult || !is_file($minimalPdfPath)) {
            @unlink($minimalPdfPath);
            self::markTestSkipped('Could not create minimal PDF (pypdf required)');
        }

        try {
            $pdfContents = file_get_contents($minimalPdfPath);
            $patch = AcroFormFieldPatch::fromArray(['fieldId' => 'p1-0']);
            $event = new AcroFormApplyRequestEvent($pdfContents, [$patch]);

            $listener = new AcroFormApplyScriptListener($scriptPath, 'python3');
            $listener($event);

            self::assertNull($event->getError(), 'Error: '.($event->getErrorDetail() ?? ''));
            self::assertNotNull($event->getModifiedPdf());
            self::assertStringStartsWith('%PDF', $event->getModifiedPdf());
        } finally {
            @unlink($minimalPdfPath);
        }
    }

    public function testSetsErrorWhenScriptExitsNonZero(): void
    {
        $script = sys_get_temp_dir().'/pdf_apply_exit1_'.getmypid().'.py';
        file_put_contents($script, "import sys\nsys.exit(1)\n");
        try {
            $listener = new AcroFormApplyScriptListener($script, 'python3');
            $event = new AcroFormApplyRequestEvent('%PDF-1.4', []);

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
        $script = sys_get_temp_dir().'/pdf_apply_empty_'.getmypid().'.py';
        file_put_contents($script, "import sys\n# no output\n");
        try {
            $listener = new AcroFormApplyScriptListener($script, 'python3');
            $event = new AcroFormApplyRequestEvent('%PDF-1.4', []);

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
        $script = sys_get_temp_dir().'/pdf_apply_text_'.getmypid().'.py';
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
            $event = new AcroFormApplyRequestEvent('%PDF-1.4', []);

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
        $script = sys_get_temp_dir().'/pdf_apply_dry_'.getmypid().'.py';
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
            $event = new AcroFormApplyRequestEvent('%PDF-1.4', [], true);

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
        $script = sys_get_temp_dir().'/pdf_apply_dry_invalid_'.getmypid().'.py';
        file_put_contents($script, "print('not json')\n");
        try {
            $listener = new AcroFormApplyScriptListener($script, 'python3');
            $event = new AcroFormApplyRequestEvent('%PDF-1.4', [], true);

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
        $event = new AcroFormApplyRequestEvent('%PDF', []);
        $event->setValidationResult(['success' => true]);

        $listener($event);

        self::assertNull($event->getError());
        self::assertSame(['success' => true], $event->getValidationResult());
    }

    public function testReturnsEarlyWhenEventHasError(): void
    {
        $listener = new AcroFormApplyScriptListener('/nonexistent/script.py', 'python3');
        $event = new AcroFormApplyRequestEvent('%PDF', []);
        $event->setError(new \RuntimeException('Previous error'));

        $listener($event);

        self::assertSame('Previous error', $event->getError()->getMessage());
    }

    /** When script fails with "python" and "not found" in stderr, listener sets specific error message. */
    public function testSetsSpecificErrorWhenScriptCommandNotFound(): void
    {
        $script = sys_get_temp_dir().'/pdf_apply_dummy_'.getmypid().'.py';
        file_put_contents($script, "import sys\nsys.exit(0)\n");
        try {
            $listener = new AcroFormApplyScriptListener($script, 'python999nonexistent');
            $event = new AcroFormApplyRequestEvent('%PDF-1.4', []);

            $listener($event);

            self::assertNotNull($event->getError());
            self::assertStringContainsString('Python 3 is not installed', $event->getError()->getMessage());
            self::assertNull($event->getModifiedPdf());
        } finally {
            @unlink($script);
        }
    }
}
