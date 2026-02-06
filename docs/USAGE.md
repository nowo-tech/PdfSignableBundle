# Usage

## Form type that renders the view

The bundle provides a **Form Type** (`SignatureCoordinatesType`) that:

1. **Renders the view**: when you render the field with `form_widget(form.signatureCoordinates)` (or `form_widget(form)` when the form root is the type), the PDF viewer, unit/origin selector and signature boxes list are shown (the bundle form theme defines the full widget).
2. **Submits coordinates**: on form submit you get a `SignatureCoordinatesModel` with `pdfUrl`, `unit`, `origin` and `signatureBoxes` (each `SignatureBoxModel` with `name`, `page`, `x`, `y`, `width`, `height`).

The models (`SignatureCoordinatesModel`, `SignatureBoxModel`) define the fields; the Type and form theme handle the view and binding.

## Demo page

The bundle exposes a page at the configured route (default `/pdf-signable`) with:

1. **PDF URL field**: enter the document URL. If the PDF is from another origin, the bundle uses its proxy to load it.
2. **Units**: mm, cm, pt, px, in for coordinates.
3. **Origin**: reference point (top/bottom, left/right) for X and Y.
4. **Signature boxes**: collection of boxes, each with name, page, width, height, X, Y.

## Viewer interaction

- **Load PDF**: enter the URL and click "Load PDF".
- **Add box**: click on the PDF (the box top-left corner is placed where you click) or "Add box".
- **Move**: drag an existing box.
- **Resize**: drag the box corner handles.
- **Delete**: "Delete" button on each box row in the list.

Coordinates stay in sync between the form and the PDF overlays. On submit the form is sent via AJAX (fetch) and, on success, the page stays on the same URL and shows an alert with the submitted coordinates (JSON). You receive the model with `pdfUrl`, `unit`, `origin` and the list of `signatureBoxes` (each with `name`, `page`, `x`, `y`, `width`, `height`).

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

## Customization options

`SignatureCoordinatesType` is highly configurable via options.

### URL

| Option          | Type   | Default | Description |
|-----------------|--------|---------|-------------|
| `pdf_url`       | string \| null | `null` | Initial PDF URL. When set, the field is pre-filled (or used as the only value when the URL field is hidden). |
| `url_field`     | bool   | `true`  | Whether to show the URL field. If `false` and `pdf_url` is set, the URL is fixed (hidden input) and the viewer loads that PDF. |
| `url_mode`      | string | `'input'` | `'input'` = text input (UrlType); `'choice'` = dropdown (ChoiceType) with `url_choices`. |
| `url_choices`   | array  | `[]`    | For `url_mode: 'choice'`: map of label => URL (e.g. `['Document A' => 'https://example.com/a.pdf']`). |
| `url_label`     | string | (trans) | Label for the URL field. |
| `url_placeholder`| string | (trans) | Placeholder for the URL input (when `url_mode` is `'input'`); or placeholder option for the select (when `'choice'`). |

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
| `signature_box_options`  | array  | `[]`    | Options passed to each **SignatureBoxType** entry (e.g. `name_mode`, `name_choices`). |

Predefined elements: set the model’s `signatureBoxes` (e.g. with existing `SignatureBoxModel` instances) before creating the form; the collection will render those entries. The same `SignatureCoordinatesModel` / array structure is returned on submit.

### SignatureBoxType (each box): name field

When used as the collection entry type, **SignatureBoxType** accepts options (via `signature_box_options` on `SignatureCoordinatesType`):

| Option            | Type   | Default | Description |
|-------------------|--------|---------|-------------|
| `name_mode`       | string | `'input'` | `'input'` = text (TextType); `'choice'` = dropdown (ChoiceType) with `name_choices`. |
| `name_choices`    | array  | `[]`    | For `name_mode: 'choice'`: map of label => value (e.g. `['Signer 1' => 'signer_1', 'Witness' => 'witness']`). |
| `name_label`      | mixed  | `false` | Label for the name field. |
| `name_placeholder`| string | (trans) | Placeholder (input) or empty option label (choice). |

The submitted data is still the same: each box has `name`, `page`, `x`, `y`, `width`, `height` in the returned array.

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
        'name_placeholder' => 'Select role',
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

## Reusable SignatureBoxType layout

The bundle provides a default Twig layout for **SignatureBoxType** (each box: name, page, width, height, x, y) that you can reuse or override like any bundle theme.

### Default usage

- The layout is in the bundle form theme (`@NowoPdfSignable/form/theme.html.twig`), which is registered automatically.
- The concrete fragment is at `@NowoPdfSignable/form/_signature_box_type_widget.html.twig`: two rows (name + page; width, height, x, y) with the Bootstrap classes the viewer JS expects (`.signature-box-name`, `.signature-box-page`, etc.).

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

The bundle’s JavaScript intercepts the form submit and sends it via `fetch` with `Accept: application/json`. If the controller returns JSON (valid form), the page does not redirect and an alert shows the submitted coordinates. If the response is HTML (e.g. validation errors) or an error occurs, the user sees an error alert. This applies when the form theme and `pdf-signable.js` are loaded.

## PDF proxy

The route `GET /pdf-signable/proxy?url=<encoded_url>` returns the PDF content at the given URL. Useful to avoid CORS when the PDF is on another domain. If you disable the proxy (`proxy_enabled: false`), that route returns 403.
