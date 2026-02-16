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
}
