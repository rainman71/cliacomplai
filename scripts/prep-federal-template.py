#!/usr/bin/env python3
"""
Prepare a federal CMS fillable-AcroForm template for Track-B overlay.

The cms.gov CMS-116 / CMS-209 PDFs are true fillable AcroForms. This script:
  1. dumps every form field's name + type + rect (so you can build the overlay map by
     name->position, NOT by hand-measuring), and
  2. flattens the interactive widgets (leaving the printed form) + normalizes the file so
     the free FPDI parser can import it, writing resources/form-templates/{CODE}.pdf.

Usage:
    # download the official form first, e.g. to a temp path, then:
    python scripts/prep-federal-template.py CMS-116 /path/to/cms116.pdf

The field dump is printed to stdout (and saved next to the source as {CODE}-fields.txt).
Build app/Forms/Overlays/{CODE}.php from it: text baseline ≈ rect.y1 - 4.5; checkbox X
centered on the rect; `page` is 1-based. Requires: pip install PyMuPDF.

Official sources:
  CMS-116: https://www.cms.gov/medicare/cms-forms/cms-forms/downloads/cms116.pdf
  CMS-209: https://www.cms.gov/Medicare/CMS-Forms/CMS-Forms/downloads/cms209.pdf
"""
import sys, os
import fitz

ROOT = os.path.join(os.path.dirname(__file__), "..", "resources", "form-templates")


def main():
    if len(sys.argv) < 3:
        print("usage: prep-federal-template.py <CODE> <source.pdf>")
        sys.exit(1)
    code, src = sys.argv[1], sys.argv[2]
    doc = fitz.open(src)

    lines = []
    for pno, page in enumerate(doc):
        for w in page.widgets() or []:
            r = w.rect
            lines.append(
                f"p{pno} [{w.field_type_string}] '{w.field_name}'  "
                f"x0={r.x0:.1f} y0={r.y0:.1f} x1={r.x1:.1f} y1={r.y1:.1f}"
            )
    dump = "\n".join(lines)
    with open(os.path.splitext(src)[0] + "-fields.txt", "w", encoding="utf-8") as f:
        f.write(dump)
    print(dump)
    print(f"\n# {len(lines)} fields")

    # Flatten: drop the interactive widgets, keep the printed form.
    for page in doc:
        for w in list(page.widgets() or []):
            page.delete_widget(w)

    os.makedirs(ROOT, exist_ok=True)
    dst = os.path.join(ROOT, f"{code}.pdf")
    doc.save(dst, garbage=4, clean=True, deflate=True)
    print(f"\nsaved {dst} ({os.path.getsize(dst):,} bytes)")


if __name__ == "__main__":
    main()
