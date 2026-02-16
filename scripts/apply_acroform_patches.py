#!/usr/bin/env python3
"""Apply AcroForm patches to a PDF and output the modified PDF to stdout.

Reads PDF and a JSON file of patches (array of { fieldId, fieldName?, rect?, defaultValue?, hidden?,
label?, fieldType?, controlType?, options?, maxLen?, page?, createIfMissing?, fontSize?, fontFamily?, ... }).
Applies per field: rect, defaultValue (/V, /DV), hidden (remove widget), label (/TU),
fieldType (/FT), options (/Opt for choice), maxLen (/MaxLen), default appearance (/DA) when fontSize or fontFamily.
Matches by: (page, idx) for "p1-0", by fieldName (/T), or by fieldId.
If createIfMissing and no match, creates a new Widget at rect (page required).

Usage:
  python apply_acroform_patches.py --pdf input.pdf --patches patches.json > output.pdf
  python apply_acroform_patches.py --pdf input.pdf --patches patches.json --dry-run  # stdout: JSON validation result

Requires: pypdf (pip install pypdf). Python 3.9+.
"""
from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path


def _resolve(obj, reader):
    """Resolve indirect references using the reader.

    If obj is an IndirectObject, returns the dereferenced object; otherwise returns obj.
    """
    try:
        from pypdf.generic import IndirectObject
        if isinstance(obj, IndirectObject):
            return reader.get_object(obj)
    except Exception:
        pass
    return obj


def _get_inheritable(obj, key, reader):
    """Get a key from obj or from its /Parent (one level). Resolves indirect refs."""
    val = obj.get(key)
    if val is not None:
        return _resolve(val, reader)
    parent = obj.get("/Parent")
    if parent is not None:
        p = _resolve(parent, reader)
        if p is not None:
            return p.get(key)
    return None


def _pdf_font_name(font_family: str | None) -> str:
    """Map CSS/common font family names to PDF base font name (no leading slash).
    Uses only the 14 standard PDF fonts so no embedding is required.
    """
    if not font_family or not isinstance(font_family, str):
        return "Helvetica"
    name = font_family.strip().lower()
    if name in ("times", "times new roman", "times-new-roman", "serif"):
        return "Times-Roman"
    if name in ("times bold", "times-new-roman bold"):
        return "Times-Bold"
    if name in ("courier", "courier new", "monospace"):
        return "Courier"
    if name in ("courier bold", "courier new bold"):
        return "Courier-Bold"
    if name in ("helvetica", "arial", "sans-serif", "sans serif"):
        return "Helvetica"
    if name in ("helvetica bold", "arial bold"):
        return "Helvetica-Bold"
    # Default and any unknown -> Helvetica
    return "Helvetica"


def _build_da_string(font_size: float, font_family: str | None) -> str:
    """Build PDF default appearance string: 0 0 0 rg /FontName size Tf (black text)."""
    pdf_font = _pdf_font_name(font_family)
    size = max(1, min(999, float(font_size)))
    return f"0 0 0 rg /{pdf_font} {size:.1f} Tf"


def _patch_field_type(patch: dict) -> str | None:
    """Map patch fieldType or controlType to PDF /FT value: /Tx, /Btn, /Ch."""
    ft = patch.get("fieldType") or patch.get("field_type")
    if ft is not None:
        s = str(ft).strip().lower()
        if s in ("tx", "text"):
            return "/Tx"
        if s in ("btn", "button", "checkbox"):
            return "/Btn"
        if s in ("ch", "choice", "select"):
            return "/Ch"
        if s in ("sig", "signature"):
            return "/Sig"
        if ft in ("/Tx", "/Btn", "/Ch", "/Sig"):
            return ft if ft.startswith("/") else f"/{ft}"
    control = patch.get("controlType") or patch.get("control_type")
    if control is not None:
        c = str(control).strip().lower()
        if c in ("text", "textarea"):
            return "/Tx"
        if c == "checkbox":
            return "/Btn"
        if c in ("select", "choice"):
            return "/Ch"
    return None


def apply_patches(pdf_path: str | Path, patches_path: str | Path) -> bytes:
    """Apply AcroForm patches to a PDF and return the modified PDF as bytes.

    Reads the patches JSON (array of dicts with fieldId, rect?, defaultValue?, hidden?, etc.).
    Matches patches to annotations by (page, index) for ids like "p1-0", or by field name (/T).
    Applies: rect update, /V and /DV for default value, and removes widget when hidden is True.
    Writes the result to an in-memory buffer and returns its value.

    Args:
        pdf_path: Path to the input PDF file.
        patches_path: Path to the JSON file containing the patches array.

    Returns:
        Modified PDF file as raw bytes (suitable for stdout or HTTP response).

    Raises:
        SystemExit: If pypdf is not installed.
    """
    try:
        from pypdf import PdfReader, PdfWriter
        from pypdf.generic import (
            ArrayObject,
            FloatObject,
            NameObject,
            TextStringObject,
        )
        try:
            from pypdf.generic import NumberObject
        except ImportError:
            from pypdf.generic import IntegerObject as NumberObject  # pypdf < 6
    except ImportError as e:
        import os
        import sys as _sys
        _detail = f" sys.path[0]={_sys.path[0]!r} PYTHONPATH={os.environ.get('PYTHONPATH', '')!r}"
        raise SystemExit(f"Requires pypdf. Install with: pip install pypdf. Debug: {e!r}{_detail}") from e

    with open(patches_path, encoding="utf-8") as f:
        patches = json.load(f)
    if not isinstance(patches, list):
        patches = []

    reader = PdfReader(str(pdf_path))
    writer = PdfWriter()
    writer.append(reader)

    # Index patches by (page_num, annot_index) for "pN-idx" ids and "X@N-idx", and by field name (/T) for names
    patches_by_page_idx: dict[tuple[int, int], dict] = {}
    patches_by_name: dict[str, dict] = {}
    # Per-page field name -> value for update_page_form_field_values (updates /AP for visibility)
    page_field_values: dict[int, dict[str, str]] = {}
    for p in patches:
        fid = p.get("fieldId") or p.get("field_id") or ""
        if not fid:
            continue
        fid = str(fid)
        if fid.startswith("p") and "-" in fid and "@" not in fid:
            try:
                # e.g. "p1-0" -> page 1, annotation index 0
                parts = fid.split("-", 1)
                page_num = int(parts[0][1:])
                idx = int(parts[1])
                patches_by_page_idx[(page_num, idx)] = p
            except (ValueError, IndexError):
                patches_by_name[fid] = p
        elif "@" in fid and "-" in fid:
            try:
                # e.g. "Nombre@1-0" -> deduplicated id from extractor; match by (page, idx)
                suffix = fid.split("@", 1)[1]
                page_str, idx_str = suffix.split("-", 1)
                page_num = int(page_str)
                idx = int(idx_str)
                patches_by_page_idx[(page_num, idx)] = p
            except (ValueError, IndexError):
                patches_by_name[fid] = p
        else:
            patches_by_name[fid] = p
        # Index by fieldName too: extractor/load use ids like "509R" but PDF /T is "NOMBRE Y APELLIDOS"
        fn = p.get("fieldName") or p.get("field_name")
        if fn and (fn := str(fn).strip()) and fn != fid:
            patches_by_name[fn] = p

    applied_count = 0
    matched_patch_ids: set[str] = set()  # fieldIds of patches that were matched
    for page_num in range(1, len(reader.pages) + 1):
        page = writer.pages[page_num - 1]
        annots = page.get("/Annots")
        if annots is None:
            continue
        if not hasattr(annots, "__iter__"):
            annots = [annots]
        else:
            annots = list(annots)

        new_annots = []
        for idx, ref in enumerate(annots):
            patch = patches_by_page_idx.get((page_num, idx))
            if patch is None:
                # Try matching by field name (/T) for this annotation
                annot = _resolve(ref, writer)
                if annot is not None:
                    name = _get_inheritable(annot, "/T", writer)
                    if name is not None:
                        try:
                            name_str = name.get_object() if hasattr(name, "get_object") else str(name)
                            if isinstance(name_str, bytes):
                                name_str = name_str.decode("utf-8", errors="replace")
                            patch = patches_by_name.get(name_str)
                        except Exception:
                            pass
            # Skip this annotation entirely if patch says hidden
            if patch and patch.get("hidden") is True:
                continue

            annot = _resolve(ref, writer)
            if annot is None:
                new_annots.append(ref)
                continue

            if patch:
                applied_count += 1
                matched_patch_ids.add(str(patch.get("fieldId") or patch.get("field_id") or ""))
                from pypdf.generic import NameObject as N

                parent = annot.get("/Parent")
                pobj = _resolve(parent, writer) if parent is not None else None

                # Update widget rect (llx, lly, urx, ury in PDF points)
                if "rect" in patch and isinstance(patch["rect"], (list, tuple)) and len(patch["rect"]) >= 4:
                    try:
                        rect = ArrayObject(
                            [
                                FloatObject(float(patch["rect"][0])),
                                FloatObject(float(patch["rect"][1])),
                                FloatObject(float(patch["rect"][2])),
                                FloatObject(float(patch["rect"][3])),
                            ]
                        )
                        annot[N("/Rect")] = rect
                    except (TypeError, ValueError):
                        pass

                # Label/tooltip on widget (/TU)
                if "label" in patch and patch["label"] is not None and str(patch["label"]).strip():
                    annot[N("/TU")] = TextStringObject(str(patch["label"]).strip())

                # Set current and default value on widget and parent field
                if "defaultValue" in patch:
                    val = patch["defaultValue"]
                    if val is not None:
                        val_str = str(val)
                        annot[N("/V")] = TextStringObject(val_str)
                        annot[N("/DV")] = TextStringObject(val_str)
                        if pobj is not None:
                            pobj[N("/V")] = TextStringObject(val_str)
                            pobj[N("/DV")] = TextStringObject(val_str)
                        # Collect for update_page_form_field_values (regenerates appearance stream)
                        name = _get_inheritable(annot, "/T", writer)
                        if name is not None:
                            try:
                                fn = name.get_object() if hasattr(name, "get_object") else str(name)
                                if isinstance(fn, bytes):
                                    fn = fn.decode("utf-8", errors="replace")
                                fn = str(fn).strip()
                                if fn:
                                    if page_num not in page_field_values:
                                        page_field_values[page_num] = {}
                                    page_field_values[page_num][fn] = val_str
                            except Exception:
                                pass

                # Field type (/FT) on parent: Tx, Btn, Ch
                if pobj is not None:
                    ft = _patch_field_type(patch)
                    if ft is not None:
                        pobj[N("/FT")] = N(ft)

                    # Max length for text fields (/MaxLen)
                    if "maxLen" in patch and patch["maxLen"] is not None:
                        try:
                            pobj[N("/MaxLen")] = NumberObject(int(patch["maxLen"]))
                        except (TypeError, ValueError):
                            pass

                    # Options for choice fields (/Opt): list of strings or [[export, display], ...]
                    if "options" in patch and isinstance(patch["options"], list) and len(patch["options"]) > 0:
                        opt_list = []
                        for item in patch["options"]:
                            if isinstance(item, dict):
                                v = item.get("value", "")
                                lbl = item.get("label")
                                v = str(v) if v is not None else ""
                                if lbl is not None and str(lbl).strip() != v:
                                    opt_list.append(ArrayObject([TextStringObject(v), TextStringObject(str(lbl))]))
                                else:
                                    opt_list.append(TextStringObject(v))
                            elif isinstance(item, str):
                                opt_list.append(TextStringObject(item))
                            else:
                                opt_list.append(TextStringObject(str(item)))
                        if opt_list:
                            pobj[N("/Opt")] = ArrayObject(opt_list)

                # Default appearance (/DA) for text: font and size (widget-level)
                if "fontSize" in patch or "fontFamily" in patch:
                    try:
                        size = float(patch.get("fontSize") or patch.get("font_size") or 11)
                        family = patch.get("fontFamily") or patch.get("font_family")
                        da_str = _build_da_string(size, family)
                        annot[N("/DA")] = TextStringObject(da_str)
                    except (TypeError, ValueError):
                        pass

            new_annots.append(ref)

        # If we removed any annotations (hidden), update the page's /Annots array
        if len(new_annots) != len(annots):
            from pypdf.generic import ArrayObject as Arr, NameObject as N
            page[N("/Annots")] = Arr(new_annots)

    # Create new Widgets for unmatched patches with createIfMissing or fieldId starting with "new-" (add-field from editor)
    for p in patches:
        fid = str(p.get("fieldId") or p.get("field_id") or "")
        if fid in matched_patch_ids:
            continue
        create_new = (
            p.get("createIfMissing") is True
            or (isinstance(p.get("createIfMissing"), str) and str(p.get("createIfMissing")).lower() in ("true", "1"))
            or p.get("create_if_missing") is True
        )
        if not create_new and not fid.startswith("new-"):
            continue
        rect_data = p.get("rect")
        if not isinstance(rect_data, (list, tuple)) or len(rect_data) < 4:
            continue
        name = (p.get("fieldName") or p.get("field_name") or fid or "Field").strip()
        page_num = int(p.get("page", 1))
        if page_num < 1 or page_num > len(writer.pages):
            continue
        try:
            from pypdf.generic import BooleanObject, DictionaryObject, NameObject as N, TextStringObject

            rect = ArrayObject([FloatObject(float(rect_data[0])), FloatObject(float(rect_data[1])),
                               FloatObject(float(rect_data[2])), FloatObject(float(rect_data[3]))])
            val = str(p.get("defaultValue", "")) if p.get("defaultValue") is not None else ""
            widget = DictionaryObject({
                N("/Subtype"): N("/Widget"),
                N("/Rect"): rect,
                N("/T"): TextStringObject(name),
                N("/FT"): N(_patch_field_type(p) or "/Tx"),
                N("/V"): TextStringObject(val),
                N("/DV"): TextStringObject(val),
            })
            if "fontSize" in p or "fontFamily" in p:
                try:
                    size = float(p.get("fontSize") or p.get("font_size") or 11)
                    family = p.get("fontFamily") or p.get("font_family")
                    widget[N("/DA")] = TextStringObject(_build_da_string(size, family))
                except (TypeError, ValueError):
                    pass
            writer._objects.append(widget)
            ref = writer.get_reference(widget)
            page = writer.pages[page_num - 1]
            annots = list(page.get("/Annots") or [])
            annots.append(ref)
            page[N("/Annots")] = ArrayObject(annots)
            root = writer.root_object
            acro = root.get("/AcroForm")
            if acro is not None:
                acro = writer.get_object(acro) if hasattr(acro, "indirect_reference") else acro
                fields = list(acro.get("/Fields") or [])
                fields.append(ref)
                acro[N("/Fields")] = ArrayObject(fields)
            else:
                acro = {N("/Fields"): ArrayObject([ref]), N("/NeedAppearances"): BooleanObject(True)}
                root[N("/AcroForm")] = acro
            applied_count += 1
        except (TypeError, ValueError, KeyError):
            pass

    # Use pypdf's form API to update appearance streams (visible in PDF.js and other viewers)
    for page_num, fields_dict in page_field_values.items():
        if fields_dict and page_num <= len(writer.pages):
            try:
                writer.update_page_form_field_values(
                    writer.pages[page_num - 1],
                    fields_dict,
                    auto_regenerate=True,
                )
            except Exception:
                pass
    # Ensure NeedAppearances is set so readers regenerate if update_page_form_field_values didn't
    writer.set_need_appearances_writer(True)
    buf = __import__("io").BytesIO()
    writer.write(buf)
    out = buf.getvalue()
    # Debug: one line to stderr (PHP listener logs it when script succeeds)
    print(
        f"[apply_acroform] patches={len(patches)} matched={applied_count} output_bytes={len(out)}",
        file=sys.stderr,
    )
    return out


def main() -> None:
    """Entry point: parse --pdf and --patches, apply patches. With --dry-run output JSON to stdout; else output PDF."""
    ap = argparse.ArgumentParser(description="Apply AcroForm patches to a PDF")
    ap.add_argument("--pdf", required=True, help="Path to input PDF")
    ap.add_argument("--patches", required=True, help="Path to JSON patches file")
    ap.add_argument("--dry-run", action="store_true", help="Validate only: run apply in memory, output JSON result to stdout")
    args = ap.parse_args()
    try:
        out = apply_patches(args.pdf, args.patches)
        if args.dry_run:
            with open(args.patches, encoding="utf-8") as f:
                patches_list = json.load(f)
            patches_count = len(patches_list) if isinstance(patches_list, list) else 0
            result = {
                "success": True,
                "message": "Apply would succeed",
                "patches_count": patches_count,
            }
            sys.stdout.write(json.dumps(result, ensure_ascii=False) + "\n")
        else:
            # Binary PDF to stdout so Symfony Process can capture and return it (no output file).
            sys.stdout.buffer.write(out)
            sys.stdout.buffer.flush()
    except Exception as e:  # noqa: BLE001
        if args.dry_run:
            result = {"success": False, "error": str(e)}
            sys.stdout.write(json.dumps(result, ensure_ascii=False) + "\n")
            sys.exit(0)
        raise


if __name__ == "__main__":
    main()
