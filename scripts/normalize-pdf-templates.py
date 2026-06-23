#!/usr/bin/env python3
"""
Normalize blank form templates so the free FPDI parser can import them.

Official templates come out of Drive as PDF 1.5+ using cross-reference streams,
which setasign/fpdi (free) cannot parse. PyMuPDF re-saves them with a classic
xref + deflate, which FPDI imports fine — same pages, same layout, just a
parser-friendly container. Run once after pulling new templates:

    python scripts/normalize-pdf-templates.py            # all *.pdf in resources/form-templates
    python scripts/normalize-pdf-templates.py CMP-173    # a single code

Requires: pip install PyMuPDF
"""
import sys, os, glob
import fitz

ROOT = os.path.join(os.path.dirname(__file__), "..", "resources", "form-templates")

def normalize(path):
    doc = fitz.open(path)
    tmp = path + ".norm"
    doc.save(tmp, garbage=4, clean=True, deflate=True)
    doc.close()
    os.replace(tmp, path)
    print(f"normalized {os.path.basename(path)} ({os.path.getsize(path):,} bytes)")

def main():
    only = sys.argv[1] if len(sys.argv) > 1 else None
    files = glob.glob(os.path.join(ROOT, "*.pdf"))
    if only:
        files = [f for f in files if os.path.splitext(os.path.basename(f))[0] == only]
        if not files:
            print(f"no template found for {only}")
            sys.exit(1)
    for f in files:
        normalize(f)

if __name__ == "__main__":
    main()
