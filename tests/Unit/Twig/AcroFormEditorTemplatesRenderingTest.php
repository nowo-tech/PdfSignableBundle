<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Unit\Twig;

use Nowo\PdfSignableBundle\AcroForm\AcroFormFieldEdit;
use Nowo\PdfSignableBundle\Form\AcroFormFieldEditType;
use Nowo\PdfSignableBundle\Twig\AcroFormFormsTwigExtension;
use Nowo\PdfSignableBundle\Twig\NowoPdfSignableTwigExtension;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;
use Twig\TwigFunction;

use function dirname;

/**
 * REQ-TWIG-005: the AcroForm editor panel and edit modal render from Symfony Form views (with or without a passed
 * acroform_edit_form), keep the ids acroform-editor.js reads, and never emit a nested form element.
 */
final class AcroFormEditorTemplatesRenderingTest extends TestCase
{
    private const DOMAIN = 'NowoPdfSignableBundle';

    public function testEditorRootBuildsFormsWhenNoneArePassed(): void
    {
        $html = $this->render(['document_key' => 'doc-1', 'config' => ['field_name_mode' => 'choice', 'field_name_choices' => ['a|A']]]);

        foreach (['acroform-document-key', 'acroform-overrides-json', 'acroform-edit-field-id', 'acroform-edit-control-type', 'acroform-edit-font-size', 'acroform-edit-field-name-select', 'acroform-edit-hidden'] as $id) {
            self::assertStringContainsString('id="' . $id . '"', $html, $id);
        }
        self::assertStringContainsString('value="doc-1"', $html);
        self::assertStringContainsString('<div id="acroform-edit-form"', $html);
        self::assertStringNotContainsString('<form', $html);
        self::assertDoesNotMatchRegularExpression('/<[^>]*id="acroform-edit-field-name-select"[^>]*required/', $html);
    }

    public function testEditorRootRendersPassedEditForm(): void
    {
        $editForm = $this->formFactory()->create(AcroFormFieldEditType::class, new AcroFormFieldEdit())->createView();

        $html = $this->render(['document_key' => '', 'acroform_edit_form' => $editForm]);

        self::assertStringContainsString('id="acroform-document-key"', $html);
        self::assertStringContainsString('id="acroform-overrides-json"', $html);
        self::assertStringContainsString('id="acroform-edit-field-name"', $html);
        self::assertStringNotContainsString('<form', $html);
    }

    private function formFactory(): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()
            ->addExtension(new PreloadedExtension([new AcroFormFieldEditType()], []))
            ->getFormFactory();
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render(array $context): string
    {
        $bundleViews = dirname(__DIR__, 3) . '/src/Resources/views';
        $bridgeViews = dirname((string) (new ReflectionClass(FormExtension::class))->getFileName(), 2) . '/Resources/views/Form';

        $loader = new FilesystemLoader([$bridgeViews]);
        $loader->addPath($bundleViews, 'NowoPdfSignableBundle');

        $translator = new Translator('en');
        $translator->addLoader('yaml', new YamlFileLoader());
        $translator->addResource('yaml', dirname(__DIR__, 3) . '/src/Resources/translations/NowoPdfSignableBundle.en.yaml', 'en', self::DOMAIN);

        $requestStack = new RequestStack();
        $requestStack->push(new Request());

        $twig = new Environment($loader, ['strict_variables' => true]);
        $twig->addExtension(new TranslationExtension($translator));
        $twig->addExtension(new FormExtension());
        $twig->addExtension(new NowoPdfSignableTwigExtension($requestStack, $translator));
        $twig->addExtension(new AcroFormFormsTwigExtension($this->formFactory()));
        $twig->addFunction(new TwigFunction('csrf_token', static fn (string $id): string => 'token-' . $id));
        $twig->addFunction(new TwigFunction('asset', static fn (string $path, ?string $package = null): string => '/' . $path));
        $twig->addFunction(new TwigFunction('path', static fn (string $route, array $params = []): string => '/' . $route));
        $engine = new TwigRendererEngine(['form_div_layout.html.twig'], $twig);
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([
            FormRenderer::class => static fn (): FormRenderer => new FormRenderer($engine),
        ]));

        return $twig->render('@NowoPdfSignableBundle/acroform/editor_root.html.twig', $context + [
            'load_url' => '/load',
            'post_url' => '/save',
        ]);
    }
}
