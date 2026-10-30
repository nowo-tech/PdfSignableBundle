#!/usr/bin/env python3
"""Process the modified PDF (e.g. after AcroForm edits) and write the result.

This is a stub: it copies input to output. Replace with your own logic
(fill remaining fields, sign, flatten, etc.) and configure process_script in the bundle.

Usage:
  python process_modified_pdf.py --input input.pdf --output output.pdf [--document-key KEY]

The bundle calls this after the user submits the modified PDF via POST /acroform/process.
Your script can write the result to --output; the bundle then dispatches an event
so PHP can save or use the file.
"""
from __future__ import annotations

import argparse
import shutil
from pathlib import Path



def limit_memory() -> None:
    """Cap the address space (PDF_SIGNABLE_MAX_MEMORY_MB, default 1024; 0 disables): crafted PDFs must not OOM the host."""
    import os

    try:
        import resource
    except ImportError:  # pragma: no cover - not POSIX
        return
    try:
        megabytes = int(os.environ.get("PDF_SIGNABLE_MAX_MEMORY_MB", "1024"))
    except ValueError:
        megabytes = 1024
    if megabytes <= 0:
        return
    limit = megabytes * 1024 * 1024
    try:
        resource.setrlimit(resource.RLIMIT_AS, (limit, limit))
    except (ValueError, OSError):  # pragma: no cover - hard limit already lower
        pass


def main() -> None:
    """Entry point: parse --input, --output, --document-key; copy input to output (stub).

    Replace the body with your own logic (e.g. fill fields, sign, flatten) and configure
    acroform_editor.process_script in the bundle. The bundle dispatches an event after
    this script runs so PHP can save or use the output file.
    """
    ap = argparse.ArgumentParser(description="Process modified PDF")
    ap.add_argument("--input", required=True, help="Path to input PDF")
    ap.add_argument("--output", required=True, help="Path to output PDF")
    ap.add_argument("--document-key", default=None, help="Optional document key from the request")
    args = ap.parse_args()
    # Stub: copy input to output; replace with fill/sign/flatten etc.
    shutil.copy(args.input, args.output)


if __name__ == "__main__":
    limit_memory()
    main()
