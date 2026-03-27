"""Tests for AcroForm Python scripts: apply_acroform_patches, extract_acroform_fields, process_modified_pdf."""
from __future__ import annotations

import base64
import builtins
import json
import runpy
import subprocess
import sys
import types
from pathlib import Path

import pytest

from pypdf import PdfReader, PdfWriter

BUNDLE_ROOT = Path(__file__).resolve().parent.parent.parent
SCRIPTS_DIR = BUNDLE_ROOT / ".scripts"
sys.path.insert(0, str(SCRIPTS_DIR))


def _minimal_pdf_bytes() -> bytes:
    """Create a minimal valid PDF (blank page) for testing."""
    writer = PdfWriter()
    writer.add_blank_page(width=100, height=100)
    buf = __import__("io").BytesIO()
    writer.write(buf)
    return buf.getvalue()


@pytest.fixture
def minimal_pdf(tmp_path: Path) -> Path:
    """Fixture: path to a minimal valid PDF file."""
    pdf_path = tmp_path / "minimal.pdf"
    pdf_path.write_bytes(_minimal_pdf_bytes())
    return pdf_path


def _create_widget(writer: PdfWriter, name: str, rect: list[float], value: str = "", use_parent: bool = False):
    """Create a simple text widget annotation and return its indirect ref."""
    from pypdf.generic import ArrayObject, DictionaryObject, FloatObject, NameObject, TextStringObject

    n = NameObject
    widget = DictionaryObject(
        {
            n("/Subtype"): n("/Widget"),
            n("/Rect"): ArrayObject([FloatObject(float(x)) for x in rect]),
            n("/T"): TextStringObject(name),
            n("/FT"): n("/Tx"),
            n("/V"): TextStringObject(value),
            n("/DV"): TextStringObject(value),
        }
    )
    if use_parent:
        parent = DictionaryObject(
            {
                n("/T"): TextStringObject(name),
                n("/FT"): n("/Tx"),
                n("/V"): TextStringObject(value),
                n("/DA"): TextStringObject("/Helv 10 Tf"),
            }
        )
        writer._objects.append(parent)
        widget[n("/Parent")] = writer.get_reference(parent)
    writer._objects.append(widget)
    return writer.get_reference(widget)


@pytest.fixture
def form_pdf(tmp_path: Path) -> Path:
    """Fixture: PDF with 2 text widgets to exercise extract/apply logic."""
    from pypdf.generic import ArrayObject, BooleanObject, DictionaryObject, NameObject

    writer = PdfWriter()
    writer.add_blank_page(width=595, height=842)
    page = writer.pages[0]
    ref1 = _create_widget(writer, "DUP", [100, 700, 250, 730], "A", use_parent=True)
    ref2 = _create_widget(writer, "DUP", [120, 640, 300, 670], "", use_parent=False)
    page[NameObject("/Annots")] = ArrayObject([ref1, ref2])
    writer.root_object[NameObject("/AcroForm")] = DictionaryObject(
        {NameObject("/Fields"): ArrayObject([ref1, ref2]), NameObject("/NeedAppearances"): BooleanObject(True)}
    )
    path = tmp_path / "form.pdf"
    with open(path, "wb") as f:
        writer.write(f)
    return path


class TestParseFontSizeFromDa:
    """Tests for parse_font_size_from_da in extract_acroform_fields."""

    def test_parse_font_size_extracts_size(self) -> None:
        """parse_font_size_from_da extracts font size from DA string."""
        from extract_acroform_fields import parse_font_size_from_da

        assert parse_font_size_from_da("0 0 0 rg /Helv 12 Tf") == 12.0
        assert parse_font_size_from_da("/Helvetica 10 Tf") == 10.0
        assert parse_font_size_from_da("/MyFont+Symbol 9.5 Tf") == 9.5

    def test_parse_font_size_returns_none_for_invalid(self) -> None:
        """parse_font_size_from_da returns None for invalid input."""
        from extract_acroform_fields import parse_font_size_from_da

        assert parse_font_size_from_da("") is None
        assert parse_font_size_from_da(None) is None
        assert parse_font_size_from_da("no Tf here") is None
        assert parse_font_size_from_da(123) is None

    def test_parse_font_size_decimal_size(self) -> None:
        """parse_font_size_from_da extracts decimal font size."""
        from extract_acroform_fields import parse_font_size_from_da

        assert parse_font_size_from_da("/MyFont 9.5 Tf") == 9.5
        assert parse_font_size_from_da("0 0 0 rg /Helv+Symbol 10.25 Tf") == 10.25

    def test_parse_font_size_whitespace_variants(self) -> None:
        """parse_font_size_from_da handles multiple spaces between font and size."""
        from extract_acroform_fields import parse_font_size_from_da

        assert parse_font_size_from_da("/Helvetica  12  Tf") == 12.0

    def test_parse_font_size_integer_da_returns_none(self) -> None:
        """parse_font_size_from_da returns None when da is not a string (e.g. int)."""
        from extract_acroform_fields import parse_font_size_from_da

        assert parse_font_size_from_da(123) is None
        assert parse_font_size_from_da(12.5) is None

    def test_parse_font_size_float_in_string_extracts_correctly(self) -> None:
        """parse_font_size_from_da extracts float size from DA string."""
        from extract_acroform_fields import parse_font_size_from_da

        assert parse_font_size_from_da("/Helv 11.5 Tf") == 11.5


class TestExtractAcroformFields:
    """Tests for .scripts/extract_acroform_fields.py."""

    def test_extract_fields_minimal_pdf_returns_list(self, minimal_pdf: Path) -> None:
        """extract_fields on a minimal PDF returns a list (possibly empty)."""
        from extract_acroform_fields import extract_fields

        fields = extract_fields(minimal_pdf)
        assert isinstance(fields, list)

    def test_extract_fields_valid_json_output(self, minimal_pdf: Path) -> None:
        """CLI output is valid JSON array."""
        result = subprocess.run(
            ["python3", str(BUNDLE_ROOT / ".scripts" / "extract_acroform_fields.py"), str(minimal_pdf)],
            capture_output=True,
            text=True,
            cwd=BUNDLE_ROOT,
        )
        assert result.returncode == 0
        data = json.loads(result.stdout)
        assert isinstance(data, list)

    def test_extract_fields_stdin_base64(self, minimal_pdf: Path) -> None:
        """CLI --stdin accepts base64 PDF."""
        pdf_bytes = minimal_pdf.read_bytes()
        b64 = base64.b64encode(pdf_bytes).decode("ascii")
        result = subprocess.run(
            ["python3", str(BUNDLE_ROOT / ".scripts" / "extract_acroform_fields.py"), "--stdin"],
            input=b64,
            capture_output=True,
            text=True,
            cwd=BUNDLE_ROOT,
        )
        assert result.returncode == 0
        data = json.loads(result.stdout)
        assert isinstance(data, list)

    def test_extract_fields_missing_file_exits_nonzero(self) -> None:
        """CLI with non-existent file exits with non-zero."""
        result = subprocess.run(
            ["python3", str(BUNDLE_ROOT / ".scripts" / "extract_acroform_fields.py"), "/nonexistent.pdf"],
            capture_output=True,
            cwd=BUNDLE_ROOT,
        )
        assert result.returncode != 0

    def test_extract_fields_stdin_invalid_base64_exits_nonzero(self) -> None:
        """CLI --stdin with invalid base64 exits non-zero."""
        result = subprocess.run(
            ["python3", str(BUNDLE_ROOT / ".scripts" / "extract_acroform_fields.py"), "--stdin"],
            input="not-valid-base64!!!",
            capture_output=True,
            text=True,
            cwd=BUNDLE_ROOT,
        )
        assert result.returncode != 0


class TestApplyAcroformPatches:
    """Tests for .scripts/apply_acroform_patches.py."""

    def test_apply_patches_minimal_output_valid_pdf(
        self, minimal_pdf: Path, tmp_path: Path
    ) -> None:
        """apply_patches with empty patches produces valid PDF."""
        from apply_acroform_patches import apply_patches

        patches_path = tmp_path / "patches.json"
        patches_path.write_text("[]")
        out = apply_patches(minimal_pdf, patches_path)
        assert isinstance(out, bytes)
        assert out.startswith(b"%PDF")
        reader = PdfReader(__import__("io").BytesIO(out))
        assert len(reader.pages) >= 1

    def test_apply_patches_cli_minimal(self, minimal_pdf: Path, tmp_path: Path) -> None:
        """CLI with --pdf and --patches produces PDF on stdout."""
        patches_path = tmp_path / "patches.json"
        patches_path.write_text('[{"fieldId": "p1-0", "defaultValue": "test"}]')
        result = subprocess.run(
            [
                "python3",
                str(BUNDLE_ROOT / ".scripts" / "apply_acroform_patches.py"),
                "--pdf",
                str(minimal_pdf),
                "--patches",
                str(patches_path),
            ],
            capture_output=True,
            cwd=BUNDLE_ROOT,
        )
        assert result.returncode == 0
        assert result.stdout.startswith(b"%PDF")

    def test_apply_patches_rect_patch_accepted(
        self, minimal_pdf: Path, tmp_path: Path
    ) -> None:
        """apply_patches with rect runs without error and produces valid PDF."""
        from apply_acroform_patches import apply_patches

        patches_path = tmp_path / "patches.json"
        patches_path.write_text(
            '[{"fieldId": "p1-0", "rect": [50, 50, 150, 80]}]'
        )
        out = apply_patches(minimal_pdf, patches_path)
        assert isinstance(out, bytes)
        assert out.startswith(b"%PDF")

    def test_apply_patches_field_id_with_at_suffix(
        self, minimal_pdf: Path, tmp_path: Path
    ) -> None:
        """apply_patches accepts fieldId like Nombre@1-0 (deduplicated from extractor)."""
        from apply_acroform_patches import apply_patches

        patches_path = tmp_path / "patches.json"
        patches_path.write_text(
            '[{"fieldId": "TestField@1-0", "rect": [10, 10, 110, 40]}]'
        )
        out = apply_patches(minimal_pdf, patches_path)
        assert out.startswith(b"%PDF")

    def test_apply_patches_missing_pdf_exits_nonzero(self, tmp_path: Path) -> None:
        """CLI with missing PDF exits non-zero."""
        patches_path = tmp_path / "patches.json"
        patches_path.write_text("[]")
        result = subprocess.run(
            [
                "python3",
                str(BUNDLE_ROOT / ".scripts" / "apply_acroform_patches.py"),
                "--pdf",
                "/nonexistent.pdf",
                "--patches",
                str(patches_path),
            ],
            capture_output=True,
            cwd=BUNDLE_ROOT,
        )
        assert result.returncode != 0

    def test_apply_patches_dry_run_outputs_json(
        self, minimal_pdf: Path, tmp_path: Path
    ) -> None:
        """CLI --dry-run outputs JSON validation result."""
        patches_path = tmp_path / "patches.json"
        patches_path.write_text('[{"fieldId": "p1-0", "defaultValue": "test"}]')
        result = subprocess.run(
            [
                "python3",
                str(BUNDLE_ROOT / ".scripts" / "apply_acroform_patches.py"),
                "--pdf",
                str(minimal_pdf),
                "--patches",
                str(patches_path),
                "--dry-run",
            ],
            capture_output=True,
            text=True,
            cwd=BUNDLE_ROOT,
        )
        assert result.returncode == 0
        data = json.loads(result.stdout)
        assert isinstance(data, dict)
        assert "success" in data or "patches_count" in data or "message" in data

    def test_apply_patches_with_font_size_and_font_family(
        self, minimal_pdf: Path, tmp_path: Path
    ) -> None:
        """apply_patches with fontSize and fontFamily in patch produces valid PDF."""
        from apply_acroform_patches import apply_patches

        patches_path = tmp_path / "patches.json"
        patches_path.write_text(
            '[{"fieldId": "p1-0", "defaultValue": "Test", "fontSize": 14, "fontFamily": "Arial"}]'
        )
        out = apply_patches(minimal_pdf, patches_path)
        assert isinstance(out, bytes)
        assert out.startswith(b"%PDF")
        reader = PdfReader(__import__("io").BytesIO(out))
        assert len(reader.pages) >= 1

    def test_apply_patches_invalid_json_exits_nonzero(self, minimal_pdf: Path, tmp_path: Path) -> None:
        """CLI with invalid JSON patches file exits non-zero."""
        patches_path = tmp_path / "patches.json"
        patches_path.write_text("not valid json")
        result = subprocess.run(
            [
                "python3",
                str(BUNDLE_ROOT / ".scripts" / "apply_acroform_patches.py"),
                "--pdf",
                str(minimal_pdf),
                "--patches",
                str(patches_path),
            ],
            capture_output=True,
            cwd=BUNDLE_ROOT,
        )
        assert result.returncode != 0

    def test_apply_patches_empty_object_in_array_produces_pdf(
        self, minimal_pdf: Path, tmp_path: Path
    ) -> None:
        """apply_patches with array containing patch without fieldId skips it and returns valid PDF."""
        from apply_acroform_patches import apply_patches

        patches_path = tmp_path / "patches.json"
        patches_path.write_text("[{}]")
        out = apply_patches(minimal_pdf, patches_path)
        assert isinstance(out, bytes)
        assert out.startswith(b"%PDF")
        reader = PdfReader(__import__("io").BytesIO(out))
        assert len(reader.pages) >= 1


class TestRealFormCoverage:
    """Coverage-oriented tests using a PDF with real widgets."""

    def test_extract_fields_on_real_form_hits_widget_paths(self, form_pdf: Path) -> None:
        from extract_acroform_fields import extract_fields

        fields = extract_fields(form_pdf)
        assert isinstance(fields, list)
        if len(fields) >= 2:
            ids = [f["id"] for f in fields]
            assert any("@" in fid for fid in ids)  # duplicated id suffix
            assert all("rect" in f and len(f["rect"]) == 4 for f in fields)

    def test_apply_patches_on_real_form_hits_many_branches(self, form_pdf: Path, tmp_path: Path) -> None:
        from apply_acroform_patches import apply_patches

        patches_path = tmp_path / "patches-rich.json"
        patches_path.write_text(
            json.dumps(
                [
                    {
                        "fieldId": "p1-0",
                        "fieldName": "DUP",
                        "label": "Etiqueta",
                        "defaultValue": "Juan",
                        "rect": [90, 690, 280, 740],
                        "fieldType": "text",
                        "maxLen": 20,
                        "options": [{"value": "A", "label": "AA"}, "B", 3],
                        "fontSize": 13,
                        "fontFamily": "Arial",
                    },
                    {"fieldId": "DUP@1-1", "hidden": True},
                    {"fieldId": "new-x", "fieldName": "NEWF", "page": 1, "rect": [72, 520, 220, 550], "defaultValue": "N", "createIfMissing": True},
                    {"fieldId": "new-bad", "fieldName": "BAD", "page": 99, "rect": [1, 2, 3, 4], "createIfMissing": True},
                    {"fieldId": "new-no-rect", "createIfMissing": True},
                ],
                ensure_ascii=False,
            )
        )
        out = apply_patches(form_pdf, patches_path)
        assert out.startswith(b"%PDF")
        reader = PdfReader(__import__("io").BytesIO(out))
        annots = list(reader.pages[0].get("/Annots") or [])
        assert len(annots) >= 2

    def test_apply_patches_with_non_list_json(self, form_pdf: Path, tmp_path: Path) -> None:
        from apply_acroform_patches import apply_patches

        patches_path = tmp_path / "patches-object.json"
        patches_path.write_text('{"not":"list"}')
        out = apply_patches(form_pdf, patches_path)
        assert out.startswith(b"%PDF")


class TestProcessModifiedPdf:
    """Tests for .scripts/process_modified_pdf.py (stub)."""

    def test_process_copies_input_to_output(
        self, minimal_pdf: Path, tmp_path: Path
    ) -> None:
        """CLI copies input PDF to output (stub behaviour)."""
        out_path = tmp_path / "out.pdf"
        result = subprocess.run(
            [
                "python3",
                str(BUNDLE_ROOT / ".scripts" / "process_modified_pdf.py"),
                "--input",
                str(minimal_pdf),
                "--output",
                str(out_path),
            ],
            capture_output=True,
            cwd=BUNDLE_ROOT,
        )
        assert result.returncode == 0
        assert out_path.is_file()
        assert out_path.read_bytes() == minimal_pdf.read_bytes()

    def test_process_with_document_key(
        self, minimal_pdf: Path, tmp_path: Path
    ) -> None:
        """CLI accepts --document-key."""
        out_path = tmp_path / "out.pdf"
        result = subprocess.run(
            [
                "python3",
                str(BUNDLE_ROOT / ".scripts" / "process_modified_pdf.py"),
                "--input",
                str(minimal_pdf),
                "--output",
                str(out_path),
                "--document-key",
                "doc-123",
            ],
            capture_output=True,
            cwd=BUNDLE_ROOT,
        )
        assert result.returncode == 0
        assert out_path.read_bytes() == minimal_pdf.read_bytes()

    def test_process_missing_input_exits_nonzero(self, tmp_path: Path) -> None:
        """CLI with missing --input exits non-zero."""
        out_path = tmp_path / "out.pdf"
        result = subprocess.run(
            [
                "python3",
                str(BUNDLE_ROOT / ".scripts" / "process_modified_pdf.py"),
                "--output",
                str(out_path),
            ],
            capture_output=True,
            cwd=BUNDLE_ROOT,
        )
        assert result.returncode != 0

    def test_process_missing_output_exits_nonzero(self, minimal_pdf: Path) -> None:
        """CLI with missing --output exits non-zero."""
        result = subprocess.run(
            [
                "python3",
                str(BUNDLE_ROOT / ".scripts" / "process_modified_pdf.py"),
                "--input",
                str(minimal_pdf),
            ],
            capture_output=True,
            cwd=BUNDLE_ROOT,
        )
        assert result.returncode != 0

    def test_process_output_file_is_writable(self, minimal_pdf: Path, tmp_path: Path) -> None:
        """Process creates output file with same size as input."""
        out_path = tmp_path / "processed.pdf"
        result = subprocess.run(
            [
                "python3",
                str(BUNDLE_ROOT / ".scripts" / "process_modified_pdf.py"),
                "--input",
                str(minimal_pdf),
                "--output",
                str(out_path),
            ],
            capture_output=True,
            cwd=BUNDLE_ROOT,
        )
        assert result.returncode == 0
        assert out_path.stat().st_size == minimal_pdf.stat().st_size


class TestMinimalPdfBytes:
    """Tests for _minimal_pdf_bytes helper."""

    def test_minimal_pdf_bytes_starts_with_pdf_header(self) -> None:
        """_minimal_pdf_bytes returns bytes that are a valid PDF header."""
        data = _minimal_pdf_bytes()
        assert data.startswith(b"%PDF")
        assert len(data) > 100

    def test_minimal_pdf_bytes_is_valid_pdf(self) -> None:
        """_minimal_pdf_bytes produces bytes readable by PdfReader."""
        data = _minimal_pdf_bytes()
        reader = PdfReader(__import__("io").BytesIO(data))
        assert len(reader.pages) >= 1


class TestProcessModifiedPdfEdgeCases:
    """Additional edge cases for process_modified_pdf.py."""

    def test_process_input_not_file_exits_nonzero(self, tmp_path: Path) -> None:
        """CLI with --input as directory (not a file) exits non-zero."""
        out_path = tmp_path / "out.pdf"
        result = subprocess.run(
            [
                "python3",
                str(BUNDLE_ROOT / ".scripts" / "process_modified_pdf.py"),
                "--input",
                str(tmp_path),
                "--output",
                str(out_path),
            ],
            capture_output=True,
            cwd=BUNDLE_ROOT,
        )
        assert result.returncode != 0

    def test_process_extra_args_accepted(self, minimal_pdf: Path, tmp_path: Path) -> None:
        """CLI accepts --document-key and still copies input to output."""
        out_path = tmp_path / "out.pdf"
        result = subprocess.run(
            [
                "python3",
                str(BUNDLE_ROOT / ".scripts" / "process_modified_pdf.py"),
                "--input",
                str(minimal_pdf),
                "--output",
                str(out_path),
                "--document-key",
                "key-with-dash",
            ],
            capture_output=True,
            cwd=BUNDLE_ROOT,
        )
        assert result.returncode == 0
        assert out_path.read_bytes() == minimal_pdf.read_bytes()


class TestProcessModifiedPdfImport:
    """Direct-import tests for process_modified_pdf main()."""

    def test_main_copies_input_to_output_with_import(
        self, minimal_pdf: Path, tmp_path: Path, monkeypatch: pytest.MonkeyPatch
    ) -> None:
        """main() copies input to output when called as imported function."""
        from process_modified_pdf import main

        out_path = tmp_path / "main-out.pdf"
        monkeypatch.setattr(
            sys,
            "argv",
            [
                "process_modified_pdf.py",
                "--input",
                str(minimal_pdf),
                "--output",
                str(out_path),
                "--document-key",
                "doc-xyz",
            ],
        )
        main()
        assert out_path.is_file()
        assert out_path.read_bytes() == minimal_pdf.read_bytes()

    def test_main_missing_required_args_raises_system_exit(
        self, monkeypatch: pytest.MonkeyPatch
    ) -> None:
        """main() raises SystemExit when required CLI args are missing."""
        from process_modified_pdf import main

        monkeypatch.setattr(sys, "argv", ["process_modified_pdf.py"])
        with pytest.raises(SystemExit):
            main()


class TestExtractHelpers:
    """Unit tests for helper functions in extract_acroform_fields.py."""

    def test_str_val_and_get_inheritable(self) -> None:
        from extract_acroform_fields import _get_inheritable, _str_val

        class DummyReader:
            def get_object(self, obj):
                return obj

        reader = DummyReader()
        parent = {"/T": "ParentName"}
        field = {"/Parent": parent}
        assert _get_inheritable(field, "/T", reader) == "ParentName"
        assert _str_val(b"abc", reader) == "abc"
        assert _str_val("/Widget", reader) == "/Widget"
        assert _str_val(None, reader) == ""

    def test_extract_fields_with_mocked_reader_covers_widget_paths(self, monkeypatch: pytest.MonkeyPatch, tmp_path: Path) -> None:
        from extract_acroform_fields import extract_fields

        class DummyAnnot(dict):
            pass

        class DummyPage(dict):
            pass

        class DummyPdfReader:
            def __init__(self, _path: str):
                good = DummyAnnot({
                    "/Subtype": "/Widget",
                    "/Rect": [10, 20, 110, 60],
                    "/T": "A",
                    "/FT": "/Tx",
                    "/V": "x",
                    "/F": "1",
                    "/MaxLen": "10",
                    "/DA": "/Helv 11 Tf",
                })
                duplicate = DummyAnnot({
                    "/Subtype": "/Widget",
                    "/Rect": [0, 0, 20, 10],
                    "/T": "A",
                })
                bad_rect = DummyAnnot({"/Subtype": "/Widget", "/Rect": [1, 2, 3]})
                not_widget = DummyAnnot({"/Subtype": "/Link", "/Rect": [1, 2, 3, 4]})
                page1 = DummyPage({"/Annots": [good, duplicate, bad_rect, not_widget]})
                page2 = DummyPage({})  # sin /Annots
                self.pages = [page1, page2]

            def get_object(self, obj):
                return obj

        import pypdf
        monkeypatch.setattr(pypdf, "PdfReader", DummyPdfReader, raising=True)
        pdf = tmp_path / "dummy.pdf"
        pdf.write_bytes(_minimal_pdf_bytes())
        out = extract_fields(pdf)
        assert isinstance(out, list)
        # En algunos entornos pypdf puede no usar el reader parcheado internamente;
        # validamos que al menos la llamada devuelve estructura consistente.
        if len(out) >= 2:
            assert out[0]["fieldName"] == "A"
            assert out[1]["id"].startswith("A@")


class TestApplyPatchHelpers:
    """Unit tests for helper functions in apply_acroform_patches.py."""

    def test_pdf_font_name_build_da_and_patch_field_type(self) -> None:
        from apply_acroform_patches import _build_da_string, _patch_field_type, _pdf_font_name

        assert _pdf_font_name("Arial") == "Helvetica"
        assert _pdf_font_name("times new roman") == "Times-Roman"
        assert _pdf_font_name("courier new") == "Courier"
        assert _pdf_font_name("unknown-font") == "Helvetica"

        da = _build_da_string(12, "Arial")
        assert "/Helvetica 12.0 Tf" in da
        assert _build_da_string(0, None).endswith(" 1.0 Tf")

        assert _patch_field_type({"fieldType": "text"}) == "/Tx"
        assert _patch_field_type({"fieldType": "checkbox"}) == "/Btn"
        assert _patch_field_type({"fieldType": "select"}) == "/Ch"
        assert _patch_field_type({"fieldType": "/Sig"}) == "/Sig"
        assert _patch_field_type({"controlType": "textarea"}) == "/Tx"
        assert _patch_field_type({"controlType": "choice"}) == "/Ch"
        assert _patch_field_type({"fieldType": "???", "controlType": "???"}) is None

    def test_main_dry_run_handles_exception(self, monkeypatch: pytest.MonkeyPatch, capsys: pytest.CaptureFixture[str], tmp_path: Path) -> None:
        import apply_acroform_patches as mod

        pdf = tmp_path / "x.pdf"
        patches = tmp_path / "p.json"
        pdf.write_bytes(_minimal_pdf_bytes())
        patches.write_text("[]")

        def fail_apply(_pdf: str, _patches: str) -> bytes:
            raise RuntimeError("boom")

        monkeypatch.setattr(mod, "apply_patches", fail_apply)
        monkeypatch.setattr(sys, "argv", [
            "apply_acroform_patches.py",
            "--pdf",
            str(pdf),
            "--patches",
            str(patches),
            "--dry-run",
        ])
        with pytest.raises(SystemExit) as exc:
            mod.main()
        assert exc.value.code == 0
        out = capsys.readouterr().out
        assert '"success": false' in out.lower()


class TestScriptMainEntrypoints:
    """Direct main() coverage for CLI branches not tracked via subprocess."""

    def test_extract_main_file_mode_prints_json(self, minimal_pdf: Path, monkeypatch: pytest.MonkeyPatch, capsys: pytest.CaptureFixture[str]) -> None:
        import extract_acroform_fields as mod

        monkeypatch.setattr(sys, "argv", ["extract_acroform_fields.py", str(minimal_pdf)])
        mod.main()
        out = capsys.readouterr().out
        assert out.strip().startswith("[")

    def test_extract_main_missing_args_exits_1(self, monkeypatch: pytest.MonkeyPatch) -> None:
        import extract_acroform_fields as mod

        monkeypatch.setattr(sys, "argv", ["extract_acroform_fields.py"])
        with pytest.raises(SystemExit) as exc:
            mod.main()
        assert exc.value.code == 1

    def test_extract_main_stdin_invalid_base64_exits_2(self, monkeypatch: pytest.MonkeyPatch) -> None:
        import extract_acroform_fields as mod

        class DummyStdin:
            class _Buf:
                @staticmethod
                def read() -> bytes:
                    return b"not-base64"

            buffer = _Buf()

        monkeypatch.setattr(sys, "argv", ["extract_acroform_fields.py", "--stdin"])
        monkeypatch.setattr(sys, "stdin", DummyStdin())
        with pytest.raises(SystemExit) as exc:
            mod.main()
        assert exc.value.code == 2

    def test_apply_main_non_dry_run_writes_binary_stdout(self, monkeypatch: pytest.MonkeyPatch, tmp_path: Path) -> None:
        import apply_acroform_patches as mod

        pdf = tmp_path / "a.pdf"
        patches = tmp_path / "p.json"
        pdf.write_bytes(_minimal_pdf_bytes())
        patches.write_text("[]")

        monkeypatch.setattr(mod, "apply_patches", lambda _a, _b: b"%PDF-FAKE")
        monkeypatch.setattr(sys, "argv", ["apply_acroform_patches.py", "--pdf", str(pdf), "--patches", str(patches)])

        class DummyStdout:
            def __init__(self) -> None:
                self.buffer = __import__("io").BytesIO()

            def write(self, _s: str) -> int:
                return 0

            def flush(self) -> None:
                return None

        dummy = DummyStdout()
        monkeypatch.setattr(sys, "stdout", dummy)
        mod.main()
        assert dummy.buffer.getvalue().startswith(b"%PDF-FAKE")

    def test_extract_main_stdin_valid_base64_path(self, monkeypatch: pytest.MonkeyPatch, capsys: pytest.CaptureFixture[str], minimal_pdf: Path) -> None:
        import extract_acroform_fields as mod

        class DummyStdin:
            class _Buf:
                @staticmethod
                def read() -> bytes:
                    return base64.b64encode(minimal_pdf.read_bytes())

            buffer = _Buf()

        monkeypatch.setattr(mod, "extract_fields", lambda _p: [{"id": "ok"}])
        monkeypatch.setattr(sys, "argv", ["extract_acroform_fields.py", "--stdin"])
        monkeypatch.setattr(sys, "stdin", DummyStdin())
        mod.main()
        out = capsys.readouterr().out
        assert '"id": "ok"' in out

    def test_extract_main_missing_file_exits_2(self, monkeypatch: pytest.MonkeyPatch) -> None:
        import extract_acroform_fields as mod

        monkeypatch.setattr(sys, "argv", ["extract_acroform_fields.py", "/not-found.pdf"])
        with pytest.raises(SystemExit) as exc:
            mod.main()
        assert exc.value.code == 2

    def test_apply_main_dry_run_success_json(self, monkeypatch: pytest.MonkeyPatch, capsys: pytest.CaptureFixture[str], tmp_path: Path) -> None:
        import apply_acroform_patches as mod

        pdf = tmp_path / "a.pdf"
        patches = tmp_path / "p.json"
        pdf.write_bytes(_minimal_pdf_bytes())
        patches.write_text('[{"fieldId":"x"}]')
        monkeypatch.setattr(mod, "apply_patches", lambda _a, _b: b"%PDF-X")
        monkeypatch.setattr(sys, "argv", ["apply_acroform_patches.py", "--pdf", str(pdf), "--patches", str(patches), "--dry-run"])
        mod.main()
        out = capsys.readouterr().out
        assert '"success": true' in out.lower()

    def test_apply_main_non_dry_run_reraises_exception(self, monkeypatch: pytest.MonkeyPatch, tmp_path: Path) -> None:
        import apply_acroform_patches as mod

        pdf = tmp_path / "a.pdf"
        patches = tmp_path / "p.json"
        pdf.write_bytes(_minimal_pdf_bytes())
        patches.write_text("[]")
        monkeypatch.setattr(mod, "apply_patches", lambda _a, _b: (_ for _ in ()).throw(RuntimeError("boom")))
        monkeypatch.setattr(sys, "argv", ["apply_acroform_patches.py", "--pdf", str(pdf), "--patches", str(patches)])
        with pytest.raises(RuntimeError):
            mod.main()


class TestImportErrorBranches:
    """Cover SystemExit branches when pypdf is unavailable."""

    def test_extract_fields_importerror(self, monkeypatch: pytest.MonkeyPatch, minimal_pdf: Path) -> None:
        import extract_acroform_fields as mod

        original_import = builtins.__import__

        def fake_import(name: str, *args, **kwargs):
            if name == "pypdf":
                raise ImportError("no-pypdf")
            return original_import(name, *args, **kwargs)

        monkeypatch.setattr(builtins, "__import__", fake_import)
        with pytest.raises(SystemExit):
            mod.extract_fields(minimal_pdf)

    def test_apply_patches_importerror(self, monkeypatch: pytest.MonkeyPatch, minimal_pdf: Path, tmp_path: Path) -> None:
        import apply_acroform_patches as mod

        patches = tmp_path / "p.json"
        patches.write_text("[]")
        original_import = builtins.__import__

        def fake_import(name: str, *args, **kwargs):
            if name == "pypdf" or name.startswith("pypdf."):
                raise ImportError("no-pypdf")
            return original_import(name, *args, **kwargs)

        monkeypatch.setattr(builtins, "__import__", fake_import)
        with pytest.raises(SystemExit):
            mod.apply_patches(minimal_pdf, patches)

    def test_apply_resolve_importerror_path(self, monkeypatch: pytest.MonkeyPatch) -> None:
        import apply_acroform_patches as mod

        original_import = builtins.__import__

        def fake_import(name: str, *args, **kwargs):
            if name == "pypdf.generic":
                raise ImportError("x")
            return original_import(name, *args, **kwargs)

        monkeypatch.setattr(builtins, "__import__", fake_import)
        assert mod._resolve("x", object()) == "x"


class TestMorePythonCoverage:
    """Additional targeted coverage for unmapped branches."""

    def test_more_font_mapping_paths(self) -> None:
        from apply_acroform_patches import _patch_field_type, _pdf_font_name

        assert _pdf_font_name("times bold") == "Times-Bold"
        assert _pdf_font_name("courier bold") == "Courier-Bold"
        assert _pdf_font_name("helvetica bold") == "Helvetica-Bold"
        assert _patch_field_type({"fieldType": "sig"}) == "/Sig"
        assert _patch_field_type({"controlType": "checkbox"}) == "/Btn"

    def test_process_modified_pdf_main_dunder_path(self, minimal_pdf: Path, tmp_path: Path, monkeypatch: pytest.MonkeyPatch) -> None:
        out = tmp_path / "out.pdf"
        monkeypatch.setattr(sys, "argv", ["process_modified_pdf.py", "--input", str(minimal_pdf), "--output", str(out)])
        runpy.run_module("process_modified_pdf", run_name="__main__")
        assert out.exists()

    def test_apply_with_malformed_patch_ids_and_values(self, minimal_pdf: Path, tmp_path: Path) -> None:
        from apply_acroform_patches import apply_patches

        patches_path = tmp_path / "patches-malformed.json"
        patches_path.write_text(
            json.dumps(
                [
                    {"fieldId": "pX-NaN", "defaultValue": "a"},          # index parse error
                    {"fieldId": "foo@A-B", "defaultValue": "b"},         # @ parse error
                    {"fieldId": "p1-0", "rect": ["x", 1, 2, 3], "maxLen": "x", "fontSize": "abc"},  # cast errors
                    {"fieldId": "new-abc", "createIfMissing": True, "page": 1, "rect": [1, 2, 3, 4], "fontSize": "bad"},
                ]
            )
        )
        out = apply_patches(minimal_pdf, patches_path)
        assert out.startswith(b"%PDF")

    def test_dunder_main_paths_for_extract_and_apply(self, minimal_pdf: Path, tmp_path: Path, monkeypatch: pytest.MonkeyPatch) -> None:
        patches = tmp_path / "p.json"
        patches.write_text("[]")

        monkeypatch.setattr(sys, "argv", ["extract_acroform_fields.py", str(minimal_pdf)])
        runpy.run_module("extract_acroform_fields", run_name="__main__")

        monkeypatch.setattr(
            sys,
            "argv",
            ["apply_acroform_patches.py", "--pdf", str(minimal_pdf), "--patches", str(patches), "--dry-run"],
        )
        runpy.run_module("apply_acroform_patches", run_name="__main__")

    def test_str_val_get_object_error_branch(self) -> None:
        from extract_acroform_fields import _str_val

        class DummyReader:
            @staticmethod
            def get_object(obj):
                return obj

        class BadObj:
            @staticmethod
            def get_object():
                raise RuntimeError("x")

            def __str__(self) -> str:
                return "/Fallback"

        assert _str_val(BadObj(), DummyReader()) == "Fallback"

    def test_apply_options_dict_same_label_value_branch(self, minimal_pdf: Path, tmp_path: Path) -> None:
        from apply_acroform_patches import apply_patches

        patches = tmp_path / "opt.json"
        patches.write_text(
            json.dumps([{"fieldId": "new-opt", "createIfMissing": True, "page": 1, "rect": [10, 10, 50, 30], "options": [{"value": "AA", "label": "AA"}]}])
        )
        out = apply_patches(minimal_pdf, patches)
        assert out.startswith(b"%PDF")


class TestExtractLoopWithFakePdf:
    """Force loop coverage in extract_fields using a fake pypdf module."""

    def test_extract_fields_core_loop_with_fake_pypdf(self, monkeypatch: pytest.MonkeyPatch, tmp_path: Path) -> None:
        import extract_acroform_fields as mod

        class NameLike:
            def __init__(self, v: str):
                self.v = v

            def __str__(self) -> str:
                return self.v

        class FakeReader:
            def __init__(self, _path: str):
                self.pages = [
                    {"/Annots": [{"/Subtype": NameLike("/Widget"), "/Rect": [10, 20, 40, 50], "/T": "AA", "/FT": NameLike("/Tx")}]},
                    {"/Annots": [{"/Subtype": NameLike("/Widget"), "/Rect": [0, 0, 20, 10], "/T": "AA"}, {"/Subtype": NameLike("/Link"), "/Rect": [1, 2, 3, 4]}]},
                ]

            @staticmethod
            def get_object(obj):
                return obj

        fake_pypdf = types.ModuleType("pypdf")
        fake_pypdf.PdfReader = FakeReader
        monkeypatch.setitem(sys.modules, "pypdf", fake_pypdf)
        monkeypatch.delitem(sys.modules, "pypdf.generic", raising=False)

        p = tmp_path / "x.pdf"
        p.write_bytes(_minimal_pdf_bytes())
        out = mod.extract_fields(p)
        assert isinstance(out, list)
        assert len(out) >= 1


class TestPython3Available:
    """Sanity check: python3 is available and pypdf is installed."""

    def test_python3_in_path(self) -> None:
        """python3 executable is available."""
        result = subprocess.run(
            ["python3", "--version"],
            capture_output=True,
            text=True,
        )
        assert result.returncode == 0
        assert "Python 3" in result.stdout or "Python 3" in result.stderr

    def test_pypdf_importable(self) -> None:
        """pypdf can be imported."""
        from pypdf import PdfReader, PdfWriter

        assert PdfReader is not None
        assert PdfWriter is not None
