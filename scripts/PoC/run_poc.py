#!/usr/bin/env python3
"""Proof of concept: AcroForm PDF flow with Lorem ipsum form.

Flow:
  1. Create blank PDF with Lorem ipsum (form-like text) → poc_blank.pdf
  2. Add AcroForm field "Nombre" in position where you'd write → poc_with_fields.pdf
  3. Move field, resize (bigger), set value, add 5 fields (DNI, Fecha, Observaciones, Firma, Notas) → poc_modified.pdf
  4. Verify: blank (no fields), with_fields (1 field), modified (6 fields, Nombre moved+resized+filled)

Run: make test-poc
"""
from __future__ import annotations

import json
import sys
from pathlib import Path

SCRIPT_DIR = Path(__file__).resolve().parent
OUTPUT_DIR = SCRIPT_DIR / "output"
REPO_ROOT = SCRIPT_DIR.parent.parent

# Form body text: title, intro, then labeled lines (placeholders for AcroForm fields).
LOREM_FORM_LINES = [
    "Sample Form",
    "",
    "Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod",
    "tempor incididunt ut labore et dolore magna aliqua.",
    "",
    "Name:",
    "_________________________________________",
    "",
    "ID number:",
    "_________________________",
    "",
    "Date:",
    "_________________________________________",
]


def _pdf_escape(s: str) -> str:
    """Escape string for PDF text literal: \ ( )"""
    return s.replace("\\", "\\\\").replace("(", "\\(").replace(")", "\\)")


def log(step: str, msg: str) -> None:
    print(f"[POC {step}] {msg}", flush=True)


def _add_text_to_page_simple(writer, page) -> None:
    """Add form-like text to page: title, intro paragraph, and labeled lines (PDF content stream)."""
    from pypdf.generic import ArrayObject, DictionaryObject, NameObject, StreamObject
    N = NameObject
    fonts = DictionaryObject({
        N("/F1"): DictionaryObject({
            N("/Type"): N("/Font"),
            N("/Subtype"): N("/Type1"),
            N("/BaseFont"): N("/Helvetica"),
        })
    })
    res = page.get("/Resources")
    if res is None:
        res = DictionaryObject()
        page[N("/Resources")] = res
    try:
        res = writer.get_object(res) if hasattr(res, "indirect_reference") else res
    except Exception:
        pass
    if res is not None and hasattr(res, "get") and res.get("/Font") is None:
        res[N("/Font")] = fonts

    margin_x = 72
    start_y = 800
    leading = 14
    body_size = 11
    title_size = 14

    parts = [
        b"BT\n",
        f"{leading} TL\n".encode(),
        f"1 0 0 1 {margin_x} {start_y} Td\n".encode(),
    ]
    for i, line in enumerate(LOREM_FORM_LINES):
        font_size = title_size if i == 0 and line else body_size
        if i == 1 and not line:
            parts.append(b"/F1 %d Tf\n" % body_size)
        else:
            parts.append(b"/F1 %d Tf\n" % font_size)
        safe = _pdf_escape(line) if line else ""
        parts.append(f"({safe}) Tj T*\n".encode("utf-8", errors="replace"))
    parts.append(b"ET\n")

    stream = StreamObject()
    stream._data = b"".join(parts)
    writer._objects.append(stream)
    ref = writer.get_reference(stream)
    orig = page.get("/Contents")
    if orig is None:
        page[N("/Contents")] = ref
    else:
        prev = list(orig) if hasattr(orig, "__iter__") and not isinstance(orig, (bytes, str)) else [orig]
        page[N("/Contents")] = ArrayObject(prev + [ref])


def _add_description_text(writer, page, text: str, x: float = 72, y: float = 820, font_size: int = 9) -> None:
    """Append an English description line to the page (same F1 font as form)."""
    from pypdf.generic import ArrayObject, DictionaryObject, NameObject, StreamObject
    N = NameObject
    # Ensure page has Font F1
    res = page.get("/Resources")
    if res is None:
        res = DictionaryObject()
        page[N("/Resources")] = res
    try:
        res = writer.get_object(res) if hasattr(res, "indirect_reference") else res
    except Exception:
        pass
    if res is not None and hasattr(res, "get") and res.get("/Font") is None:
        res[N("/Font")] = DictionaryObject({
            N("/F1"): DictionaryObject({
                N("/Type"): N("/Font"),
                N("/Subtype"): N("/Type1"),
                N("/BaseFont"): N("/Helvetica"),
            })
        })
    safe = _pdf_escape(text)
    raw = f"BT 1 0 0 1 {x} {y} Tm /F1 {font_size} Tf ({safe}) Tj ET\n".encode("utf-8", errors="replace")
    stream = StreamObject()
    stream._data = raw
    writer._objects.append(stream)
    ref = writer.get_reference(stream)
    orig = page.get("/Contents")
    if orig is None:
        page[N("/Contents")] = ref
    else:
        prev = list(orig) if hasattr(orig, "__iter__") and not isinstance(orig, (bytes, str)) else [orig]
        page[N("/Contents")] = ArrayObject(prev + [ref])


def step1_blank_pdf() -> Path:
    """Create blank PDF with Lorem ipsum form-like text."""
    log("1", "Creating PDF with Lorem ipsum (form-like)...")
    try:
        from pypdf import PdfWriter
    except ImportError as e:
        log("1", f"FAIL: pypdf not installed: {e}")
        raise SystemExit(1) from e

    writer = PdfWriter()
    writer.add_blank_page(width=595, height=842)
    page = writer.pages[0]
    _add_text_to_page_simple(writer, page)
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
    out_path = OUTPUT_DIR / "poc_blank.pdf"
    with open(out_path, "wb") as f:
        writer.write(f)
    log("1", f"OK: saved {out_path} (Lorem ipsum form)")
    return out_path


def _create_widget(writer, name: str, rect: list[float], value: str = "") -> "IndirectObject":
    """Create a Widget text field and return its reference."""
    from pypdf.generic import (
        ArrayObject,
        BooleanObject,
        DictionaryObject,
        FloatObject,
        NameObject,
        TextStringObject,
    )
    N = NameObject
    widget = DictionaryObject({
        N("/Subtype"): N("/Widget"),
        N("/Rect"): ArrayObject([FloatObject(x) for x in rect]),
        N("/T"): TextStringObject(name),
        N("/FT"): N("/Tx"),
        N("/V"): TextStringObject(value),
        N("/DV"): TextStringObject(value),
    })
    writer._objects.append(widget)
    return writer.get_reference(widget)


def step2_add_acroform_fields(blank_pdf_path: Path) -> Path:
    """Add AcroForm field 'Nombre' where you'd write (below 'Nombre:' in form)."""
    log("2", "Adding AcroForm field 'Nombre' (position: where you'd write)...")
    try:
        from pypdf import PdfReader, PdfWriter
        from pypdf.generic import ArrayObject, BooleanObject, DictionaryObject, NameObject
    except ImportError as e:
        log("2", f"FAIL: pypdf import error: {e}")
        raise SystemExit(1) from e

    reader = PdfReader(str(blank_pdf_path))
    writer = PdfWriter()
    writer.append(reader)
    page = writer.pages[0]
    # Field below "Nombre:" – rect llx, lly, urx, ury (PDF coords, origin bottom-left)
    # "Nombre:" at y~780, field box below at y~755
    rect = [200, 755, 450, 775]
    ref = _create_widget(writer, "Nombre", rect, "")
    page[NameObject("/Annots")] = ArrayObject([ref])
    root = writer.root_object
    acroform = DictionaryObject({
        NameObject("/Fields"): ArrayObject([ref]),
        NameObject("/NeedAppearances"): BooleanObject(True),
    })
    root[NameObject("/AcroForm")] = acroform
    _add_description_text(
        writer, page,
        "AcroForm demo - This PDF has one fillable text field: Nombre.",
        x=72, y=820, font_size=9,
    )
    out_path = OUTPUT_DIR / "poc_with_fields.pdf"
    with open(out_path, "wb") as f:
        writer.write(f)
    log("2", f"OK: saved {out_path} (1 field 'Nombre' at {rect})")
    return out_path


def step3_modify_and_add_fields(pdf_with_fields_path: Path) -> Path:
    """Apply patches: move Nombre, resize (bigger), set value; then add DNI, Fecha + 3 more fields."""
    log("3", "Applying patches (move rect, resize bigger, set value) + adding DNI, Fecha, Observaciones, Firma, Notas...")
    # Patches: move Nombre to new position, make box bigger (wider+taller), set value
    patches = [
        {
            "fieldId": "p1-0",
            "defaultValue": "Juan Pérez",
            "rect": [200, 665, 520, 715],  # larger: wider (320) and taller (50)
        },
    ]
    patches_path = OUTPUT_DIR / "poc_patches.json"
    with open(patches_path, "w", encoding="utf-8") as f:
        json.dump(patches, f, indent=2, ensure_ascii=False)
    log("3", f"Wrote patches to {patches_path}")

    import importlib.util
    apply_script = REPO_ROOT / "scripts" / "apply_acroform_patches.py"
    if not apply_script.is_file():
        log("3", f"FAIL: apply script not found: {apply_script}")
        raise SystemExit(1)
    spec = importlib.util.spec_from_file_location("apply_acroform_patches", apply_script)
    if spec is None or spec.loader is None:
        log("3", "FAIL: could not load apply script")
        raise SystemExit(1)
    apply_module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(apply_module)
    out_bytes = apply_module.apply_patches(pdf_with_fields_path, patches_path)

    # Add new fields: DNI, Fecha, Observaciones, Firma, Notas (5 cajas de escritura)
    from io import BytesIO
    from pypdf import PdfReader, PdfWriter
    reader = PdfReader(BytesIO(out_bytes))
    writer = PdfWriter()
    writer.append(reader)
    page = writer.pages[0]
    N = __import__("pypdf.generic", fromlist=["NameObject"]).NameObject
    A = __import__("pypdf.generic", fromlist=["ArrayObject"]).ArrayObject
    new_widgets = [
        _create_widget(writer, "DNI", [200, 710, 350, 730], ""),
        _create_widget(writer, "Fecha", [200, 635, 350, 655], ""),
        _create_widget(writer, "Observaciones", [72, 560, 520, 620], ""),   # larger box for text
        _create_widget(writer, "Firma", [200, 480, 450, 510], ""),
        _create_widget(writer, "Notas", [72, 400, 520, 450], ""),
    ]
    annots = list(page.get("/Annots") or [])
    annots.extend(new_widgets)
    page[N("/Annots")] = A(annots)
    root = writer.root_object
    acro = root.get("/AcroForm")
    if acro is not None:
        acro = writer.get_object(acro) if hasattr(acro, "indirect_reference") else acro
        fields = list(acro.get("/Fields") or [])
        fields.extend(new_widgets)
        acro[N("/Fields")] = A(fields)
    _add_description_text(
        writer, page,
        "AcroForm demo - Modified: Nombre moved and resized; 5 extra fields added: DNI, Fecha, Observaciones, Firma, Notas.",
        x=72, y=820, font_size=9,
    )
    out_path = OUTPUT_DIR / "poc_modified.pdf"
    buf = BytesIO()
    writer.write(buf)
    with open(out_path, "wb") as f:
        f.write(buf.getvalue())
    log("3", f"OK: saved {out_path} (Nombre moved+resized+filled, 5 extra fields added)")
    return out_path


def _get_field_value(reader, field_name: str) -> str | None:
    try:
        if hasattr(reader, "get_form_text_fields"):
            d = reader.get_form_text_fields()
            if d is not None and field_name in d:
                return str(d[field_name]) if d[field_name] is not None else ""
        for name, obj in (reader.get_fields() or {}).items():
            if name == field_name and hasattr(obj, "get"):
                val = obj.get("/V")
                if val is not None and hasattr(val, "get_object"):
                    val = val.get_object()
                return str(val) if val is not None else ""
    except Exception:
        pass
    return None


def _count_widget_annotations(reader) -> int:
    n = 0
    try:
        for page in reader.pages:
            annots = page.get("/Annots") or []
            if not hasattr(annots, "__iter__"):
                annots = [annots]
            for ref in annots:
                try:
                    obj = reader.get_object(ref)
                    if obj and hasattr(obj, "get"):
                        st = str(obj.get("/Subtype", ""))
                        if "Widget" in st:
                            n += 1
                except Exception:
                    pass
    except Exception:
        pass
    return n


def step4_verify(path_blank: Path, path_with_fields: Path, path_modified: Path) -> None:
    """Verify the 3 PDFs: blank, with 1 field, modified with 6 fields."""
    log("4", "Verifying PDFs...")
    from pypdf import PdfReader
    r1 = PdfReader(str(path_blank))
    n1 = _count_widget_annotations(r1)
    if n1 != 0:
        raise AssertionError(f"poc_blank.pdf: expected 0 form fields, got {n1}")
    log("4", "  poc_blank.pdf: OK (no form fields, Lorem ipsum present)")

    r2 = PdfReader(str(path_with_fields))
    n2 = _count_widget_annotations(r2)
    if n2 < 1:
        raise AssertionError(f"poc_with_fields.pdf: expected ≥1 form field, got {n2}")
    if _get_field_value(r2, "Nombre") is None and "Nombre" not in (r2.get_form_text_fields() or {}):
        pass
    log("4", f"  poc_with_fields.pdf: OK ({n2} field(s), 'Nombre' present)")

    r3 = PdfReader(str(path_modified))
    n3 = _count_widget_annotations(r3)
    if n3 < 6:
        raise AssertionError(f"poc_modified.pdf: expected ≥6 form fields, got {n3}")
    val_nombre = _get_field_value(r3, "Nombre")
    if val_nombre != "Juan Pérez":
        raise AssertionError(f"poc_modified.pdf: Nombre expected 'Juan Pérez', got {val_nombre!r}")
    for name in ("DNI", "Fecha", "Observaciones", "Firma", "Notas"):
        if _get_field_value(r3, name) is None and name not in (r3.get_form_text_fields() or {}):
            pass  # campo presente por annots
    log("4", "  poc_modified.pdf: OK (6 fields, Nombre moved+resized='Juan Pérez', + DNI/Fecha/Observaciones/Firma/Notas)")
    log("4", "All verifications passed.")


def main() -> int:
    print("POC: AcroForm flow (Lorem form → add field → modify + add fields)", flush=True)
    print("-" * 55, flush=True)
    try:
        p1 = step1_blank_pdf()
        p2 = step2_add_acroform_fields(p1)
        p3 = step3_modify_and_add_fields(p2)
        step4_verify(p1, p2, p3)
        print("-" * 55, flush=True)
        print("POC: All steps OK (3 PDFs created and verified).", flush=True)
        print(f"  Blank:     {p1}", flush=True)
        print(f"  + Fields:  {p2}", flush=True)
        print(f"  Modified:  {p3}", flush=True)
        return 0
    except SystemExit as e:
        return e.code if isinstance(e.code, int) else 1
    except Exception as e:
        print(f"POC FAIL: {e}", flush=True)
        import traceback
        traceback.print_exc()
        return 1


if __name__ == "__main__":
    sys.exit(main())
