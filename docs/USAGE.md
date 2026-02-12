# Usage

## Form type that renders the view

The bundle provides a **Form Type** (`SignatureCoordinatesType`) that:

1. **Renders the view**: when you render the field with `form_widget(form.signatureCoordinates)` (or `form_widget(form)` when the form root is the type), the PDF viewer, unit/origin selector and signature boxes list are shown (the bundle form theme defines the full widget).
2. **Submits coordinates**: on form submit you get a `SignatureCoordinatesModel` with `pdfUrl`, `unit`, `origin` and `signatureBoxes` (each `SignatureBoxModel` with `name`, `page`, `x`, `y`, `width`, `height`, `angle`, and optionally `signatureData` when signing in box is enabled).

The models (`SignatureCoordinatesModel`, `SignatureBoxModel`) define the fields; the Type and form theme handle the view and binding.

## Demo page

The bundle exposes a page at the configured route (default `/pdf-signable`) with:

1. **PDF URL field**: enter the document URL. If the PDF is from another origin, the bundle uses its proxy to load it.
2. **Units**: mm, cm, pt, px, in for coordinates.
3. **Origin**: reference point (top/bottom, left/right) for X and Y.
4. **Signature boxes**: collection of boxes, each with name, page, width, height, X, Y, and optional rotation angle (°).

## Viewer interaction

- **Load PDF**: enter the URL and click "Load PDF". A full-area loading overlay with spinner is shown while the document (or proxy) loads.
- **Thumbnails**: a strip of page thumbnails appears on the left; click a thumbnail to scroll to that page. The current page is highlighted. See [ACCESSIBILITY](ACCESSIBILITY.md) for keyboard and screen reader use.
- **Zoom**: a toolbar (top-right when a PDF is loaded) offers **zoom out** (−), **zoom in** (+) and **fit width** (translated). The PDF loads by default at fit-to-width; zoom range is 0.5×–3×.
- **Add box**: click on the PDF (the box top-left corner is placed where you click) or "Add box".
- **Move**: drag an existing box.
- **Resize**: drag the box corner handles.
- **Delete**: "Delete" button on each box row in the list, or select a box (click its overlay) and press **Delete** / **Backspace**.
- **Keyboard shortcuts** (when focus is not in an input): **Ctrl+Shift+A** add a box (centred on page 1); **Ctrl+Z** undo last box; **Delete** / **Backspace** delete the selected box. Click an overlay to select it; click on the canvas to clear selection.
- **Touch**: on touch devices, **pinch** to zoom and **two-finger drag** to pan the viewer. Mouse/trackpad drag and click-to-add work as usual.

Coordinates stay in sync between the form and the PDF overlays. On submit the form is sent as a normal POST. On success the server redirects and can show a flash message with the saved coordinates (e.g. unit, origin, and a list of boxes); on validation errors the form is re-rendered with the submitted data and error messages. You receive the model with `pdfUrl`, `unit`, `origin` and the list of `signatureBoxes` (each with `name`, `page`, `x`, `y`, `width`, `height`, `angle`).

## Using the form type in your application

You can reuse the form type without using the bundle page:

```php
use Nowo\PdfSignableBundle\Form\SignatureCoordinatesType;
use Nowo\PdfSignableBundle\Model\SignatureCoordinatesModel;

$model = new SignatureCoordinatesModel();
$form = $this->createForm(SignatureCoordinatesType::class, $model);
```

Render the form in your template. **Important**: add the form theme so the widget renders correctly:

```twig
{% form_theme form '@NowoPdfSignable/form/theme.html.twig' %}
{{ form_start(form) }}
{{ form_widget(form.signatureCoordinates) }}
{{ form_end(form) }}
```

The bundle theme is prepended automatically, but when embedding in a custom form you may need to add it explicitly if the widget does not display.

## Named configurations

You can define **named configs** in `nowo_pdf_signable.configs` and reference them when adding the form type so you don’t repeat the same options everywhere.

**1. Define configs** in `config/packages/nowo_pdf_signable.yaml`:

```yaml
nowo_pdf_signable:
    configs:
        fixed_url:
            pdf_url: 'https://example.com/template.pdf'
            url_field: false
            units: ['mm', 'pt']
        limited:
            min_entries: 1
            max_entries: 4
            signature_box_options:
                name_mode: choice
                name_choices: { 'Signer 1': signer_1, 'Witness': witness }
```

**2. Use a named config** with the `config` option (options passed here override the config):

```php
$builder->add('signatureCoordinates', SignatureCoordinatesType::class, [
    'config' => 'fixed_url',
    // optional overrides
    'pdf_url' => 'https://other.com/doc.pdf',
]);
```

If `config` is set, the named config is merged first; any option you pass when creating the form overrides the config. Keys in a named config are the same as the form type options (e.g. `pdf_url`, `url_field`, `units`, `unit_default`, `signature_box_options`).

## Customization options

`SignatureCoordinatesType` is highly configurable via options.

### Config

| Option   | Type   | Default | Description |
|----------|--------|---------|-------------|
| `config` | string \| null | `null` | Name of a config from `nowo_pdf_signable.configs`. That config is merged with the options passed here (passed options win). |

### URL

| Option          | Type   | Default | Description |
|-----------------|--------|---------|-------------|
| `pdf_url`       | string \| null | `null` | Initial PDF URL. When set, the field is pre-filled (or used as the only value when the URL field is hidden). |
| `url_field`     | bool   | `true`  | Whether to show the URL field. If `false` and `pdf_url` is set, the URL is fixed (hidden input) and the viewer loads that PDF. |
| `show_load_pdf_button` | bool | `true` | When `true`, the "Load PDF" button is shown next to the URL input (only applies when `url_field` is true). Set to `false` in fixed-URL configs to hide the button. |
| `url_mode`      | string | `'input'` | `'input'` = text input (UrlType); `'choice'` = dropdown (ChoiceType) with `url_choices`. |
| `url_choices`   | array  | `[]`    | For `url_mode: 'choice'`: map of label => URL (e.g. `['Document A' => 'https://example.com/a.pdf']`). |
| `url_label`     | string | (trans) | Label for the URL field. |
| `url_placeholder`| string | (trans) | Placeholder for the URL input (when `url_mode` is `'input'`); or placeholder option for the select (when `'choice'`). |
| `unit_field`      | bool   | `true`  | When `false`, the unit selector is hidden and the value is fixed to `unit_default` (submitted as a hidden input). Use in locked/config-only setups. |
| `origin_field`    | bool   | `true`  | When `false`, the origin selector is hidden and the value is fixed to `origin_default` (submitted as a hidden input). Use in locked/config-only setups. |

### Unit

| Option          | Type   | Default | Description |
|-----------------|--------|---------|-------------|
| `units`         | array \| null | `null` | Allowed units. `null` = all (`pt`, `mm`, `cm`, `px`, `in`). Else e.g. `['mm', 'cm']`. |
| `unit_default`  | string | `'mm'`  | Default/preset unit when the form has no data. |
| `unit_mode`     | string | `'choice'` | `'choice'` = dropdown; `'input'` = text field (user types the unit, validated against `units`). |
| `unit_label`     | string | (trans) | Label for the unit field. |

### Origin

| Option           | Type   | Default | Description |
|------------------|--------|---------|-------------|
| `origins`        | array \| null | `null` | Allowed origins. `null` = all four corners. Else e.g. `['top_left', 'bottom_left']`. |
| `origin_default` | string | `'bottom_left'` | Default/preset origin. |
| `origin_mode`    | string | `'choice'` | `'choice'` = dropdown; `'input'` = text field (validated against `origins`). |
| `origin_label`   | string | (trans) | Label for the origin field. |

### Signature boxes (collection)

| Option                   | Type   | Default | Description |
|--------------------------|--------|---------|-------------|
| `min_entries`            | int    | `0`     | Minimum number of signature boxes (validated on submit). |
| `max_entries`            | int \| null | `null` | Maximum number of boxes; `null` = unlimited. "Add box" is hidden when at max. |
| `unique_box_names`       | bool \| string[] | `false` | **Global:** `true` = all names must be unique; `false` = no check. **Per name:** array of names (e.g. `['signer_1', 'witness']`) = only those names must be unique; other names may repeat. |
| `signature_box_options`  | array  | `[]`    | Options passed to each **SignatureBoxType** entry (e.g. `name_mode`, `name_choices`). |
| `allowed_pages`          | int[] \| null | `null` | When set, the page field becomes a dropdown and boxes can only be placed on these pages (e.g. `[1]` for first page only). Passed to each box entry. |
| `sort_boxes`             | bool   | `false` | When `true`, on submit the boxes collection is sorted by page, then Y, then X before binding. Useful for deterministic export order. |
| `prevent_box_overlap`    | bool   | `true`  | When `true`, overlapping boxes on the **same page** are rejected: validation fails on submit and the frontend blocks drag/resize that would cause overlap (reverts and shows a message). Set to `false` to allow overlap. |
| `collection_constraints` | array  | `[]`    | Additional Symfony constraints on the **collection** (e.g. custom `Callback`). Merged with built-in constraints (count, unique names, overlap). |
| `box_constraints`        | array  | `[]`    | Additional constraints on each **SignatureBoxModel** (e.g. `Callback` receiving the box). Passed to each entry via `signature_box_options`. |
| `box_defaults_by_name`   | array  | `[]`    | Map of box name to default dimensions: `['signer_1' => ['width' => 150, 'height' => 40, 'x' => 0, 'y' => 0, 'angle' => 0], ...]`. When the user selects a name, the frontend fills in those fields. |
| `enable_rotation`        | bool   | `false` | When `true`, each box has an **angle** field (rotation in degrees) and the viewer shows a **rotate handle** above each overlay. When `false`, the angle field is not rendered and boxes are not rotatable (angle is treated as 0). |
| `snap_to_grid`           | float  | `0`     | Grid step in the current form unit (e.g. `5` for 5 mm). When dragging, box position and size snap to this grid. Use `0` to disable. |
| `snap_to_boxes`          | bool   | `true`  | When `true`, dragging snaps box edges to other boxes’ edges (within ~10 px) on the same page. Set to `false` to disable. |
| `show_grid`             | bool   | `false` | When `true`, a grid overlay is drawn on the PDF (step given by `grid_step` in the form unit) to help align boxes. |
| `grid_step`             | float  | `10`    | Grid step in form unit for the visual grid (e.g. `10` for 10 mm). Used when `show_grid` is `true`. |
| `viewer_lazy_load`      | bool   | `false` | When `true`, PDF.js and the viewer script load only when the coordinates block is visible (IntersectionObserver). Use on long pages to reduce initial load. |
| `enable_signature_capture` | bool | `false` | When `true`, each box shows a draw pad (canvas); image stored in `SignatureBoxModel::signatureData`. Low legal validity. See ROADMAP. |
| `enable_signature_upload`  | bool | `false` | When `true`, each box shows a file input to upload a signature image (same `signatureData`). |
| `signing_legal_disclaimer` | string \| null | `null` | Optional text shown above the PDF viewer. |
| `signing_legal_disclaimer_url` | string \| null | `null` | Optional URL link when disclaimer is set. |
| `signing_require_consent` | bool | `false` | When `true`, a required checkbox is shown (e.g. "I accept the legal effect of this signature"); user must check it to submit. Stored in `SignatureCoordinatesModel::getSigningConsent()`. |
| `signing_consent_label` | string \| null | `'signing.consent_label'` | Label for the consent checkbox (translation key or literal string). |
| `signing_only` | bool | `false` | When `true`, each signature box row shows only the **box name** (read-only) and the **signature capture** (draw/upload). Coordinate fields (page, x, y, width, height, angle) and unit/origin selectors are hidden (values are still submitted). Use for predefined boxes where the user only signs. |
| `batch_sign_enabled` | bool | `false` | When `true`, a **Sign all** button is shown; submitting with that button sends `batch_sign=1` and the bundle dispatches **`BATCH_SIGN_REQUESTED`**. Your listener performs the actual batch signing. See [SIGNING_ADVANCED](SIGNING_ADVANCED.md). |

Predefined elements: set the model’s `signatureBoxes` (e.g. with existing `SignatureBoxModel` instances) before creating the form; the collection will render those entries. The same `SignatureCoordinatesModel` / array structure is returned on submit.

### Signing in boxes (draw or image)

When `enable_signature_capture` and/or `enable_signature_upload` is `true`, each signature box row includes a **draw pad** (canvas) and/or an **upload** input. The image is stored in `SignatureBoxModel::getSignatureData()` and shown inside the box overlay on the PDF. This is a **simple signature** (low legal validity); for qualified/advanced electronic signatures see the [ROADMAP](ROADMAP.md#pdf-signing-and-legal-validity).

### Legal disclaimer

Set `signing_legal_disclaimer` (and optionally `signing_legal_disclaimer_url`) to display a short notice above the viewer, e.g. to inform users that the signature has no qualified legal validity. The URL is shown as a link (translation key `signing.terms_link`).

### Making the signature more legally robust

To strengthen evidential value of the simple (draw/upload) signature:

- **Timestamp per box** — When the user draws or uploads a signature, the frontend sets an ISO 8601 timestamp in `SignatureBoxModel::getSignedAt()`. You can **overwrite with server time** on submit for stronger evidence:
  ```php
  foreach ($model->getSignatureCoordinates()->getSignatureBoxes() as $box) {
      if ($box->getSignatureData() !== null && $box->getSignatureData() !== '') {
          $box->setSignedAt((new \DateTimeImmutable())->format('c'));
      }
  }
  ```
- **Explicit consent** — Set `signing_require_consent: true` (and optionally `signing_consent_label`) so the user must check "I accept the legal effect of this signature" before submitting. The value is in `SignatureCoordinatesModel::getSigningConsent()`.
- **Audit metadata** — The bundle can fill `submitted_at`, `ip` and `user_agent` automatically when `nowo_pdf_signable.audit.fill_from_request` is true (default). Use the `AuditMetadata` class constants for recommended keys (`Nowo\PdfSignableBundle\Model\AuditMetadata`). After a valid submit you can add more (e.g. `user_id`, `tsa_token`) in a listener; see [SIGNING_ADVANCED](SIGNING_ADVANCED.md). Example (manual attach):
  ```php
  $coords = $model->getSignatureCoordinates();
  $coords->setAuditMetadata([
      'signed_at' => (new \DateTimeImmutable())->format('c'),
      'ip' => $request->getClientIp(),
      'user_agent' => $request->headers->get('User-Agent'),
  ]);
  $export = $coords->toArray(); // includes audit_metadata
  ```
  Then persist or log `$export` (or the full model) for evidence.

These measures do not make the signature *qualified* (for that, see [ROADMAP](ROADMAP.md#pdf-signing-and-legal-validity)), but they improve traceability and consent.

### Same signer (machine name), multiple locations

The **name** of a box is a logical identifier (e.g. `signer_1`, `witness`). You can have **several boxes with the same name** when the same signer must sign in more than one place (e.g. initial on page 1 and full signature on page 3).

- **By default** (`unique_box_names: false`): duplicate names are allowed. Add two (or more) boxes and set the same name on each; each box is a separate position. The overlay will show the same color for the same name and, when there are duplicates, a disambiguator in the label (e.g. `signer_1 (1)`, `signer_1 (2)`).
- **Global** `unique_box_names: true`: at most one box per name (all names must be unique). Use when each role has exactly one signature location.
- **Per name** `unique_box_names: ['signer_1', 'witness']`: only the listed names must be unique; other names (e.g. `signer_2`) may appear on multiple boxes.

**Backend handling:** You receive a flat list of `SignatureBoxModel`. To get "all positions per signer", group by name:

```php
$byName = [];
foreach ($model->getSignatureBoxes() as $box) {
    $byName[$box->getName()][] = [
        'page'   => $box->getPage(),
        'x'      => $box->getX(),
        'y'      => $box->getY(),
        'width'  => $box->getWidth(),
        'height' => $box->getHeight(),
    ];
}
// Example: $byName['signer_1'] = [ ['page' => 1, 'x' => 10, ...], ['page' => 3, 'x' => 50, ...] ]
```

### SignatureBoxType (each box): name field

When used as the collection entry type, **SignatureBoxType** accepts options (via `signature_box_options` on `SignatureCoordinatesType`):

| Option            | Type   | Default | Description |
|-------------------|--------|---------|-------------|
| `name_mode`       | string | `'input'` | `'input'` = text (TextType); `'choice'` = dropdown (ChoiceType) with `name_choices`. |
| `name_choices`    | array  | `[]`    | For `name_mode: 'choice'`: map of label => value (e.g. `['Signer 1' => 'signer_1', 'Witness' => 'witness']`). |
| `name_label`      | mixed  | `false` | Label for the name field. |
| `name_placeholder`   | string | (trans) | Placeholder for the **text input** when `name_mode: 'input'` (ignored in choice mode). |
| `choice_placeholder`| `false` \| string | `false` | When `name_mode: 'choice'`, empty option label. Use `false` (default) for **no empty option** (no "Select role"); use a string to show an explicit "Choose…" option. |
| `allowed_pages`     | int[] \| null | `null` | When set (e.g. `[1, 2, 3]`), the page field is rendered as a dropdown with only these pages; validation ensures the submitted page is in the list. Use for single-page or limited-page contracts. Can be set on **SignatureCoordinatesType** (and passed to all boxes) or per box via `signature_box_options`. |

The submitted data is still the same: each box has `name`, `page`, `x`, `y`, `width`, `height`, `angle` in the returned array.

### Example: fixed URL and restricted units

```php
$builder->add('signatureCoordinates', SignatureCoordinatesType::class, [
    'label' => false,
    'pdf_url' => 'https://example.com/template.pdf',
    'url_field' => false,
    'units' => ['mm', 'cm'],
    'unit_default' => 'mm',
    'origins' => ['bottom_left', 'bottom_right'],
    'origin_default' => 'bottom_left',
]);
```

### Example: URL as dropdown

```php
$builder->add('signatureCoordinates', SignatureCoordinatesType::class, [
    'url_mode' => SignatureCoordinatesType::URL_MODE_CHOICE,
    'url_choices' => [
        'Contract' => 'https://example.com/contract.pdf',
        'Terms'    => 'https://example.com/terms.pdf',
    ],
    'url_placeholder' => 'Select a document',
]);
```

### Example: free-text unit and origin (validated)

```php
$builder->add('signatureCoordinates', SignatureCoordinatesType::class, [
    'unit_mode' => SignatureCoordinatesType::UNIT_MODE_INPUT,
    'unit_default' => 'mm',
    'units' => ['mm', 'cm', 'pt'],
    'origin_mode' => SignatureCoordinatesType::ORIGIN_MODE_INPUT,
    'origin_default' => 'bottom_left',
    'origins' => ['top_left', 'bottom_left'],
]);
```

### Example: page restriction (allowed pages)

Restrict signature boxes to specific pages (e.g. first page only):

```php
$builder->add('signatureCoordinates', SignatureCoordinatesType::class, [
    'allowed_pages' => [1],
    'min_entries' => 0,
    'max_entries' => 4,
]);
```

For a range of pages use e.g. `'allowed_pages' => [1, 2, 3]`. The page field is rendered as a dropdown; validation rejects any other page.

### Example: sorted boxes on submit

Ensure boxes are stored in a deterministic order (page, then Y, then X):

```php
$builder->add('signatureCoordinates', SignatureCoordinatesType::class, [
    'sort_boxes' => true,
]);
```

### Example: no overlapping boxes

By default, overlapping boxes on the same page are **prevented**: the frontend blocks drag/resize that would cause overlap (reverts and shows a message), and validation on submit also rejects overlapping boxes. To **allow** overlap (e.g. for testing), set the option to `false`:

```php
$builder->add('signatureCoordinates', SignatureCoordinatesType::class, [
    'prevent_box_overlap' => false,  // allow overlapping boxes
]);
```

### Export / import coordinates

The models can be exported to or imported from a plain array (e.g. for JSON/YAML persistence or APIs):

- **`SignatureCoordinatesModel::toArray()`** — returns `['pdf_url' => ..., 'unit' => ..., 'origin' => ..., 'signature_boxes' => [...]]`.
- **`SignatureCoordinatesModel::fromArray(array $data)`** — creates a new model from that structure.
- **`SignatureBoxModel::toArray()`** — returns `['name' => ..., 'page' => ..., 'x' => ..., 'y' => ..., 'width' => ..., 'height' => ..., 'angle' => ...]`.
- **`SignatureBoxModel::fromArray(array $data)`** — creates a new box from that structure.

Example: export to JSON and import from a file:

```php
$model = $form->getData(); // SignatureCoordinatesModel
$array = $model->toArray();
$json = json_encode($array, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
file_put_contents('coordinates.json', $json);

// Later: import
$data = json_decode(file_get_contents('coordinates.json'), true);
$model = SignatureCoordinatesModel::fromArray($data);
```

### Example: default values per box name

When the user selects a box name (e.g. from a dropdown), pre-fill width, height, position and angle from a map:

```php
$builder->add('signatureCoordinates', SignatureCoordinatesType::class, [
    'signature_box_options' => [
        'name_mode' => SignatureBoxType::NAME_MODE_CHOICE,
        'name_choices' => ['Signer 1' => 'signer_1', 'Witness' => 'witness'],
    ],
    'box_defaults_by_name' => [
        'signer_1' => ['width' => 150, 'height' => 40, 'x' => 0, 'y' => 0, 'angle' => 0],
        'witness'  => ['width' => 120, 'height' => 30, 'x' => 0, 'y' => 0, 'angle' => 0],
    ],
]);
```

### Example: custom constraints

Add your own validation on the collection or on each box:

```php
use Nowo\PdfSignableBundle\Model\SignatureBoxModel;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

$builder->add('signatureCoordinates', SignatureCoordinatesType::class, [
    'collection_constraints' => [
        new Callback(function (mixed $boxes, ExecutionContextInterface $context): void {
            if (!is_array($boxes) || count($boxes) < 2) return;
            // e.g. custom rule across boxes
        }),
    ],
    'box_constraints' => [
        new Callback(function (SignatureBoxModel $box, ExecutionContextInterface $context): void {
            if ($box->getWidth() > 500) {
                $context->buildViolation('Box too wide.')->addViolation();
            }
        }),
    ],
]);
```

### Example: limited boxes and name as selector

```php
use Nowo\PdfSignableBundle\Form\SignatureBoxType;
use Nowo\PdfSignableBundle\Form\SignatureCoordinatesType;

$builder->add('signatureCoordinates', SignatureCoordinatesType::class, [
    'min_entries' => 1,
    'max_entries' => 4,
    'signature_box_options' => [
        'name_mode' => SignatureBoxType::NAME_MODE_CHOICE,
        'name_choices' => [
            'Signer 1' => 'signer_1',
            'Signer 2' => 'signer_2',
            'Witness' => 'witness',
        ],
        // choice_placeholder => false (default): no empty "Select role" option; user must pick a real choice
    ],
]);
```

### Example: predefined boxes (from model)

```php
$model = new SignatureCoordinatesModel();
$model->setPdfUrl($url);
$model->addSignatureBox((new SignatureBoxModel())->setName('signer_1')->setPage(1)->setWidth(150)->setHeight(40)->setX(0)->setY(0));
$model->addSignatureBox((new SignatureBoxModel())->setName('signer_2')->setPage(1)->setWidth(150)->setHeight(40)->setX(0)->setY(50));
$form = $this->createForm(SignatureCoordinatesType::class, $model, [
    'max_entries' => 5,
]);
```

## Overriding bundle templates

You can override any Twig template provided by the bundle by placing a file with the **same path** inside your project’s `templates/bundles/` directory. Symfony will use your template instead of the bundle’s.

**Important:** The directory name under `templates/bundles/` must be the **bundle name** returned by `Bundle::getName()`. Symfony’s default implementation removes the `Bundle` suffix from the bundle class short name. For this bundle the class is `NowoPdfSignableBundle`, so the name is **`NowoPdfSignable`** (not `NowoPdfSignableBundle`). Use `templates/bundles/NowoPdfSignable/` — if you use `NowoPdfSignableBundle` the override will not be found.

**Why do some bundles use “Bundle” in the path?** Because the path is whatever `getName()` returns. The default removes the suffix, so many bundles use a name without “Bundle” (e.g. `NowoPdfSignable`). Other bundles **override** `getName()` and return a name that includes “Bundle” (e.g. `AcmeUserBundle`) for convention or backwards compatibility; for those, the override directory is `templates/bundles/AcmeUserBundle/`. To know which name to use for a given bundle, check that bundle’s `getName()` in its source or the bundle’s documentation.

### Bundle template paths

The bundle’s views live under `Resources/views/`. To override them, create the same path under `templates/bundles/NowoPdfSignable/`:

| Bundle path (relative to `Resources/views/`) | Override in your project |
|---------------------------------------------|---------------------------|
| `form/theme.html.twig` | `templates/bundles/NowoPdfSignable/form/theme.html.twig` |
| `form/_signature_box_type_widget.html.twig` | `templates/bundles/NowoPdfSignable/form/_signature_box_type_widget.html.twig` |
| `signature/index.html.twig` | `templates/bundles/NowoPdfSignable/signature/index.html.twig` |

### Overriding the form theme

The bundle prepends `@NowoPdfSignable/form/theme.html.twig` to the form themes. When you place your copy in `templates/bundles/NowoPdfSignable/form/theme.html.twig`, Symfony will normally resolve that path to your file. **If the form fields still render with the bundle’s theme** (only the page layout is overridden), add the theme explicitly in `config/packages/twig.yaml` so that Twig uses your overridden template for the form:

```yaml
# config/packages/twig.yaml
twig:
  form_themes:
    - '@NowoPdfSignable/form/theme.html.twig'
    # ... other themes
```

With that in place, the namespace `@NowoPdfSignable` resolves to your `templates/bundles/NowoPdfSignable/` copy and the form fields will use your overridden theme.

- **Full override:** Copy `form/theme.html.twig` from the bundle (or from the bundle repo) to `templates/bundles/NowoPdfSignable/form/theme.html.twig` and edit it. The theme defines the blocks `signature_coordinates_widget`, `signature_box_widget`, `signature_box_row`, `form_row`, and `form_errors`. If you change `signature_coordinates_widget`, keep the same root element class (`.nowo-pdf-signable-widget`), data attributes, and element IDs (`#pdf-viewer-container`, `#loadPdfBtn`, `#signature-boxes-list`, etc.) so the bundled JavaScript keeps working. Include the viewer CSS and JS once per request using the Twig function `nowo_pdf_signable_include_assets()` — see [CONTRIBUTING](CONTRIBUTING.md#form-theme-and-assets).
- **Block override only:** To change only the layout of each signature box (or a single block), use a custom form theme that extends or redefines the bundle blocks, and apply it with `{% form_theme form 'form/signature_theme.html.twig' %}` (and keep `@NowoPdfSignable/form/theme.html.twig` in the theme list). See [Reusable SignatureBoxType layout](#reusable-signatureboxtype-layout) below.

### Overriding the signature index view

If you use the bundle’s built-in page (route `/pdf-signable` or as configured), the controller renders `@NowoPdfSignable/signature/index.html.twig`. To customize that page, copy the template from the bundle to `templates/bundles/NowoPdfSignable/signature/index.html.twig` and adjust it. It expects the variables `form`, and optionally `page_title` and `config_explanation`.

---

## Reusable SignatureBoxType layout

The bundle provides a default Twig layout for **SignatureBoxType** (each box: name, page, width, height, x, y, angle) that you can reuse or override like any bundle theme.

### Default usage

- The layout is in the bundle form theme (`@NowoPdfSignable/form/theme.html.twig`), which is registered automatically.
- The concrete fragment is at `@NowoPdfSignable/form/_signature_box_type_widget.html.twig`: two rows (name + page; width, height, x, y, angle) with the Bootstrap classes the viewer JS expects (`.signature-box-name`, `.signature-box-page`, `.signature-box-angle`, etc.). The overlay is drawn with CSS `transform: rotate(angle deg)`.

### Reusing the fragment

If in your own theme you want the same layout and add something around it:

```twig
{% block signature_box_widget %}
  <div class="my-wrapper">
    {% include "@NowoPdfSignable/form/_signature_box_type_widget.html.twig" with { form: form } %}
  </div>
{% endblock %}
```

### Overriding the layout

To change the look of each box completely:

1. Create a form theme in your app (e.g. `templates/form/signature_theme.html.twig`).
2. Define the block `signature_box_widget` (and optionally `signature_box_row`):

```twig
{# templates/form/signature_theme.html.twig #}
{% use "form_div_layout.html.twig" %}

{% block signature_box_widget %}
  <div class="row g-2 mb-2">
    <div class="col-md-6">{{ form_row(form.name) }}</div>
    <div class="col-md-2">{{ form_row(form.page) }}</div>
  </div>
  <div class="row g-2">
    {{ form_row(form.width) }}
    {{ form_row(form.height) }}
    {{ form_row(form.x) }}
    {{ form_row(form.y) }}
  </div>
{% endblock %}
```

3. Apply the theme where you render the form:

```twig
{% form_theme form 'form/signature_theme.html.twig' %}
{{ form_widget(form.signatureCoordinates) }}
```

Or set it globally in `config/packages/twig.yaml`:

```yaml
twig:
  form_themes:
    - 'form/signature_theme.html.twig'
    - '@NowoPdfSignable/form/theme.html.twig'
```

Important: keep the CSS classes the viewer JS uses (`.signature-box-name`, `.signature-box-page`, `.signature-box-width`, `.signature-box-height`, `.signature-box-x`, `.signature-box-y`) on the inputs, or adapt the script if you change the structure.

## Form submit behavior

The bundle’s JavaScript runs on submit to re-index the collection field names (so the server receives consecutive indices `[0]`, `[1]`, …) and then submits the form normally (full page POST). The server handles the request; on success it redirects and can show a flash message with the saved coordinates (e.g. unit, origin, and list of boxes); on validation errors it re-renders the form with the submitted data and field errors. The form theme and `pdf-signable.js` must be loaded on the page where the form is rendered.

**CSS and JS included once per request:** The bundle form theme includes the PDF viewer CSS (`pdf-signable.css`), PDF.js, and `pdf-signable.js` only once per request. If you have several `SignatureCoordinatesType` widgets on the same page (e.g. in a multi-step form or repeated blocks), the link and scripts are output only for the first widget, so the same CSS and JS are not duplicated.

## PDF proxy

The route `GET /pdf-signable/proxy?url=<encoded_url>` returns the PDF content at the given URL. Useful to avoid CORS when the PDF is on another domain. If you disable the proxy (`proxy_enabled: false`), that route returns 403.

When **proxy_url_allowlist** is set in the bundle configuration, the proxy only fetches URLs that match one of the entries (substring or regex). See [Configuration](CONFIGURATION.md).
