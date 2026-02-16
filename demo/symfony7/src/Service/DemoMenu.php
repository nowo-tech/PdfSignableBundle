<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Demo menu structure: grouped for sidebar/offcanvas nav and flat list for prev/next.
 */
final class DemoMenu
{
    /**
     * @return list<array{group: string, items: list<array{route: string, label: string}>}>
     */
    public static function grouped(): array
    {
        return [
            [
                'group' => 'By configuration',
                'items' => [
                    ['route' => 'app_signature', 'label' => 'No config'],
                    ['route' => 'app_signature_default_config', 'label' => 'Config default'],
                    ['route' => 'app_signature_fixed_url', 'label' => 'Config fixed_url'],
                    ['route' => 'app_signature_fixed_url_overridden', 'label' => 'Config overridden'],
                    ['route' => 'app_signature_url_choice', 'label' => 'URL as dropdown'],
                ],
            ],
            [
                'group' => 'Define areas',
                'items' => [
                    ['route' => 'app_signature_limited_boxes', 'label' => 'Limited boxes + name selector'],
                    ['route' => 'app_signature_same_signer_multiple', 'label' => 'Same signer, multiple locations'],
                    ['route' => 'app_signature_unique_per_name', 'label' => 'Unique per name (array)'],
                    ['route' => 'app_signature_page_restriction', 'label' => 'Page restriction'],
                    ['route' => 'app_signature_sorted_boxes', 'label' => 'Sorted boxes'],
                    ['route' => 'app_signature_no_overlap', 'label' => 'No overlapping boxes'],
                    ['route' => 'app_signature_allow_overlap', 'label' => 'Allow overlapping boxes'],
                    ['route' => 'app_signature_rotation', 'label' => 'Rotation (angle + handle)'],
                    ['route' => 'app_signature_defaults_by_name', 'label' => 'Defaults per box name'],
                    ['route' => 'app_signature_fixed_size_boxes', 'label' => 'Fixed size (w/h/position hidden)'],
                    ['route' => 'app_signature_min_size_boxes', 'label' => 'Min box size (30×15 mm)'],
                    ['route' => 'app_signature_snap_to_grid', 'label' => 'Snap to grid + boxes'],
                    ['route' => 'app_signature_guides_and_grid', 'label' => 'Guides and grid'],
                    ['route' => 'app_signature_lazy_load', 'label' => 'Viewer lazy load'],
                    ['route' => 'app_signature_latest_features', 'label' => 'Latest features (combined)'],
                    ['route' => 'app_signature_predefined', 'label' => 'Predefined boxes'],
                ],
            ],
            [
                'group' => 'AcroForm',
                'items' => [
                    ['route' => 'app_signature_acroform_editor', 'label' => 'AcroForm editor (default)'],
                    ['route' => 'app_signature_acroform_editor_label_choice', 'label' => 'AcroForm editor — Label as dropdown'],
                    ['route' => 'app_signature_acroform_editor_no_coords', 'label' => 'AcroForm editor — Coordinates hidden'],
                    ['route' => 'app_signature_acroform_editor_custom_fonts', 'label' => 'AcroForm editor — Custom font options'],
                    ['route' => 'app_signature_acroform_editor_all_options', 'label' => 'AcroForm editor — All options'],
                    ['route' => 'app_signature_acroform_editor_min_size', 'label' => 'AcroForm editor (min field size 24 pt)'],
                ],
            ],
            [
                'group' => 'Signing',
                'items' => [
                    ['route' => 'app_signing_draw', 'label' => 'Draw signature in box'],
                    ['route' => 'app_signing_upload', 'label' => 'Draw or upload signature'],
                    ['route' => 'app_signing_legal_disclaimer', 'label' => 'Legal disclaimer'],
                    ['route' => 'app_signing_predefined_boxes', 'label' => 'Predefined boxes — sign only'],
                    ['route' => 'app_signing_options', 'label' => 'Signing options (AutoFirma, legal)'],
                ],
            ],
            [
                'group' => '',
                'items' => [
                    ['route' => 'nowo_pdf_signable_index', 'label' => 'Bundle route'],
                ],
            ],
        ];
    }

    /**
     * Flat list of all demos (route + label) in menu order, for prev/next.
     *
     * @return list<array{route: string, label: string}>
     */
    public static function flat(): array
    {
        $flat = [];
        foreach (self::grouped() as $section) {
            foreach ($section['items'] as $item) {
                $flat[] = $item;
            }
        }
        return $flat;
    }

    /**
     * Previous and next demo for the given route, or null.
     *
     * @return array{prev: array{route: string, label: string}|null, next: array{route: string, label: string}|null}
     */
    public static function prevNext(string $currentRoute): array
    {
        $flat = self::flat();
        $index = null;
        foreach ($flat as $i => $item) {
            if ($item['route'] === $currentRoute) {
                $index = $i;
                break;
            }
        }
        $prev = $index !== null && $index > 0 ? $flat[$index - 1] : null;
        $next = $index !== null && $index < \count($flat) - 1 ? $flat[$index + 1] : null;
        return ['prev' => $prev, 'next' => $next];
    }

    /**
     * Home page sections with subsections and cards. Used to render the demo index.
     *
     * @return list<array{title: string, items: list<array{type: string, title?: string, col_class?: string, cards?: list<array{title: string, bullets: list<string>, route: string, btn_class: string, card_class?: string}>}>}>
     */
    public static function homeSections(): array
    {
        return [
            [
                'title' => 'Define signature areas',
                'items' => [
                    ['type' => 'subsection', 'title' => 'Named configs usage (nowo_pdf_signable.signature.configs)'],
                    ['type' => 'cards', 'col_class' => 'col-md-6 col-lg-3', 'cards' => [
                        ['title' => 'No config', 'bullets' => ['No <code>config</code> passed to the type', 'Units, origin and example URL from form baseOptions + bundle <code>example_pdf_url</code>', 'Nothing from <code>signature.configs</code>'], 'route' => 'app_signature', 'btn_class' => 'btn-primary'],
                        ['title' => 'Config <code>default</code>', 'bullets' => ['<code>config: \'default\'</code>', '<code>units</code>, <code>unit_default</code>, <code>origin_default</code> from <code>signature.configs.default</code>', 'Options in code override the config'], 'route' => 'app_signature_default_config', 'btn_class' => 'btn-primary'],
                        ['title' => 'Config <code>fixed_url</code>', 'bullets' => ['<code>config: \'fixed_url\'</code>', '<code>url_field: false</code>, <code>show_load_pdf_button: false</code> — URL hidden, no Load PDF button', '<code>unit_field: false</code>, <code>origin_field: false</code> — unit and origin hidden (fixed to default)', 'Single document (template/contract); only boxes form visible'], 'route' => 'app_signature_fixed_url', 'btn_class' => 'btn-primary'],
                        ['title' => 'Config overridden', 'bullets' => ['Same <code>fixed_url</code> config', 'Override in code: <code>unit_default: \'pt\'</code>', 'Shows form options override named config'], 'route' => 'app_signature_fixed_url_overridden', 'btn_class' => 'btn-primary'],
                    ]],
                    ['type' => 'subsection', 'title' => 'URL options (inline)'],
                    ['type' => 'cards', 'col_class' => 'col-md-6 col-lg-4', 'cards' => [
                        ['title' => 'URL as dropdown', 'bullets' => ['<code>url_mode: choice</code>, <code>url_choices</code> (label → URL)', 'User picks document by label; <code>url_placeholder</code> for dropdown', 'Fixed set of PDFs (templates/models)'], 'route' => 'app_signature_url_choice', 'btn_class' => 'btn-primary'],
                    ]],
                    ['type' => 'subsection', 'title' => 'Box / validation options'],
                    ['type' => 'cards', 'col_class' => 'col-md-6 col-lg-4', 'cards' => [
                        ['title' => 'Limited boxes + name selector', 'bullets' => ['<code>min_entries: 1</code>, <code>max_entries: 4</code>', '<code>unique_box_names: true</code> — no duplicate names', '<code>name_mode: choice</code>, <code>name_choices</code> (Signer 1, 2, Witness)'], 'route' => 'app_signature_limited_boxes', 'btn_class' => 'btn-primary'],
                        ['title' => 'Same signer, multiple locations', 'bullets' => ['<code>unique_box_names: false</code> — duplicate names allowed', 'Same name on several boxes = same signer, multiple positions', 'Overlay disambiguates (e.g. <code>signer_1 (1)</code>, <code>signer_1 (2)</code>)'], 'route' => 'app_signature_same_signer_multiple', 'btn_class' => 'btn-primary'],
                        ['title' => 'Unique per name (array)', 'bullets' => ['<code>unique_box_names: [\'signer_1\', \'witness\']</code> — only these unique', '<code>signer_2</code> may appear on multiple boxes', 'One Signer 1 and one Witness; Signer 2 can sign in several places'], 'route' => 'app_signature_unique_per_name', 'btn_class' => 'btn-primary'],
                    ]],
                    ['type' => 'subsection', 'title' => 'Page &amp; box options'],
                    ['type' => 'cards', 'col_class' => 'col-md-6 col-lg-4', 'cards' => [
                        ['title' => 'Page restriction', 'bullets' => ['<code>allowed_pages: [1]</code> — page field is a dropdown (e.g. page 1 only)', 'Use case: single-page contract; restrict boxes to first page'], 'route' => 'app_signature_page_restriction', 'btn_class' => 'btn-primary'],
                        ['title' => 'Sorted boxes', 'bullets' => ['<code>sort_boxes: true</code> — on submit, boxes sorted by page, then Y, then X', 'Deterministic order for export/downstream signing'], 'route' => 'app_signature_sorted_boxes', 'btn_class' => 'btn-primary'],
                        ['title' => 'No overlapping boxes', 'bullets' => ['<code>prevent_box_overlap: true</code> (default) — boxes on the same page cannot overlap', 'Frontend: drag/resize that would overlap is reverted and a message is shown', 'Validation error on submit if two boxes intersect'], 'route' => 'app_signature_no_overlap', 'btn_class' => 'btn-primary'],
                        ['title' => 'Allow overlapping boxes', 'bullets' => ['<code>prevent_box_overlap: false</code> — overlap allowed on the same page', 'No frontend revert; no validation error', 'Use case: testing or intentional overlapping layout'], 'route' => 'app_signature_allow_overlap', 'btn_class' => 'btn-primary'],
                        ['title' => 'Rotation (angle + rotate handle)', 'bullets' => ['<code>enable_rotation: true</code> — angle field per box', 'Viewer shows rotate handle above each overlay; drag to rotate'], 'route' => 'app_signature_rotation', 'btn_class' => 'btn-primary'],
                        ['title' => 'Default values per box name', 'bullets' => ['<code>box_defaults_by_name</code> — width, height, x, y, angle per name', 'Selecting a name in the form fills those fields automatically'], 'route' => 'app_signature_defaults_by_name', 'btn_class' => 'btn-primary'],
                    ]],
                    ['type' => 'cards', 'col_class' => 'col-md-6 col-lg-4', 'cards' => [
                        ['title' => 'Snap to grid + snap to boxes', 'bullets' => ['Two boxes pre-placed; drag one near the other to see edges snap', '<code>snap_to_grid: 10</code> (10 mm) — move any box to see it jump to the grid', 'Fixed PDF loads automatically'], 'route' => 'app_signature_snap_to_grid', 'btn_class' => 'btn-primary'],
                        ['title' => 'Guides and grid', 'bullets' => ['<code>show_grid: true</code>, <code>grid_step</code> — grid overlay on each page (in form unit)', 'Helps align boxes; grid is visual only, does not affect coordinates'], 'route' => 'app_signature_guides_and_grid', 'btn_class' => 'btn-primary'],
                        ['title' => 'Viewer lazy load', 'bullets' => ['<code>viewer_lazy_load: true</code> — PDF.js and signable script load when widget enters viewport', 'Uses IntersectionObserver; useful for long pages with several widgets'], 'route' => 'app_signature_lazy_load', 'btn_class' => 'btn-primary'],
                    ]],
                ],
            ],
            [
                'title' => 'AcroForm',
                'items' => [
                    ['type' => 'subsection', 'title' => 'AcroForm editor (save/load overrides, apply to PDF)'],
                    ['type' => 'cards', 'col_class' => 'col-md-6 col-lg-4', 'cards' => [
                        ['title' => 'AcroForm editor (default)', 'bullets' => ['Save/load overrides (label, controlType, rect, defaultValue) per field', 'Session storage; Apply to PDF / Process'], 'route' => 'app_signature_acroform_editor', 'btn_class' => 'btn-primary'],
                        ['title' => 'AcroForm editor — Label as dropdown', 'bullets' => ['<code>label_mode: choice</code> — label is a select (Nombre, Apellidos, DNI, etc.) + Otro'], 'route' => 'app_signature_acroform_editor_label_choice', 'btn_class' => 'btn-primary'],
                        ['title' => 'AcroForm editor — Coordinates hidden', 'bullets' => ['<code>show_field_rect: false</code> — rect input hidden in edit modal'], 'route' => 'app_signature_acroform_editor_no_coords', 'btn_class' => 'btn-primary'],
                        ['title' => 'AcroForm editor — Custom font options', 'bullets' => ['<code>font_sizes</code> + <code>font_families</code> — select instead of free input'], 'route' => 'app_signature_acroform_editor_custom_fonts', 'btn_class' => 'btn-primary'],
                        ['title' => 'AcroForm editor — All options', 'bullets' => ['Label dropdown + coordinates hidden + custom fonts combined'], 'route' => 'app_signature_acroform_editor_all_options', 'btn_class' => 'btn-primary'],
                        ['title' => 'AcroForm editor (min field size 24 pt)', 'bullets' => ['<code>min_field_width/height: 24</code> — move/resize enforces minimum size'], 'route' => 'app_signature_acroform_editor_min_size', 'btn_class' => 'btn-primary'],
                    ]],
                    ['type' => 'subsection', 'title' => 'Latest features (combined)'],
                    ['type' => 'cards', 'col_class' => 'col-md-6 col-lg-4', 'cards' => [
                        ['title' => 'Page restriction + sort + no overlap + snap', 'bullets' => ['<code>allowed_pages: [1]</code>, <code>sort_boxes: true</code>, <code>prevent_box_overlap: true</code>', '<code>snap_to_grid: 5</code>, <code>snap_to_boxes: true</code> — snap while dragging'], 'route' => 'app_signature_latest_features', 'btn_class' => 'btn-primary', 'card_class' => 'border-primary'],
                    ]],
                    ['type' => 'subsection', 'title' => 'Model prefill'],
                    ['type' => 'cards', 'col_class' => 'col-md-6 col-lg-4', 'cards' => [
                        ['title' => 'Predefined boxes', 'bullets' => ['Model pre-filled on GET: fixed URL, two boxes (<code>signer_1</code>, <code>signer_2</code>)', '<code>url_field: false</code>, <code>max_entries: 5</code>', 'Edit positions and optionally add more boxes'], 'route' => 'app_signature_predefined', 'btn_class' => 'btn-primary'],
                    ]],
                ],
            ],
            [
                'title' => 'Signing (draw / image / legal)',
                'items' => [
                    ['type' => 'subsection', 'title' => 'Capture signature in box and legal disclaimer'],
                    ['type' => 'cards', 'col_class' => 'col-md-6 col-lg-4', 'cards' => [
                        ['title' => 'Draw signature in box', 'bullets' => ['<code>enable_signature_capture: true</code> — draw pad per box', 'Draw with mouse or finger; image in overlay. Low legal validity.'], 'route' => 'app_signing_draw', 'btn_class' => 'btn-success', 'card_class' => 'border-success'],
                        ['title' => 'Draw or upload signature image', 'bullets' => ['Draw pad + file upload per box'], 'route' => 'app_signing_upload', 'btn_class' => 'btn-primary'],
                        ['title' => 'Legal disclaimer', 'bullets' => ['<code>signing_legal_disclaimer</code> + optional URL'], 'route' => 'app_signing_legal_disclaimer', 'btn_class' => 'btn-primary'],
                        ['title' => 'Predefined boxes — sign only', 'bullets' => ['Boxes already placed (2 signers)', '<code>min_entries = max_entries = 2</code> — cannot add or remove boxes', 'Only draw or upload signature in each box'], 'route' => 'app_signing_predefined_boxes', 'btn_class' => 'btn-success', 'card_class' => 'border-success'],
                        ['title' => 'Signing options (AutoFirma, legal)', 'bullets' => ['Legal validity: simple vs qualified signature', 'Links to AutoFirma and roadmap (eIDAS, PKI)', 'Legal disclaimer and signing demos'], 'route' => 'app_signing_options', 'btn_class' => 'btn-info', 'card_class' => 'border-info'],
                    ]],
                ],
            ],
        ];
    }
}
