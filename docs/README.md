# Documentation index — NowoPdfSignableBundle

Quick guide to find each topic. AcroForm: [ACROFORM](ACROFORM.md) (unified guide) and [ACROFORM_FLOWS](ACROFORM_FLOWS.md) (diagrams).

---

## Getting started

| Document | Content |
|----------|---------|
| [INSTALLATION](INSTALLATION.md) | Bundle installation (Composer, assets, minimal configuration). |
| [CONFIGURATION](CONFIGURATION.md) | `nowo_pdf_signable` options (proxy, signing, AcroForm, routes, styles). |
| [USAGE](USAGE.md) | Basic usage: coordinates form, signature box, Twig integration. |
| [WORKFLOW](WORKFLOW.md) | Signature viewer flow: page load, PDF, boxes, sync and form submit. Includes Mermaid diagrams. |

---

## Signing and coordinates

| Document | Content |
|----------|---------|
| [SIGNING_ADVANCED](SIGNING_ADVANCED.md) | Batch signing, PKI, integration with signing services. |
| [EVENTS](EVENTS.md) | Bundle PHP events (SignatureCoordinatesSubmitted, BatchSign, PdfSignRequest, Proxy, AcroForm Apply/Process) and **frontend events** (AcroForm editor ↔ viewer). |

---

## AcroForm (PDF form fields)

| Document | Content |
|----------|---------|
| [ACROFORM](ACROFORM.md) | **Unified guide:** overview, current behaviour, data model (overrides), editor UI, configuration (label, rect, font, checkbox), form types, backend and summary. Recommended entry point. |
| [ACROFORM_FLOWS](ACROFORM_FLOWS.md) | Flow and sequence diagrams: architecture, Load/Save, move-resize, Edit modal, add field, Apply, Process, frontend events and global variables. |
| [ACROFORM_BACKEND_EXTENSION](ACROFORM_BACKEND_EXTENSION.md) | Backend: Layer 1 (overrides) and Layer 2 (apply to PDF), DTOs, endpoints, events `AcroFormApplyRequestEvent` and `AcroFormModifiedPdfProcessedEvent`. |

---

## Proposals and roadmap

| Document | Content |
|----------|---------|
| [PROPOSAL_ACROFORM_PAGE_TYPE](PROPOSAL_ACROFORM_PAGE_TYPE.md) | Proposal for AcroForm page form type. |
| [PROPOSAL_ACROFORM_EDITOR_EXTENSIBILITY](PROPOSAL_ACROFORM_EDITOR_EXTENSIBILITY.md) | Proposal for AcroForm editor extensibility. |
| [ROADMAP](ROADMAP.md) | Bundle roadmap. |

---

## Styles, accessibility and security

| Document | Content |
|----------|---------|
| [STYLES](STYLES.md) | Widget CSS and customisation. |
| [ACCESSIBILITY](ACCESSIBILITY.md) | Viewer and form accessibility. |
| [SECURITY](SECURITY.md) | Security considerations (PDF proxy, URLs, signing). |

---

## Development and release

| Document | Content |
|----------|---------|
| [CONTRIBUTING](CONTRIBUTING.md) | How to contribute to the project. |
| [TESTING](TESTING.md) | PHP tests and execution. |
| [RELEASE](RELEASE.md) | Release and versioning process. |
| [CHANGELOG](CHANGELOG.md) | Change history. |
| [UPGRADING](UPGRADING.md) | Upgrade guide between versions. |
