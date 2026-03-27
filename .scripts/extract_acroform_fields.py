#!/usr/bin/env python3
"""Extract AcroForm field metadata from a PDF file and output JSON.

Output: JSON array of field descriptors (id, rect, width, height, fieldType,
value, page, fontSize, maxLen, fieldName, flags, subtype) for use by the
PdfSignableBundle backend (e.g. POST /acroform/fields/extract).

Usage:
  python extract_acroform_fields.py <path-to-pdf>
  python extract_acroform_fields.py --stdin   # read base64 PDF from stdin (one line)

Requires: pypdf (pip install pypdf). Python 3.9+.
"""
from __future__ import annotations

import json
import re
import sys
from pathlib import Path


def _resolve(obj, reader):
    """Resolve indirect references using the reader.

    If obj is an IndirectObject, returns the dereferenced object; otherwise returns obj unchanged.
    """
    try:
        from pypdf.generic import IndirectObject
        if isinstance(obj, IndirectObject):
            return reader.get_object(obj)
    except Exception:
        pass
    return obj


def _str_val(obj, reader):
    """Get string value from a PDF string/name object, resolving indirect refs if needed.

    Handles bytes (decoded as UTF-8), str, and NameObject; strips leading slash from names.
    """
    obj = _resolve(obj, reader)
    if obj is None:
        return ""
    if isinstance(obj, bytes):
        return obj.decode("utf-8", errors="replace")
    if isinstance(obj, str):
        return obj
    s = getattr(obj, "get_object", None)
    if s is not None:
        try:
            return _str_val(s(), reader)
        except Exception:
            pass
    return str(obj).replace("/", "").strip()


def parse_font_size_from_da(da):
    """Parse default appearance string (DA) for font size.

    E.g. "0 0 0 rg /Helv 12 Tf" or "/Helvetica 10 Tf" -> 12 or 10.
    Returns None if da is missing or no /FontName size Tf pattern is found.
    """
    if not da or not isinstance(da, str):
        return None
    # Match /FontName size Tf
    m = re.search(r'/[A-Za-z0-9+_-]+\s+([\d.]+)\s+Tf', da)
    if m:
        try:
            return float(m.group(1))
        except ValueError:
            pass
    return None


def _get_inheritable(obj, key, reader):
    """Get a key from obj or from its /Parent (one level only).

    Used for field-level keys that may be inherited from the parent field dictionary.
    """
    val = obj.get(key)
    if val is not None:
        return _resolve(val, reader)
    parent = obj.get("/Parent")
    if parent is not None:
        p = _resolve(parent, reader)
        if p is not None:
            return p.get(key)
    return None


def extract_fields(pdf_path: str | Path) -> list[dict]:
    """Extract AcroForm/Widget field descriptors from a PDF file.

    Iterates over all pages and Widget annotations; for each, reads rect, type (/FT),
    name (/T), value (/V), maxLen, DA (for font size), and flags. Returns a list of
    dicts suitable for JSON (id, rect, width, height, fieldType, value, page, etc.).
    Field ids are deduplicated by appending @page-idx when the name is repeated.

    Args:
        pdf_path: Path to the PDF file (or Path object).

    Returns:
        List of field descriptor dicts (id, rect, width, height, fieldType, value,
        page, subtype, fieldName, fontSize, maxLen, flags).

    Raises:
        SystemExit: If pypdf is not installed.
    """
    try:
        from pypdf import PdfReader
    except ImportError:
        raise SystemExit("Requires pypdf. Install with: pip install pypdf")

    reader = PdfReader(str(pdf_path))
    fields_out = []
    seen_ids = set()

    for page_num, page in enumerate(reader.pages, start=1):
        annots = page.get("/Annots")
        if annots is None:
            continue
        if not hasattr(annots, "__iter__"):
            annots = [annots]
        for idx, ref in enumerate(annots):
            annot = _resolve(ref, reader)
            if annot is None:
                continue
            # Only process Widget annotations (form fields)
            subtype = annot.get("/Subtype")
            subtype = _resolve(subtype, reader)
            if subtype is None:
                continue
            st = _str_val(subtype, reader)
            if st != "Widget":
                continue

            rect = annot.get("/Rect")
            rect = _resolve(rect, reader)
            if rect is None or not hasattr(rect, "__getitem__") or len(rect) < 4:
                continue
            try:
                llx, lly, urx, ury = float(rect[0]), float(rect[1]), float(rect[2]), float(rect[3])
            except (TypeError, ValueError):
                continue
            width = max(0, urx - llx)
            height = max(0, ury - lly)

            # Field type/name/value may be on the widget or on the parent field dict
            parent = annot.get("/Parent")
            field_dict = annot
            if parent is not None:
                p = _resolve(parent, reader)
                if p is not None:
                    field_dict = p

            ft = _get_inheritable(field_dict, "/FT", reader)
            field_type = _str_val(ft, reader) if ft is not None else "Tx"

            name = _get_inheritable(field_dict, "/T", reader)
            field_name = _str_val(name, reader) if name is not None else ""

            v = _get_inheritable(field_dict, "/V", reader)
            value = _str_val(v, reader) if v is not None else ""

            max_len = None
            m = _get_inheritable(field_dict, "/MaxLen", reader)
            if m is not None:
                try:
                    max_len = int(m)
                except (TypeError, ValueError):
                    pass

            da = annot.get("/DA") or _get_inheritable(field_dict, "/DA", reader)
            da = _resolve(da, reader) if da is not None else None
            da_str = _str_val(da, reader) if da is not None else ""
            fontSize = parse_font_size_from_da(da_str)

            flags = None
            f = _get_inheritable(field_dict, "/F", reader)
            if f is not None:
                try:
                    flags = int(f)
                except (TypeError, ValueError):
                    pass

            # Deduplicate id: use field name or p{page}-{idx}; append @page-idx if name repeated
            fid = (field_name or "").strip() or f"p{page_num}-{idx}"
            if fid in seen_ids:
                fid = f"{fid}@{page_num}-{idx}"
            seen_ids.add(fid)

            fields_out.append({
                "id": fid,
                "rect": [llx, lly, urx, ury],
                "width": round(width, 2),
                "height": round(height, 2),
                "fieldType": field_type or "Tx",
                "value": value,
                "page": page_num,
                "subtype": "Widget",
                "fieldName": field_name,
                "fontSize": fontSize,
                "maxLen": max_len,
                "flags": flags,
            })

    return fields_out


def main() -> None:
    """Entry point: parse args, load PDF (file or base64 from stdin), print JSON array to stdout."""
    if len(sys.argv) < 2:
        print("Usage: extract_acroform_fields.py <path-to-pdf>", file=sys.stderr)
        print("   or: extract_acroform_fields.py --stdin  # base64 PDF from stdin", file=sys.stderr)
        sys.exit(1)

    if sys.argv[1] == "--stdin":
        import base64
        import tempfile
        data = sys.stdin.buffer.read()
        try:
            raw = base64.b64decode(data, validate=True)
        except Exception as e:
            print(json.dumps({"error": f"Invalid base64: {e}"}), file=sys.stderr)
            sys.exit(2)
        with tempfile.NamedTemporaryFile(suffix=".pdf", delete=False) as f:
            f.write(raw)
            path = f.name
        try:
            fields = extract_fields(path)
        finally:
            Path(path).unlink(missing_ok=True)
    else:
        path = Path(sys.argv[1])
        if not path.is_file():
            print(json.dumps({"error": f"File not found: {path}"}), file=sys.stderr)
            sys.exit(2)
        fields = extract_fields(path)

    print(json.dumps(fields, ensure_ascii=False))


if __name__ == "__main__":
    main()
