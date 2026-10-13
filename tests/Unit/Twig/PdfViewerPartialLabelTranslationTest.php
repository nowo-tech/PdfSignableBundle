<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Unit\Twig;

use Nowo\PdfSignableBundle\Form\SignatureBoxType;
use Nowo\PdfSignableBundle\Form\SignatureCoordinatesType;
use Nowo\PdfSignableBundle\Model\SignatureBoxModel;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryBuilder;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Validator\Validation;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

use function dirname;

/**
 * Regression: the PDF URL label in _pdf_viewer_partial.html.twig must be translated
 * (it used to print the raw key "signature_coordinates_type.pdf_url.label"), and so must the
 * signing-only box name taken from a translatable choice label.
 */
final class PdfViewerPartialLabelTranslationTest extends TestCase
{
    private const DOMAIN = 'NowoPdfSignableBundle';

    public function testSignatureCoordinatesPdfUrlLabelIsTranslated(): void
    {
        $view = $this->formFactory()->create(SignatureCoordinatesType::class)->createView();

        $html = $this->renderPartial($view['pdfUrl']);

        self::assertStringContainsString('>PDF URL</label>', $html);
        self::assertStringNotContainsString('signature_coordinates_type.pdf_url.label', $html);
    }

    public function testCustomUrlLabelIsTranslatedInFormTranslationDomain(): void
    {
        $view = $this->formFactory()->create(SignatureCoordinatesType::class, null, [
            'url_label' => 'page.url_label',
        ])->createView();

        $html = $this->renderPartial($view['pdfUrl']);

        self::assertStringContainsString('>PDF URL</label>', $html);
        self::assertStringNotContainsString('page.url_label', $html);
    }

    public function testLabelTranslationParametersAndDomainAreHonoured(): void
    {
        $view = $this->formFactory()->createNamedBuilder('doc', FormType::class, null, ['translation_domain' => 'app'])
            ->add('pdfUrl', UrlType::class, [
                'label'                        => 'doc.url',
                'label_translation_parameters' => ['%name%' => 'contract'],
            ])
            ->getForm()
            ->createView();

        $html = $this->renderPartial($view['pdfUrl']);

        self::assertStringContainsString('>URL of contract</label>', $html);
    }

    public function testTranslationDomainFalsePrintsLabelAsIs(): void
    {
        $view = $this->formFactory()->createNamedBuilder('doc', FormType::class, null, ['translation_domain' => false])
            ->add('pdfUrl', UrlType::class, ['label' => 'signature_coordinates_type.pdf_url.label'])
            ->getForm()
            ->createView();

        $html = $this->renderPartial($view['pdfUrl']);

        self::assertStringContainsString('>signature_coordinates_type.pdf_url.label</label>', $html);
    }

    public function testLabelFalseRendersNoLabel(): void
    {
        $view = $this->formFactory()->create(SignatureCoordinatesType::class, null, ['url_label' => false])->createView();

        $html = $this->renderPartial($view['pdfUrl']);

        self::assertStringNotContainsString('<label', $html);
        self::assertStringContainsString('id="loadPdfBtn"', $html);
    }

    public function testEmptyLabelFallsBackToDefaultUrlLabel(): void
    {
        $view = $this->formFactory()->createNamedBuilder('doc', FormType::class, null, ['translation_domain' => self::DOMAIN])
            ->add('pdfUrl', UrlType::class, ['label' => ''])
            ->getForm()
            ->createView();

        $html = $this->renderPartial($view['pdfUrl']);

        self::assertStringContainsString('>PDF URL</label>', $html);
    }

    public function testSigningOnlyBoxNameChoiceLabelIsTranslated(): void
    {
        $model = (new SignatureBoxModel())->setName('signer');
        $view  = $this->formFactory()->create(SignatureBoxType::class, $model, [
            'name_mode'    => SignatureBoxType::NAME_MODE_CHOICE,
            'name_choices' => ['signature_box_type.page.label' => 'signer'],
            'signing_only' => true,
        ])->createView();

        $html = $this->renderTemplate('@NowoPdfSignableBundle/form/_signature_box_type_widget.html.twig', ['form' => $view]);

        self::assertMatchesRegularExpression('#<strong>\\s*Page\\s*</strong>#', $html);
        self::assertStringNotContainsString('signature_box_type.page.label</strong>', $html);
    }

    private function formFactory(): FormFactoryInterface
    {
        return (new FormFactoryBuilder())
            ->addExtension(new PreloadedExtension([new SignatureBoxType(), new SignatureCoordinatesType('', [])], []))
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->getFormFactory();
    }

    private function renderPartial(FormView $pdfUrlForm): string
    {
        return $this->renderTemplate('@NowoPdfSignableBundle/form/_pdf_viewer_partial.html.twig', ['pdf_url_form' => $pdfUrlForm]);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function renderTemplate(string $template, array $context): string
    {
        $bundleViews = dirname(__DIR__, 3) . '/src/Resources/views';
        $bridgeViews = dirname((string) (new ReflectionClass(FormExtension::class))->getFileName(), 2) . '/Resources/views/Form';

        $loader = new FilesystemLoader([$bridgeViews]);
        $loader->addPath($bundleViews, 'NowoPdfSignableBundle');

        $translator = new Translator('en');
        $translator->addLoader('yaml', new YamlFileLoader());
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('yaml', dirname(__DIR__, 3) . '/src/Resources/translations/NowoPdfSignableBundle.en.yaml', 'en', self::DOMAIN);
        $translator->addResource('array', ['doc.url' => 'URL of %name%'], 'en', 'app');

        $twig = new Environment($loader, ['strict_variables' => true]);
        $twig->addExtension(new TranslationExtension($translator));
        $twig->addExtension(new FormExtension());
        $engine = new TwigRendererEngine(['form_div_layout.html.twig'], $twig);
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([
            FormRenderer::class => static fn (): FormRenderer => new FormRenderer($engine),
        ]));

        return $twig->render($template, $context);
    }
}
