<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Tests\Unit\Form;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

use function basename;
use function dirname;
use function file_get_contents;
use function glob;
use function is_array;
use function preg_match_all;
use function sprintf;
use function strrpos;
use function substr;
use function trim;

/**
 * Regression: constraint messages are translated in the validators domain, not in NowoPdfSignableBundle, so the
 * signature forms showed raw keys such as "signing.consent_required". Every dotted constraint message used by the
 * forms must exist in the validators catalogue of every locale the bundle ships.
 */
final class ConstraintMessagesTranslationTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function localeProvider(): iterable
    {
        foreach (glob(dirname(__DIR__, 3) . '/src/Resources/translations/NowoPdfSignableBundle.*.yaml') ?: [] as $file) {
            $locale = basename($file, '.yaml');
            $locale = substr($locale, (int) strrpos($locale, '.') + 1);

            yield $locale => [$locale];
        }
    }

    #[DataProvider('localeProvider')]
    public function testEveryDottedConstraintMessageIsTranslated(string $locale): void
    {
        $file = dirname(__DIR__, 3) . '/src/Resources/translations/validators.' . $locale . '.yaml';
        self::assertFileExists($file, sprintf('No validators catalogue for the %s locale', $locale));
        $catalogue = self::flatten(Yaml::parseFile($file));

        $used = [];
        foreach (glob(dirname(__DIR__, 3) . '/src/Form/*.php') ?: [] as $source) {
            $code = (string) file_get_contents($source);
            preg_match_all("/(?:message|minMessage|maxMessage):\\s*'([a-z_]+(?:\\.[a-z_]+)+)'/", $code, $a);
            preg_match_all("/buildViolation\\('([a-z_]+(?:\\.[a-z_]+)+)'/", $code, $b);
            foreach ([...$a[1], ...$b[1]] as $key) {
                $used[$key] = true;
            }
        }

        self::assertNotSame([], $used, 'No dotted constraint message left in the forms: adjust this test.');
        foreach ($used as $key => $_) {
            self::assertArrayHasKey($key, $catalogue, sprintf('Constraint message "%s" is missing from validators.%s.yaml', $key, $locale));
            self::assertNotSame('', trim($catalogue[$key]));
        }
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<string, string>
     */
    private static function flatten(array $data, string $prefix = ''): array
    {
        $flat = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $flat += self::flatten($value, $prefix . $key . '.');
            } else {
                $flat[$prefix . $key] = (string) $value;
            }
        }

        return $flat;
    }
}
