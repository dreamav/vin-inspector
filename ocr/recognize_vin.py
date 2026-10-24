"""
VIN Recognition Pipeline using OpenCV and EasyOCR.
Implements ISO 3779 standards for Vehicle Identification Numbers:
- Forbidden characters: I, O, Q replaced by 1, 0, 0
- 17-character structure validation
- Modulo-11 check digit verification (NHTSA / ISO 3779)
- OCR confusion character correction guided by check digit
- High-priority label matching (VIN:, V.I.N., CHASSIS, etc.)
- Clean JSON output to stdout for seamless PHP integration
"""

import sys
import os
import re
import json
import time
import warnings
from typing import List, Tuple, Dict, Any, Optional

# Suppress PyTorch/EasyOCR console warnings
os.environ["PYTHONWARNINGS"] = "ignore"
warnings.filterwarnings("ignore")

import cv2
import numpy as np

# ISO 3779 Transliteration Values for Modulo-11 Check Digit (NHTSA / ISO 3779)
ISO_3779_VALUES = {
    'A': 1, 'B': 2, 'C': 3, 'D': 4, 'E': 5, 'F': 6, 'G': 7, 'H': 8,
    'J': 1, 'K': 2, 'L': 3, 'M': 4, 'N': 5,
    'P': 7, 'R': 9,
    'S': 2, 'T': 3, 'U': 4, 'V': 5, 'W': 6, 'X': 7, 'Y': 8, 'Z': 9,
    '0': 0, '1': 1, '2': 2, '3': 3, '4': 4, '5': 5, '6': 6, '7': 7, '8': 8, '9': 9
}

# ISO 3779 Position Weights (1-indexed 1 to 17)
ISO_3779_WEIGHTS = [8, 7, 6, 5, 4, 3, 2, 10, 0, 9, 8, 7, 6, 5, 4, 3, 2]

# ISO 3779 standard 17-character VIN pattern (I, O, Q are strictly forbidden)
VIN_PATTERN = re.compile(r'^[A-HJ-NPR-Z0-9]{17}$')

# Common OCR confusion character pairs
OCR_CONFUSIONS = {
    'Z': '2', '2': 'Z',
    'S': '5', '5': 'S',
    'B': '8', '8': 'B',
    'G': '6', '6': 'G',
    'D': '0',
    'U': 'V', 'V': 'U'
}

# Common English words found on door stickers that shouldn't be mistaken for VINs
STICKER_EXCLUDE_WORDS = {
    'MANUFACTURE', 'IDENTIFICATION', 'CONFORMS', 'APPLICABLE',
    'FEDERAL', 'STANDARDS', 'PASSENGER', 'CORPORATION', 'PREVENTION'
}


def normalize_ocr_string(text: str) -> str:
    """
    Applies standard ISO 3779 substitutions for forbidden optical characters:
    'O' -> '0', 'I' -> '1', 'Q' -> '0'
    Strips whitespace, hyphens, and punctuation.
    """
    if not text:
        return ""
    text_upper = text.upper()
    # Replace ISO 3779 forbidden letters with their numeric equivalents
    text_clean = text_upper.replace('O', '0').replace('I', '1').replace('Q', '0')
    # Keep only valid VIN alphanumeric characters
    text_clean = re.sub(r'[^A-HJ-NPR-Z0-9]', '', text_clean)
    return text_clean


def validate_vin_check_digit(vin: str) -> Tuple[bool, str]:
    """
    Calculates Modulo-11 check digit according to ISO 3779 / US 49 CFR Part 565.
    Returns (is_valid: bool, expected_char: str).
    """
    if len(vin) != 17:
        return False, ""
    
    vin = vin.upper()
    if any(c in vin for c in ('I', 'O', 'Q')):
        return False, ""
    
    total = 0
    for char, weight in zip(vin, ISO_3779_WEIGHTS):
        val = ISO_3779_VALUES.get(char)
        if val is None:
            return False, ""
        total += val * weight
    
    rem = total % 11
    expected_char = 'X' if rem == 10 else str(rem)
    actual_char = vin[8]
    
    return (actual_char == expected_char), expected_char


def score_repair_candidate(cand: str, substitutions: List[int]) -> int:
    """
    Scores a candidate that achieved a valid check digit:
    - Penalizes letters in positions 12-16 (VIS should be digits in production vehicles)
    - Penalizes digits in positions 1-2 (WMI positions 2 & 3 are almost always letters, e.g. 1HG, WAU, 5UX)
    - Rewards fewer substitutions (1 change is much more probable than 2 or 3)
    """
    score = 1000 - (len(substitutions) * 100)
    
    # VIS positions 12-16 (0-indexed, characters 13 to 17) must be numeric
    vis_letters = sum(cand[i].isalpha() for i in range(12, 17))
    score -= vis_letters * 250
    
    # In positions 1-2 (WMI letters): 1HG, 5UX, 3PC, WAU
    if len(cand) >= 3:
        if cand[1].isalpha():
            score += 50
        if cand[2].isalpha():
            score += 50

    return score


def attempt_check_digit_repair(vin: str) -> Tuple[str, bool]:
    """
    If a 17-character candidate does not validate via check digit,
    attempts to fix common optical OCR confusions (e.g. S<->5, Z<->2, B<->8, G<->6)
    guided by the mathematical check digit equation.
    """
    is_valid, _ = validate_vin_check_digit(vin)
    if is_valid:
        return vin, True

    chars = list(vin)
    valid_candidates = []
    
    # In positions 13-16 (the last 4 digits of VIS), letters are virtually always digits in production vehicles
    digit_fix = {'B': '8', 'Z': '2', 'S': '5', 'G': '6', 'D': '0'}
    for i in range(13, 17):
        if chars[i] in digit_fix:
            chars[i] = digit_fix[chars[i]]
            
    candidate_pos = ''.join(chars)
    is_valid, _ = validate_vin_check_digit(candidate_pos)
    if is_valid:
        return candidate_pos, True

    # Find ambiguous positions
    ambig_indices = [i for i, c in enumerate(chars) if c in OCR_CONFUSIONS]
    
    # 1 substitution
    for idx in ambig_indices:
        orig = chars[idx]
        chars[idx] = OCR_CONFUSIONS[orig]
        cand = ''.join(chars)
        valid, _ = validate_vin_check_digit(cand)
        if valid:
            valid_candidates.append((cand, [idx]))
        chars[idx] = orig
        
    # 2 substitutions
    for i in range(len(ambig_indices)):
        idx1 = ambig_indices[i]
        orig1 = chars[idx1]
        chars[idx1] = OCR_CONFUSIONS[orig1]
        for j in range(i + 1, len(ambig_indices)):
            idx2 = ambig_indices[j]
            orig2 = chars[idx2]
            chars[idx2] = OCR_CONFUSIONS[orig2]
            cand = ''.join(chars)
            valid, _ = validate_vin_check_digit(cand)
            if valid:
                valid_candidates.append((cand, [idx1, idx2]))
            chars[idx2] = orig2
        chars[idx1] = orig1

    # 3 substitutions (for noisy/angled conditions)
    if not valid_candidates and 3 <= len(ambig_indices) <= 8:
        for i in range(len(ambig_indices)):
            idx1 = ambig_indices[i]
            orig1 = chars[idx1]
            chars[idx1] = OCR_CONFUSIONS[orig1]
            for j in range(i + 1, len(ambig_indices)):
                idx2 = ambig_indices[j]
                orig2 = chars[idx2]
                chars[idx2] = OCR_CONFUSIONS[orig2]
                for k in range(j + 1, len(ambig_indices)):
                    idx3 = ambig_indices[k]
                    orig3 = chars[idx3]
                    chars[idx3] = OCR_CONFUSIONS[orig3]
                    cand = ''.join(chars)
                    valid, _ = validate_vin_check_digit(cand)
                    if valid:
                        valid_candidates.append((cand, [idx1, idx2, idx3]))
                    chars[idx3] = orig3
                chars[idx2] = orig2
            chars[idx1] = orig1
            
    if valid_candidates:
        valid_candidates.sort(key=lambda item: score_repair_candidate(item[0], item[1]), reverse=True)
        return valid_candidates[0][0], True

    return vin, False


def is_valid_vin_structure(candidate: str) -> bool:
    """
    Checks if a candidate is a plausible VIN rather than an English phrase:
    - Exactly 17 characters matching ISO 3779 alphabet
    - Contains both digits and letters (minimum 2 letters, minimum 2 digits)
    - Last 3-4 characters should not be purely alphabetic (ISO 3779 VIS serial number rule)
    """
    if len(candidate) != 17 or not VIN_PATTERN.match(candidate):
        return False
        
    digits_count = sum(c.isdigit() for c in candidate)
    letters_count = sum(c.isalpha() for c in candidate)
    if digits_count < 2 or letters_count < 2:
        return False
        
    # Reject English sentence fragments where the last 4 chars are all letters (e.g. "UFAC", "FICA")
    if candidate[-4:].isalpha():
        return False
        
    return True


def preprocess_image(image_path: str):
    """
    Reads image safely and applies dynamic scaling and enhancement:
    - Scales down images larger than 1920px (for speed)
    - Scales up images smaller than 900px (to enhance small sticker text)
    - Applies Grayscale, CLAHE contrast equalization, and unsharp masking
    """
    if not os.path.isfile(image_path):
        return None, None, None

    try:
        with open(image_path, 'rb') as f:
            file_bytes = np.fromfile(f, dtype=np.uint8)
            img = cv2.imdecode(file_bytes, cv2.IMREAD_COLOR)
    except Exception:
        img = cv2.imread(image_path)
        
    if img is None:
        return None, None, None

    h, w = img.shape[:2]
    max_dim = max(h, w)
    
    # Scale down if 4K/huge
    if max_dim > 1920:
        scale = 1920.0 / max_dim
        img = cv2.resize(img, (int(w * scale), int(h * scale)), interpolation=cv2.INTER_AREA)
    # Scale up if tiny thumbnail/crop
    elif max_dim < 900:
        scale = 1200.0 / max_dim
        img = cv2.resize(img, (int(w * scale), int(h * scale)), interpolation=cv2.INTER_CUBIC)

    # Grayscale
    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)

    # Contrast Limited Adaptive Histogram Equalization (CLAHE)
    clahe = cv2.createCLAHE(clipLimit=2.5, tileGridSize=(8, 8))
    gray_clahe = clahe.apply(gray)
    
    # Unsharp mask for stamped relief on metal
    gaussian = cv2.GaussianBlur(gray_clahe, (0, 0), 2.0)
    unsharp = cv2.addWeighted(gray_clahe, 1.5, gaussian, -0.5, 0)

    return img, gray_clahe, unsharp


def extract_vin_candidates(ocr_results: List[Any]) -> List[Dict[str, Any]]:
    """
    Finds and ranks all 17-character VIN candidates from OCR results:
    - Single block matches
    - Substrings within blocks
    - Adjacent block combinations
    - Check digit calculation and guided repair
    """
    candidates = []
    
    # Identify positions of "VIN", "V.I.N.", "V.LN", "CHASSIS" labels
    label_indices = set()
    for idx, (_, text, _) in enumerate(ocr_results):
        t_upper = text.upper()
        if re.search(r'\b(V[\.\s]?I[\.\s]?N|V[\.\s]?LN|CHASSIS|FAHRGESTELL|VEHICLE\s*ID)\b', t_upper) or 'V.LN' in t_upper:
            label_indices.add(idx)

    for idx, (bbox, text, conf) in enumerate(ocr_results):
        norm = normalize_ocr_string(text)
        is_labeled = (idx - 1 in label_indices) or (idx in label_indices) or (idx + 1 in label_indices)
        
        # 1. Exact 17-character string
        if len(norm) == 17:
            if is_valid_vin_structure(norm):
                fixed_vin, valid = attempt_check_digit_repair(norm)
                score = (500 if valid else 50) + (100 if is_labeled else 0) + int(float(conf) * 100)
                candidates.append({
                    'vin': fixed_vin,
                    'raw_norm': norm,
                    'confidence': float(conf),
                    'check_digit_valid': valid,
                    'score': score,
                    'source': text
                })
        # 2. 17-char slice inside longer string (e.g., *WAUZZZF23KA123456* or VIN:1HG...)
        elif len(norm) > 17:
            for s in range(len(norm) - 16):
                sub = norm[s:s+17]
                if is_valid_vin_structure(sub):
                    fixed_vin, valid = attempt_check_digit_repair(sub)
                    score = (450 if valid else 40) + (100 if is_labeled else 0) + int(float(conf) * 90)
                    candidates.append({
                        'vin': fixed_vin,
                        'raw_norm': sub,
                        'confidence': float(conf) * 0.95,
                        'check_digit_valid': valid,
                        'score': score,
                        'source': text
                    })

    # 3. Concatenate adjacent blocks if needed
    if len(ocr_results) >= 2:
        for i in range(len(ocr_results) - 1):
            t1 = normalize_ocr_string(ocr_results[i][1])
            t2 = normalize_ocr_string(ocr_results[i+1][1])
            combined = t1 + t2
            avg_conf = (float(ocr_results[i][2]) + float(ocr_results[i+1][2])) / 2.0
            
            if len(combined) == 17 and is_valid_vin_structure(combined):
                fixed_vin, valid = attempt_check_digit_repair(combined)
                score = (400 if valid else 30) + int(avg_conf * 80)
                candidates.append({
                    'vin': fixed_vin,
                    'raw_norm': combined,
                    'confidence': avg_conf * 0.9,
                    'check_digit_valid': valid,
                    'score': score,
                    'source': f"{ocr_results[i][1]} + {ocr_results[i+1][1]}"
                })

    return candidates


def recognize_vin_image(image_path: str) -> Dict[str, Any]:
    """
    Main recognition execution function.
    """
    start_time = time.time()

    # Preprocess
    img_bgr, img_clahe, img_unsharp = preprocess_image(image_path)
    if img_bgr is None:
        return {
            "success": False,
            "vin": None,
            "confidence": 0.0,
            "check_digit_valid": False,
            "execution_time_sec": round(time.time() - start_time, 2),
            "error": f"Image file not found or could not be loaded: '{image_path}'",
            "details": {
                "raw_detected": [],
                "normalized_vin": None
            }
        }

    # Initialize EasyOCR
    import easyocr
    reader = easyocr.Reader(['en'], gpu=False, verbose=False)

    # Pass 1: CLAHE contrast equalized image
    ocr_results = reader.readtext(img_clahe)
    candidates = extract_vin_candidates(ocr_results)

    # Pass 2: Unsharp image fallback (for stamped metal)
    if not candidates or not any(c['check_digit_valid'] for c in candidates):
        ocr_results_unsharp = reader.readtext(img_unsharp)
        cand_unsharp = extract_vin_candidates(ocr_results_unsharp)
        if cand_unsharp:
            candidates.extend(cand_unsharp)
            ocr_results.extend(ocr_results_unsharp)

    # Pass 3: Original BGR fallback
    if not candidates:
        ocr_results_bgr = reader.readtext(img_bgr)
        cand_bgr = extract_vin_candidates(ocr_results_bgr)
        if cand_bgr:
            candidates.extend(cand_bgr)
            ocr_results.extend(ocr_results_bgr)

    elapsed = round(time.time() - start_time, 2)
    raw_texts = [r[1] for r in ocr_results]

    if candidates:
        # Sort by composite score (valid check digit, confidence, label proximity)
        candidates.sort(key=lambda c: c['score'], reverse=True)
        best = candidates[0]

        return {
            "success": True,
            "vin": best['vin'],
            "confidence": round(best['confidence'], 2),
            "check_digit_valid": best['check_digit_valid'],
            "execution_time_sec": elapsed,
            "details": {
                "raw_detected": raw_texts,
                "normalized_vin": best['vin'],
                "match_source": best['source']
            }
        }
    else:
        return {
            "success": False,
            "vin": None,
            "confidence": 0.0,
            "check_digit_valid": False,
            "execution_time_sec": elapsed,
            "error": "No valid 17-character VIN found in image",
            "details": {
                "raw_detected": raw_texts,
                "normalized_vin": None
            }
        }


def main():
    if len(sys.argv) < 2:
        out = {
            "success": False,
            "vin": None,
            "confidence": 0.0,
            "check_digit_valid": False,
            "execution_time_sec": 0.0,
            "error": "Usage: python recognize_vin.py <path_to_image>"
        }
        sys.stdout.write(json.dumps(out, ensure_ascii=False, indent=2) + "\n")
        sys.exit(1)

    image_path = sys.argv[1]
    result = recognize_vin_image(image_path)
    sys.stdout.write(json.dumps(result, ensure_ascii=False, indent=2) + "\n")


if __name__ == "__main__":
    main()
