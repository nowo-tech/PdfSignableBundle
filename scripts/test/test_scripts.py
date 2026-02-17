"""Tests for AcroForm Python scripts: apply_acroform_patches, extract_acroform_fields, process_modified_pdf."""
from __future__ import annotations

import base64
import json
import subprocess
from pathlib import Path

import pytest

from pypdf import PdfReader, PdfWriter

BUNDLE_ROOT = Path(__file__).resolve().parent.parent.parent


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
    """Tests for scripts/extract_acroform_fields.py."""

    def test_extract_fields_minimal_pdf_returns_list(self, minimal_pdf: Path) -> None:
        """extract_fields on a minimal PDF returns a list (possibly empty)."""
        from extract_acroform_fields import extract_fields

        fields = extract_fields(minimal_pdf)
        assert isinstance(fields, list)

    def test_extract_fields_valid_json_output(self, minimal_pdf: Path) -> None:
        """CLI output is valid JSON array."""
        result = subprocess.run(
            ["python3", str(BUNDLE_ROOT / "scripts" / "extract_acroform_fields.py"), str(minimal_pdf)],
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
            ["python3", str(BUNDLE_ROOT / "scripts" / "extract_acroform_fields.py"), "--stdin"],
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
            ["python3", str(BUNDLE_ROOT / "scripts" / "extract_acroform_fields.py"), "/nonexistent.pdf"],
            capture_output=True,
            cwd=BUNDLE_ROOT,
        )
        assert result.returncode != 0

    def test_extract_fields_stdin_invalid_base64_exits_nonzero(self) -> None:
        """CLI --stdin with invalid base64 exits non-zero."""
        result = subprocess.run(
            ["python3", str(BUNDLE_ROOT / "scripts" / "extract_acroform_fields.py"), "--stdin"],
            input="not-valid-base64!!!",
            capture_output=True,
            text=True,
            cwd=BUNDLE_ROOT,
        )
        assert result.returncode != 0


class TestApplyAcroformPatches:
    """Tests for scripts/apply_acroform_patches.py."""

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
                str(BUNDLE_ROOT / "scripts" / "apply_acroform_patches.py"),
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
                str(BUNDLE_ROOT / "scripts" / "apply_acroform_patches.py"),
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
                str(BUNDLE_ROOT / "scripts" / "apply_acroform_patches.py"),
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
                str(BUNDLE_ROOT / "scripts" / "apply_acroform_patches.py"),
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


class TestProcessModifiedPdf:
    """Tests for scripts/process_modified_pdf.py (stub)."""

    def test_process_copies_input_to_output(
        self, minimal_pdf: Path, tmp_path: Path
    ) -> None:
        """CLI copies input PDF to output (stub behaviour)."""
        out_path = tmp_path / "out.pdf"
        result = subprocess.run(
            [
                "python3",
                str(BUNDLE_ROOT / "scripts" / "process_modified_pdf.py"),
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
                str(BUNDLE_ROOT / "scripts" / "process_modified_pdf.py"),
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
                str(BUNDLE_ROOT / "scripts" / "process_modified_pdf.py"),
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
                str(BUNDLE_ROOT / "scripts" / "process_modified_pdf.py"),
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
                str(BUNDLE_ROOT / "scripts" / "process_modified_pdf.py"),
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
                str(BUNDLE_ROOT / "scripts" / "process_modified_pdf.py"),
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
                str(BUNDLE_ROOT / "scripts" / "process_modified_pdf.py"),
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
