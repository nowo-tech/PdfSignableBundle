<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\DependencyInjection;

use Nowo\FormKitBundle\DependencyInjection\FormKitExtension;
use Nowo\PdfSignableBundle\DependencyInjection\PdfSignableExtension;
use Nowo\PdfSignableBundle\NowoPdfSignableBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The bundle prepends its FormKit profile (REQ-TWIG-005 rule 11 / REQ-UI-003 "Form theme").
 */
final class PdfSignableExtensionFormKitTest extends TestCase
{
    public function testPrependsTheFormKitProfile(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new FormKitExtension());

        (new PdfSignableExtension())->prepend($container);

        $configs = $container->getExtensionConfig('nowo_form_kit');
        self::assertCount(1, $configs);
        $profile = $configs[0]['profiles'][NowoPdfSignableBundle::FORM_KIT_PROFILE];
        self::assertSame(PdfSignableExtension::formKitProfile(), $profile);
        self::assertSame('pdf_signable', $profile['alias']);
        self::assertSame('NowoPdfSignableBundle', $profile['translation_domain']);
        self::assertFalse($profile['auto_help']);
        self::assertFalse($profile['auto_placeholder']);
        self::assertSame('form-select', $profile['field_types']['choice']['attr']['class']);
    }

    public function testHostProfileWins(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new FormKitExtension());
        $container->prependExtensionConfig('nowo_form_kit', ['profiles' => ['pdf_signable' => ['alias' => 'pdf_signable', 'translation_domain' => 'app']]]);

        (new PdfSignableExtension())->prepend($container);

        $configs = $container->getExtensionConfig('nowo_form_kit');
        self::assertCount(1, $configs);
        self::assertSame('app', $configs[0]['profiles']['pdf_signable']['translation_domain']);
    }

    public function testOtherHostProfilesDoNotPreventThePrepend(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new FormKitExtension());
        $container->prependExtensionConfig('nowo_form_kit', ['translation_domain' => 'messages']);
        $container->prependExtensionConfig('nowo_form_kit', ['profiles' => ['default' => ['alias' => 'default']]]);

        (new PdfSignableExtension())->prepend($container);

        $configs = $container->getExtensionConfig('nowo_form_kit');
        self::assertCount(3, $configs);
        self::assertArrayHasKey('pdf_signable', $configs[0]['profiles']);
    }

    public function testNoFormKitExtensionPrependsNothing(): void
    {
        $container = new ContainerBuilder();

        (new PdfSignableExtension())->prepend($container);

        self::assertSame([], $container->getExtensionConfig('nowo_form_kit'));
    }
}
