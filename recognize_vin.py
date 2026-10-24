"""
Root wrapper for VIN Recognition pipeline.
Delegates execution to ocr/recognize_vin.py
"""

import sys
import os

# Add ocr directory to Python path
current_dir = os.path.dirname(os.path.abspath(__file__))
ocr_dir = os.path.join(current_dir, "ocr")
if ocr_dir not in sys.path:
    sys.path.insert(0, ocr_dir)

from recognize_vin import main

if __name__ == "__main__":
    main()
