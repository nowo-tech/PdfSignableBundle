<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Twig;

use Nowo\PdfSignableBundle\Form\AcroFormFieldEditType;
use Nowo\PdfSignableBundle\Twig\AcroFormFormsTwigExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\PreloadedExtension;
use Twig\TwigFunction;

/**
 * Tests for AcroFormFormsTwigExtension (REQ-TWIG-005 form views for the AcroForm editor).
 */
final class AcroFormFormsTwigExtensionTest extends TestCase
{
    private function extension(): AcroFormFormsTwigExtension
    {
        $factory = Forms::createFormFactoryBuilder()
            ->addExtension(new PreloadedExtension([new AcroFormFieldEditType(fontSizes: [10, 12])], []))
            ->getFormFactory();

        return new AcroFormFormsTwigExtension($factory);
    }

    public function testRegistersFunctions(): void
    {
        $names = array_map(static fn (TwigFunction $f): string => $f->getName(), $this->extension()->getFunctions());

        self::assertSame(['nowo_pdf_signable_acroform_panel_form', 'nowo_pdf_signable_acroform_edit_form'], $names);
    }

    public function testPanelForm(): void
    {
        $view = $this->extension()->panelForm('doc-9');

        self::assertSame('doc-9', $view->children['documentKey']->vars['value']);
        self::assertSame('', $this->extension()->panelForm(null)->children['documentKey']->vars['value']);
    }

    public function testEditFormUsesConfigAndIsNotRequired(): void
    {
        $view = $this->extension()->editForm([
            'field_name_mode'    => 'choice',
            'field_name_choices' => ['a|A', 'b|B'],
            'show_field_rect'    => false,
            'font_sizes'         => null,
        ]);

        self::assertFalse($view->vars['required']);
        self::assertSame('choice', $view->vars['acroform_edit_config']['field_name_mode']);
        self::assertFalse($view->vars['acroform_edit_config']['show_field_rect']);
        self::assertSame([10, 12], $view->vars['acroform_edit_config']['font_sizes']);
        self::assertArrayNotHasKey('rect', $view->children);
        self::assertFalse($view->children['fieldName']->vars['required']);
    }

    public function testEditFormWithoutConfigUsesTypeDefaults(): void
    {
        $view = $this->extension()->editForm(null);

        self::assertSame('input', $view->vars['acroform_edit_config']['field_name_mode']);
        self::assertArrayHasKey('fieldId', $view->children);
    }
}
