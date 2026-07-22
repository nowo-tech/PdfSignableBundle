# Code inventory — 100% traceability

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/pdf-signable-bundle`  
**Last audited**: 2026-07-07

This file proves that **every production source artifact** under `src/` is referenced by the baseline specification. Co-located Vitest files (`*.test.ts`, 22 files) and `Resources/assets/README.md` are test-only or maintainer docs and excluded from the production count.

## PHP classes (`src/**/*.php`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `NowoPdfSignableBundle.php` | Bundle entry | FR-BUNDLE-001 |
| `DependencyInjection/Configuration.php` | Config tree | FR-CFG-001 |
| `DependencyInjection/PdfSignableExtension.php` | DI extension | FR-CFG-002 |
| `DependencyInjection/Compiler/TwigPathsPass.php` | Twig namespace paths | FR-BUNDLE-001 |
| `DependencyInjection/ProxyUrlAllowlistValidationPass.php` | Allowlist compile-time validation | FR-SEC-001 |
| `Checker/DependencyChecker.php` | Optional dependency probe | FR-CLI-001 |
| `Checker/DependencyCheckerInterface.php` | Dependency probe contract | FR-CLI-001 |
| `Command/CheckDependenciesCommand.php` | CLI `nowo:pdf-signable:check-dependencies` | FR-CLI-001 |
| `Controller/SignatureController.php` | Demo form + PDF proxy | FR-CTRL-001, FR-PROXY-001 |
| `Controller/AcroFormOverridesController.php` | AcroForm overrides API | FR-ACRO-004 |
| `Proxy/ProxyUrlValidator.php` | SSRF / allowlist validation | FR-SEC-001, FR-PROXY-001 |
| `Form/SignatureCoordinatesType.php` | Main signature form type | FR-FORM-001 |
| `Form/SignatureBoxType.php` | Signature box collection entry | FR-FORM-001 |
| `Form/Extension/SignatureCoordinatesTypeExtension.php` | Form type extension | FR-FORM-001 |
| `Form/AcroFormEditorType.php` | AcroForm editor form | FR-ACRO-004 |
| `Form/AcroFormFieldEditType.php` | Single field edit sub-form | FR-ACRO-004 |
| `Model/SignatureCoordinatesModel.php` | Signature coordinates DTO | FR-MDL-001 |
| `Model/SignatureBoxModel.php` | Single box DTO | FR-MDL-001 |
| `Model/AuditMetadata.php` | Audit trail metadata | FR-MDL-002 |
| `Model/AcroFormPageModel.php` | AcroForm page DTO | FR-MDL-003 |
| `Event/PdfSignableEvents.php` | Event name constants | FR-EVT-001 |
| `Event/PdfProxyRequestEvent.php` | Proxy request hook | FR-EVT-001 |
| `Event/PdfProxyResponseEvent.php` | Proxy response hook | FR-EVT-001 |
| `Event/SignatureCoordinatesSubmittedEvent.php` | Coordinates submit hook | FR-EVT-001 |
| `Event/BatchSignRequestedEvent.php` | Batch sign hook | FR-EVT-001 |
| `Event/PdfSignRequestEvent.php` | Per-box sign hook | FR-EVT-001 |
| `Event/AcroFormApplyRequestEvent.php` | AcroForm apply hook | FR-EVT-002 |
| `Event/AcroFormModifiedPdfProcessedEvent.php` | AcroForm PDF processed hook | FR-EVT-002 |
| `EventListener/DependencyCheckListener.php` | Dev dependency banner | FR-CLI-001 |
| `EventListener/AcroFormApplyScriptListener.php` | Python AcroForm apply | FR-ACRO-003 |
| `AcroForm/AcroFormOverrides.php` | Overrides value object | FR-ACRO-001 |
| `AcroForm/AcroFormFieldEdit.php` | Field edit DTO | FR-ACRO-002 |
| `AcroForm/AcroFormFieldPatch.php` | Field patch DTO | FR-ACRO-002 |
| `AcroForm/PdfAcroFormEditorInterface.php` | AcroForm editor contract | FR-ACRO-003 |
| `AcroForm/PythonProcessEnv.php` | Python subprocess env | FR-ACRO-003 |
| `AcroForm/Exception/AcroFormEditorException.php` | AcroForm errors | FR-ACRO-003 |
| `AcroForm/Storage/AcroFormOverridesStorageInterface.php` | Overrides storage contract | FR-ACRO-001 |
| `AcroForm/Storage/SessionAcroFormOverridesStorage.php` | Session-backed storage | FR-ACRO-001 |
| `Twig/NowoPdfSignableTwigExtension.php` | Twig helpers | FR-TWIG-001 |

## TypeScript production (`src/Resources/assets/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `signable-editor.ts` | Signable editor entry | FR-UI-SIGN-001 |
| `signable-editor/index.ts` | Editor bootstrap | FR-UI-SIGN-001 |
| `signable-editor/box-drag.ts` | Box drag interaction | FR-UI-SIGN-002 |
| `signable-editor/box-overlays.ts` | Overlay rendering | FR-UI-SIGN-002 |
| `signable-editor/coordinates.ts` | Coordinate math | FR-UI-SIGN-003 |
| `signable-editor/constants.ts` | Editor constants | FR-UI-SIGN-001 |
| `signable-editor/types.ts` | Editor types | FR-UI-SIGN-001 |
| `signable-editor/utils.ts` | Editor utilities | FR-UI-SIGN-001 |
| `signable-editor/grid.ts` | Snap grid | FR-UI-SIGN-004 |
| `signable-editor/font-auto-size.ts` | Auto font sizing | FR-UI-SIGN-005 |
| `signable-editor/signature-pad.ts` | Signature pad widget | FR-UI-SIGN-006 |
| `signable-editor/thumbnails.ts` | Page thumbnails strip | FR-UI-SIGN-007 |
| `signable-editor/touch.ts` | Touch gestures | FR-UI-SIGN-008 |
| `acroform-editor.ts` | AcroForm editor entry | FR-UI-ACRO-001 |
| `acroform-editor/index.ts` | AcroForm bootstrap | FR-UI-ACRO-001 |
| `acroform-editor/config.ts` | AcroForm runtime config | FR-UI-ACRO-001 |
| `acroform-editor/strings.ts` | AcroForm i18n strings | FR-UI-ACRO-002 |
| `acroform-editor/acroform-move-resize.ts` | Field move/resize | FR-UI-ACRO-003 |
| `shared/index.ts` | Shared module barrel | FR-UI-SHR-001 |
| `shared/constants.ts` | Shared constants | FR-UI-SHR-001 |
| `shared/types.ts` | Shared types | FR-UI-SHR-001 |
| `shared/pdfjs-loader.ts` | PDF.js loader | FR-UI-SHR-002 |
| `shared/url-and-scale.ts` | URL + scale helpers | FR-UI-SHR-003 |
| `shared/zoom-toolbar.ts` | Zoom toolbar | FR-UI-SHR-004 |
| `shared/touch.ts` | Shared touch helpers | FR-UI-SHR-005 |
| `logger.ts` | Browser logger | FR-UI-SHR-006 |
| `pdfSignableLogger.ts` | Namespaced logger | FR-UI-SHR-006 |
| `pdfjs-dist.d.ts` | PDF.js type shim | FR-UI-SHR-002 |
| `pdf-signable.scss` | Viewer + overlay styles | FR-UI-SHR-007 |

## Public JavaScript / CSS (`src/Resources/public/js/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `pdf-signable.js` | Built signable bundle (IIFE) | FR-BUILD-001 |
| `pdf-signable.css` | Built signable styles | FR-BUILD-001 |
| `acroform-editor.js` | Built acroform bundle | FR-BUILD-001 |
| `pdf.worker.min.js` | PDF.js worker | FR-BUILD-001, FR-UI-SHR-002 |

## Symfony config (`src/Resources/config/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `services.yaml` | Service wiring | FR-DI-001 |
| `routes.yaml` | Bundle routes | FR-DI-001 |

## Translations (`src/Resources/translations/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `nowo_pdf_signable.en.yaml` | English catalog | FR-I18N-001 |
| `nowo_pdf_signable.es.yaml` | Spanish catalog | FR-I18N-001 |
| `nowo_pdf_signable.ca.yaml` | Catalan catalog | FR-I18N-001 |
| `nowo_pdf_signable.cs.yaml` | Czech catalog | FR-I18N-001 |
| `nowo_pdf_signable.de.yaml` | German catalog | FR-I18N-001 |
| `nowo_pdf_signable.fr.yaml` | French catalog | FR-I18N-001 |
| `nowo_pdf_signable.it.yaml` | Italian catalog | FR-I18N-001 |
| `nowo_pdf_signable.nl.yaml` | Dutch catalog | FR-I18N-001 |
| `nowo_pdf_signable.pl.yaml` | Polish catalog | FR-I18N-001 |
| `nowo_pdf_signable.pt.yaml` | Portuguese catalog | FR-I18N-001 |
| `nowo_pdf_signable.ru.yaml` | Russian catalog | FR-I18N-001 |
| `nowo_pdf_signable.tr.yaml` | Turkish catalog | FR-I18N-001 |

## Twig views (`src/Resources/views/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `form/theme.html.twig` | Form theme root | FR-TWIG-002 |
| `form/_pdf_viewer_partial.html.twig` | PDF.js viewer partial | FR-TWIG-002 |
| `form/_signature_box_type_widget.html.twig` | Box widget | FR-TWIG-002 |
| `signature/index.html.twig` | Demo index page | FR-TWIG-003 |
| `_dependency_debug_alert.html.twig` | Missing-deps alert | FR-TWIG-003 |
| `acroform/editor_root.html.twig` | AcroForm editor shell | FR-TWIG-004 |
| `acroform/_edit_modal.html.twig` | AcroForm edit modal | FR-TWIG-004 |
| `acroform/_edit_modal_body.html.twig` | AcroForm modal body | FR-TWIG-004 |

## Coverage summary

| Category | Files | Mapped |
| --- | ---: | ---: |
| PHP classes | 38 | 38 |
| TypeScript / SCSS production | 29 | 29 |
| Public JS / CSS / worker | 4 | 4 |
| YAML config | 2 | 2 |
| Translation catalogs | 12 | 12 |
| Twig views | 8 | 8 |
| **Total production sources** | **93** | **93** |

Vitest co-located tests (`*.test.ts`, 22 files) and `Resources/assets/README.md` are documented as out-of-scope test/maintainer artifacts in [`spec.md`](spec.md).
