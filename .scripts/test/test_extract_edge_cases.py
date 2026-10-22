"""Edge cases of extract_acroform_fields.extract_fields (REQ-TEST-004): skipped annotations,
malformed rectangles and numbers, unnamed and duplicated fields."""
from __future__ import annotations

import sys
from pathlib import Path

from pypdf import PdfWriter
from pypdf.generic import ArrayObject, DictionaryObject, NameObject, NumberObject, TextStringObject

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from extract_acroform_fields import extract_fields, parse_font_size_from_da  # noqa: E402

N = NameObject


def _annot(writer: PdfWriter, entries: dict) -> object:
    annot = DictionaryObject({N(k): v for k, v in entries.items()})
    writer._objects.append(annot)
    return writer.get_reference(annot)


def _pdf(tmp_path: Path, annots: list) -> Path:
    writer = PdfWriter()
    writer.add_blank_page(width=200, height=200)
    writer.add_blank_page(width=200, height=200)
    writer.pages[0][N("/Annots")] = ArrayObject(annots(writer))
    path = tmp_path / "edge.pdf"
    with path.open("wb") as handle:
        writer.write(handle)
    return path


def rect(*values: object) -> ArrayObject:
    return ArrayObject([v if not isinstance(v, (int, float)) else NumberObject(v) for v in values])


def test_skips_non_widgets_and_malformed_entries_and_parses_numbers(tmp_path: Path) -> None:
    def annots(writer: PdfWriter) -> list:
        return [
            _annot(writer, {"/Subtype": N("/Link"), "/Rect": rect(0, 0, 10, 10)}),
            _annot(writer, {"/Rect": rect(0, 0, 10, 10)}),
            _annot(writer, {"/Subtype": N("/Widget")}),
            _annot(writer, {"/Subtype": N("/Widget"), "/Rect": rect(0, 0, 10)}),
            _annot(writer, {"/Subtype": N("/Widget"), "/Rect": rect(TextStringObject("x"), 0, 10, 10)}),
            _annot(writer, {"/Subtype": N("/Widget"), "/Rect": rect(10, 10, 60, 30), "/MaxLen": TextStringObject("ten"), "/F": TextStringObject("bad"), "/DA": TextStringObject("/Helv 9 Tf")}),
            _annot(writer, {"/Subtype": N("/Widget"), "/Rect": rect(10, 40, 60, 60), "/T": TextStringObject("code"), "/MaxLen": NumberObject(6), "/F": NumberObject(4)}),
            _annot(writer, {"/Subtype": N("/Widget"), "/Rect": rect(10, 70, 60, 90), "/T": TextStringObject("code")}),
        ]

    fields = extract_fields(_pdf(tmp_path, annots))

    assert [f["id"] for f in fields] == ["p1-5", "code", "code@1-7"]
    unnamed, named, duplicate = fields
    assert unnamed["maxLen"] is None and unnamed["flags"] is None and unnamed["fontSize"] == 9.0
    assert named["maxLen"] == 6 and named["flags"] == 4 and named["fieldType"] == "Tx"
    assert duplicate["fieldName"] == "code"


def test_parse_font_size_without_size_pattern() -> None:
    assert parse_font_size_from_da("0 g") is None
    assert parse_font_size_from_da(None) is None
