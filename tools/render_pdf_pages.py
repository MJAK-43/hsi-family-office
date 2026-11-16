from pathlib import Path
import sys

import fitz


if len(sys.argv) != 3:
    raise SystemExit("Usage: render_pdf_pages.py INPUT.pdf OUTPUT_DIR")

pdf_path = Path(sys.argv[1]).resolve()
output_dir = Path(sys.argv[2]).resolve()
output_dir.mkdir(parents=True, exist_ok=True)

document = fitz.open(pdf_path)
matrix = fitz.Matrix(2.0, 2.0)
for index, page in enumerate(document, start=1):
    pixmap = page.get_pixmap(matrix=matrix, alpha=False)
    pixmap.save(output_dir / f"page-{index}.png")

print(f"{len(document)} pages rendered to {output_dir}")
