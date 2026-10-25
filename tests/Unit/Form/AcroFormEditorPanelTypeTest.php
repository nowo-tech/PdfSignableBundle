<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Form;

use Nowo\PdfSignableBundle\Form\AcroFormEditorPanelType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Forms;

/**
 * Tests for AcroFormEditorPanelType: unmapped client-side controls keep the ids read by acroform-editor.js.
 */
final class AcroFormEditorPanelTypeTest extends TestCase
{
    public function testKeepsIdsAndDocumentKey(): void
    {
        $form = Forms::createFormFactory()->create(AcroFormEditorPanelType::class, null, ['document_key' => 'doc-1']);
        $view = $form->createView();

        self::assertSame('acroform_editor_panel', (new AcroFormEditorPanelType())->getBlockPrefix());
        self::assertSame('acroform-document-key', $view->children['documentKey']->vars['id']);
        self::assertSame('acroform-overrides-json', $view->children['overridesJson']->vars['id']);
        self::assertSame('doc-1', $view->children['documentKey']->vars['value']);
        self::assertSame(6, $view->children['overridesJson']->vars['attr']['rows']);
        self::assertFalse($form->get('documentKey')->getConfig()->getMapped());
        self::assertFalse($form->get('overridesJson')->getConfig()->getMapped());
        self::assertSame('NowoPdfSignableBundle', $form->getConfig()->getOption('translation_domain'));
    }

    public function testNullDocumentKeyIsEmpty(): void
    {
        $view = Forms::createFormFactory()->create(AcroFormEditorPanelType::class, null, ['document_key' => null])->createView();

        self::assertSame('', $view->children['documentKey']->vars['value']);
    }
}
