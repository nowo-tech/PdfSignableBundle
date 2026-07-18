<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

use function array_key_exists;
use function implode;
use function is_array;
use function sprintf;

/**
 * Defines the bundle configuration tree (proxy_enabled, proxy_url_allowlist, example_pdf_url, profiles).
 *
 * @see PdfSignableExtension
 */
final class Configuration implements ConfigurationInterface
{
    /** Configuration root key (nowo_pdf_signable). */
    public const ALIAS = 'nowo_pdf_signable';

    /**
     * Builds the configuration tree for the nowo_pdf_signable bundle.
     *
     * @return TreeBuilder The tree builder with proxy_enabled, proxy_url_allowlist, example_pdf_url and profiles nodes
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(self::ALIAS);
        $root        = $treeBuilder->getRootNode();

        $root
            ->children()
                ->booleanNode('proxy_enabled')
                    ->info('Enable proxy endpoint to fetch external PDFs (avoids CORS)')
                    ->defaultValue(true)
                ->end()
                ->arrayNode('proxy_url_allowlist')
                    ->info('When non-empty, proxy only fetches URLs matching one entry. Each entry: substring of URL, or regex if prefixed with # (e.g. #^https://example\.com/#)')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
                ->scalarNode('example_pdf_url')
                    ->info('Default PDF URL for demo/preload')
                    ->defaultValue('https://www.transportes.gob.es/recursos_mfom/paginabasica/recursos/11_07_2019_modelo_orientativo_de_contrato_de_arrendamiento_de_vivienda.pdf')
                ->end()
                ->booleanNode('debug')
                    ->info('Enable console logging in the browser (PDF viewer and signature boxes)')
                    ->defaultValue(false)
                ->end()
                ->arrayNode('signature')
                    ->info('Signature: global defaults (box dimensions, lock) and profiles by name. Default profile is "default".')
                    ->addDefaultsIfNotSet()
                    ->beforeNormalization()
                        ->always(static fn (?array $v): array => self::normalizeLegacyProfileKeys($v))
                    ->end()
                    ->children()
                        ->scalarNode('default_profile')
                            ->info('Default profile when form option config is not set (e.g. "default"). Resolved from signature.profiles[name]. Legacy key: default_config_alias.')
                            ->defaultValue('default')
                        ->end()
                        ->floatNode('default_box_width')
                            ->info('Default width for new signature boxes (in form unit). Global default; overridable per profile.')
                            ->defaultNull()
                        ->end()
                        ->floatNode('default_box_height')
                            ->info('Default height for new signature boxes (in form unit). Global default; overridable per profile.')
                            ->defaultNull()
                        ->end()
                        ->booleanNode('lock_box_width')
                            ->info('When true, width is fixed (use default_box_width) and the field is hidden. Global default; overridable per profile.')
                            ->defaultValue(false)
                        ->end()
                        ->booleanNode('lock_box_height')
                            ->info('When true, height is fixed (use default_box_height) and the field is hidden. Global default; overridable per profile.')
                            ->defaultValue(false)
                        ->end()
                        ->floatNode('min_box_width')
                            ->info('Minimum width for signature boxes (in form unit). Global default; overridable per profile.')
                            ->defaultNull()
                        ->end()
                        ->floatNode('min_box_height')
                            ->info('Minimum height for signature boxes (in form unit). Global default; overridable per profile.')
                            ->defaultNull()
                        ->end()
                        ->arrayNode('profiles')
                            ->info('Profiles by name. Use form option config: "name" to apply (e.g. config: "fixed_url"). Profile "default" is used when no config is specified (see default_profile). Legacy key: configs. Keys per profile: pdf_url, units, unit_default, origin_default, url_field, show_acroform, default_box_width, etc.')
                            ->useAttributeAsKey('name')
                            ->variablePrototype()->end()
                            ->defaultValue([])
                        ->end()
                    ->end()
                    ->validate()
                        ->always(static fn (array $v): array => self::assertDefaultProfileExists($v, 'signature'))
                    ->end()
                ->end()
                ->arrayNode('audit')
                    ->info('Audit metadata options for evidence trail.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('fill_from_request')
                            ->info('When true, the controller merges IP, user_agent and submitted_at into the model audit_metadata before dispatching SIGNATURE_COORDINATES_SUBMITTED.')
                            ->defaultValue(true)
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('tsa_url')
                    ->info('Optional TSA URL for RFC 3161 timestamps. The bundle does not call it; use in your listener to obtain a timestamp token and set it in audit_metadata.')
                    ->defaultNull()
                ->end()
                ->scalarNode('signing_service_id')
                    ->info('Optional service ID for PKI/PAdES signing. The bundle does not use it; reference in your listener to call your signing service or HSM.')
                    ->defaultNull()
                ->end()
                ->arrayNode('acroform')
                    ->info('AcroForm: platform settings (enabled, scripts, storage) and profiles by name. Default profile is "default".')
                    ->addDefaultsIfNotSet()
                    ->beforeNormalization()
                        ->always(static fn (?array $v): array => self::normalizeLegacyProfileKeys($v))
                    ->end()
                    ->children()
                        ->booleanNode('enabled')
                            ->info('Enable overrides storage and acroform endpoints.')
                            ->defaultValue(false)
                        ->end()
                        ->scalarNode('overrides_storage')
                            ->info("Storage for overrides: 'session' or service id implementing AcroFormOverridesStorageInterface.")
                            ->defaultValue('session')
                        ->end()
                        ->scalarNode('document_key_mode')
                            ->info("'request' = use document_key from request only; 'derive_from_url' = allow pdf_url and derive key from allowlisted URL.")
                            ->defaultValue('request')
                        ->end()
                        ->booleanNode('allow_pdf_modify')
                            ->info('Enable POST /pdf-signable/acroform/apply to return modified PDF (requires editor service or event listener).')
                            ->defaultValue(false)
                        ->end()
                        ->scalarNode('editor_service_id')
                            ->info('Optional service id implementing PdfAcroFormEditorInterface.')
                            ->defaultNull()
                        ->end()
                        ->integerNode('max_pdf_size')
                            ->info('Max PDF size in bytes for apply endpoint (base64 decoded).')
                            ->defaultValue(20971520)
                        ->end()
                        ->integerNode('max_patches')
                            ->info('Max number of patches per apply request.')
                            ->defaultValue(500)
                        ->end()
                        ->scalarNode('fields_extractor_script')
                            ->info('Optional path to Python script that extracts AcroForm fields from a PDF (e.g. scripts/extract_acroform_fields.py). Receives PDF path as first argument; stdout must be a JSON array of field descriptors. When set, POST /acroform/fields/extract is available.')
                            ->defaultNull()
                        ->end()
                        ->scalarNode('apply_script')
                            ->info('Optional path to Python script that applies AcroForm patches to a PDF. Receives --pdf <path> --patches <path-to-json> and outputs modified PDF to stdout. When set, POST /acroform/apply can return the modified PDF via this script.')
                            ->defaultNull()
                        ->end()
                        ->scalarNode('apply_script_command')
                            ->info('Executable used to run the apply_script (e.g. python3, python, or /usr/bin/python3). Used only when apply_script is set.')
                            ->defaultValue('python3')
                        ->end()
                        ->scalarNode('process_script')
                            ->info('Optional path to Python script that processes the modified PDF (e.g. fill, sign). Receives --input <path> [--document-key <key>]; writes result to --output <path>. When set, POST /acroform/process is available; after the script runs, an event is dispatched so the app can save the result.')
                            ->defaultNull()
                        ->end()
                        ->scalarNode('process_script_command')
                            ->info('Executable used to run the process_script (e.g. python3, python, or /usr/bin/python3). Used only when process_script is set.')
                            ->defaultValue('python3')
                        ->end()
                        ->scalarNode('default_profile')
                            ->info('Default profile when form option config is not set (e.g. "default"). Resolved from acroform.profiles[name]. Legacy key: default_config_alias.')
                            ->defaultValue('default')
                        ->end()
                        ->floatNode('min_field_width')
                            ->info('Minimum width for AcroForm fields when moving/resizing (in PDF points). Global default; overridable per profile.')
                            ->defaultValue(12.0)
                        ->end()
                        ->floatNode('min_field_height')
                            ->info('Minimum height for AcroForm fields when moving/resizing (in PDF points). Global default; overridable per profile.')
                            ->defaultValue(12.0)
                        ->end()
                        ->scalarNode('label_mode')
                            ->info('When editing a field, label can be: "input" (free text) or "choice" (select from label_choices plus optional "Other" free text). Global default; overridable per profile.')
                            ->defaultValue('input')
                        ->end()
                        ->arrayNode('label_choices')
                            ->info('List of label options when label_mode is "choice". Global default; overridable per profile.')
                            ->scalarPrototype()->end()
                            ->defaultValue([])
                        ->end()
                        ->scalarNode('label_other_text')
                            ->info('Deprecated: use field_name_other_text. When set, shows "Other" in the (legacy) label select.')
                            ->defaultValue('')
                        ->end()
                        ->scalarNode('field_name_mode')
                            ->info('When editing a field, field name can be: "input" (free text) or "choice" (select from field_name_choices plus optional "Other" free text). Global default; overridable per profile.')
                            ->defaultValue('input')
                        ->end()
                        ->arrayNode('field_name_choices')
                            ->info('List of field name options when field_name_mode is "choice". Format: list of strings (e.g. [\'Name\', \'Date\'] or [\'nombre|Nombre\']), list of { value, label? } objects, or associative array (label => value, e.g. { Nombre: nombre, Apellidos: apellidos }). Global default; overridable per profile.')
                            ->variablePrototype()->end()
                            ->defaultValue([])
                        ->end()
                        ->scalarNode('field_name_other_text')
                            ->info('When set (non-empty), shows an "Other" option in the field name select with this text and a free-text input. Leave empty to hide. Global default; overridable per profile.')
                            ->defaultValue('')
                        ->end()
                        ->booleanNode('show_field_rect')
                            ->info('When editing a field, show the coordinates (rect) input in the modal. Global default; overridable per profile.')
                            ->defaultValue(true)
                        ->end()
                        ->arrayNode('font_sizes')
                            ->info('Allowed font sizes (pt) in the edit-field modal. Empty = number input. Global default; overridable per profile.')
                            ->integerPrototype()->end()
                            ->defaultValue([])
                        ->end()
                        ->arrayNode('font_families')
                            ->info('Allowed font families in the edit-field modal. Global default; overridable per profile.')
                            ->scalarPrototype()->end()
                            ->defaultValue([])
                        ->end()
                        ->arrayNode('profiles')
                            ->info('Profiles by name. Use form option config: "name" to apply (e.g. config: "with_fonts"). Profile "default" is used when no config is specified (see default_profile). Legacy key: configs. Keys per profile: pdf_url, url_field (when false, hides the PDF URL input row), document_key_field (when false, hides the document key input row), document_key, field_name_mode, field_name_choices, field_name_other_text, font_sizes, font_families, show_field_rect, min_field_width, min_field_height, etc.')
                            ->useAttributeAsKey('name')
                            ->variablePrototype()->end()
                            ->defaultValue([])
                        ->end()
                    ->end()
                    ->validate()
                        ->always(static fn (array $v): array => self::assertDefaultProfileExists($v, 'acroform'))
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }

    /**
     * Maps legacy default_config_alias / configs keys to default_profile / profiles.
     *
     * @param array<string, mixed>|null $v
     *
     * @return array<string, mixed>
     */
    private static function normalizeLegacyProfileKeys(?array $v): array
    {
        $v ??= [];

        if (!isset($v['default_profile']) && isset($v['default_config_alias'])) {
            $v['default_profile'] = $v['default_config_alias'];
        }
        unset($v['default_config_alias']);

        if (!isset($v['profiles']) && isset($v['configs']) && is_array($v['configs'])) {
            $v['profiles'] = $v['configs'];
        }
        unset($v['configs']);

        return $v;
    }

    /**
     * When profiles is non-empty, default_profile must be a key in profiles.
     *
     * @param array<string, mixed> $section
     *
     * @return array<string, mixed>
     */
    private static function assertDefaultProfileExists(array $section, string $sectionName): array
    {
        $profiles = $section['profiles'] ?? [];
        if (!is_array($profiles) || $profiles === []) {
            return $section;
        }

        $default = $section['default_profile'] ?? 'default';
        if (!array_key_exists($default, $profiles)) {
            throw new InvalidConfigurationException(sprintf('nowo_pdf_signable.%s.default_profile "%s" is not defined in %s.profiles (%s).', $sectionName, $default, $sectionName, implode(', ', array_keys($profiles))));
        }

        return $section;
    }
}
