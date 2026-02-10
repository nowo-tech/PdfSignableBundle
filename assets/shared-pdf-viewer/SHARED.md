# Shared PDF viewer logic (local copy)

This folder is a **copy** of the shared PDF viewer logic. The same folder exists in **PdfTemplateBundle/assets/shared-pdf-viewer**. When you change URL/scale behaviour here, update the other bundle's copy so both stay in sync.

## Contents

- **URL and scale**: `getLoadUrl`, `getScaleForFitWidth`, `getScaleForFitPage` (same logic in both bundles).
- **Constants**: `SCALE_GUTTER`.
- **Types**: `IPdfDocForScale`.

## Usage in this bundle

```ts
import { getLoadUrl, getScaleForFitWidth, getScaleForFitPage } from './shared-pdf-viewer';
```
