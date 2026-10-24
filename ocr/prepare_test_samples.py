import os
import requests
import numpy as np
from PIL import Image, ImageDraw, ImageFont, ImageFilter
import cv2

SAMPLES_DIR = os.path.join(os.path.dirname(__file__), "test_samples")
os.makedirs(SAMPLES_DIR, exist_ok=True)

# 1. Загрузка реальных фото с открытых источников (Wikimedia Commons)
REAL_SAMPLES = [
    {
        "filename": "real_door_sticker_daimler.jpg",
        "url": "https://upload.wikimedia.org/wikipedia/commons/d/d1/Vin_number_11.jpg",
        "desc": "Real DaimlerChrysler door pillar sticker with barcode and VIN"
    },
    {
        "filename": "real_stamped_chassis.jpg",
        "url": "https://upload.wikimedia.org/wikipedia/commons/9/9c/VIN_-_IMG_0467.JPG",
        "desc": "Real stamped chassis/frame VIN on metal"
    }
]

headers = {"User-Agent": "VinInspector-Bot/1.0 (Testing OCR; education project)"}

for item in REAL_SAMPLES:
    dest = os.path.join(SAMPLES_DIR, item["filename"])
    if not os.path.exists(dest):
        try:
            print(f"Downloading {item['filename']}...")
            resp = requests.get(item["url"], headers=headers, timeout=15)
            if resp.status_code == 200 and len(resp.content) > 1000:
                with open(dest, "wb") as f:
                    f.write(resp.content)
                print(f"  OK: saved {len(resp.content)} bytes")
            else:
                print(f"  Failed: HTTP {resp.status_code}")
        except Exception as e:
            print(f"  Download error: {e}")

# Калькулятор контрольного знака для генерации валидных VIN
VIN_TRANSLATION = {
    'A': 1, 'B': 2, 'C': 3, 'D': 4, 'E': 5, 'F': 6, 'G': 7, 'H': 8,
    'J': 1, 'K': 2, 'L': 3, 'M': 4, 'N': 5, 'P': 7, 'R': 9,
    'S': 2, 'T': 3, 'U': 4, 'V': 5, 'W': 6, 'X': 7, 'Y': 8, 'Z': 9,
    '0': 0, '1': 1, '2': 2, '3': 3, '4': 4, '5': 5, '6': 6, '7': 7, '8': 8, '9': 9
}
VIN_WEIGHTS = [8, 7, 6, 5, 4, 3, 2, 10, 0, 9, 8, 7, 6, 5, 4, 3, 2]

def compute_check_digit(vin_16):
    """Принимает 17-значный VIN (где 9-й знак любой) и возвращает правильный контрольный знак"""
    chars = list(vin_16.upper())
    total = sum(VIN_TRANSLATION[ch] * w for ch, w in zip(chars, VIN_WEIGHTS))
    rem = total % 11
    return 'X' if rem == 10 else str(rem)

def make_valid_vin(prefix_8, suffix_8):
    temp = prefix_8 + "0" + suffix_8
    cd = compute_check_digit(temp)
    return prefix_8 + cd + suffix_8

# Создадим реалистичные синтетические образцы для всех 4 требуемых сценариев:

# Сценарий 1: Табличка под лобовым стеклом (windshield VIN) — черная металлическая плашка с бликами стекла
vin1 = make_valid_vin("1HGCR2F8", "HA012345") # 1HGCR2F80HA012345
img1 = Image.new("RGB", (900, 320), color=(20, 24, 32))
draw1 = ImageDraw.Draw(img1)

# Текстура панели приборов / стекла
for y in range(320):
    shade = int(20 + y * 0.1)
    draw1.line([(0, y), (900, y)], fill=(shade, shade + 3, shade + 8))

# Металлическая рамка окна шильдика
draw1.rectangle([120, 80, 780, 240], fill=(12, 14, 18), outline=(60, 68, 78), width=3)
draw1.rectangle([140, 100, 760, 220], fill=(5, 5, 8))

# Рисуем заклепки
for rx, ry in [(130, 90), (770, 90), (130, 230), (770, 230)]:
    draw1.ellipse([rx-6, ry-6, rx+6, ry+6], fill=(110, 115, 125), outline=(40, 45, 50))

# Текст VIN (шрифт или точечный рендеринг)
try:
    font_large = ImageFont.truetype("arialbd.ttf", 46)
    font_small = ImageFont.truetype("arial.ttf", 20)
except Exception:
    font_large = ImageFont.load_default()
    font_small = ImageFont.load_default()

draw1.text((170, 115), "HONDA MOTOR CO., LTD.", fill=(160, 165, 175), font=font_small)
draw1.text((170, 145), vin1, fill=(235, 238, 245), font=font_large)

# Накладываем блик стекла (световая диагональная полоса)
cv_img1 = cv2.cvtColor(np.array(img1), cv2.COLOR_RGB2BGR)
overlay = cv_img1.copy()
cv2.line(overlay, (200, 0), (600, 320), (255, 255, 255), 70)
cv2.addWeighted(overlay, 0.28, cv_img1, 0.72, 0, cv_img1)
# Добавим легкий шум стекла
noise = np.random.normal(0, 7, cv_img1.shape).astype(np.uint8)
cv_img1 = cv2.add(cv_img1, noise)

cv2.imwrite(os.path.join(SAMPLES_DIR, "sample1_windshield_vin.jpg"), cv_img1)
print(f"Created sample1_windshield_vin.jpg with VIN: {vin1}")


# Сценарий 2: Наклейка на стойке двери (B-pillar door sticker) с логотипом, параметрами и штрихкодом
vin2 = make_valid_vin("3PCAJ5M3", "KF147171") # Тестовый Infiniti QX50
img2 = Image.new("RGB", (950, 480), color=(240, 240, 242))
draw2 = ImageDraw.Draw(img2)
draw2.rectangle([20, 20, 930, 460], fill=(255, 255, 255), outline=(30, 30, 30), width=4)

draw2.text((60, 45), "MFD BY NISSAN MOTOR CO., LTD.  DATE: 03/19", fill=(10, 10, 10), font=font_small)
draw2.text((60, 75), "GVWR: 4850 LBS (2200 KG)   GAWR FR: 2500 LBS   RR: 2450 LBS", fill=(20, 20, 20), font=font_small)
draw2.text((60, 110), "THIS VEHICLE CONFORMS TO ALL APPLICABLE FEDERAL MOTOR VEHICLE SAFETY", fill=(40, 40, 40), font=font_small)
draw2.text((60, 140), "STANDARDS IN EFFECT ON THE DATE OF MANUFACTURE SHOWN ABOVE.", fill=(40, 40, 40), font=font_small)

# Имитация штрих-кода Code 39
np.random.seed(42)
bx = 60
by = 185
bh = 90
draw2.rectangle([bx, by, 890, by+bh], fill=(255, 255, 255))
curr_x = bx
while curr_x < 880:
    w = np.random.choice([2, 4, 6])
    draw2.rectangle([curr_x, by, curr_x + w, by + bh], fill=(0, 0, 0))
    curr_x += w + np.random.choice([2, 3, 5])

draw2.text((60, 290), "V.I.N.:", fill=(10, 10, 10), font=font_small)
draw2.text((150, 280), vin2, fill=(0, 0, 0), font=font_large)
draw2.text((60, 350), "PASSENGER CAR   MODEL: 2019 INFINITI QX50 AWD   COLOR: KAD", fill=(30, 30, 30), font=font_small)
draw2.text((60, 385), "TIRE: 235/55R19   RIMS: 19X7.5J   COLD TIRE PSI: 33", fill=(30, 30, 30), font=font_small)

cv_img2 = cv2.cvtColor(np.array(img2), cv2.COLOR_RGB2BGR)
cv2.imwrite(os.path.join(SAMPLES_DIR, "sample2_door_sticker.jpg"), cv_img2)
print(f"Created sample2_door_sticker.jpg with VIN: {vin2}")


# Сценарий 3: Выбитый на металле стакана/лонжерона номер (stamped metal VIN) — серый металл, рельеф
vin3 = make_valid_vin("WAUZZZF2", "KA123456") # Audi A6
# Создаем текстуру шлифованного металла
metal = np.zeros((300, 950, 3), dtype=np.uint8)
for i in range(300):
    base_gray = 130 + int(20 * np.sin(i / 15.0))
    metal[i, :, :] = base_gray
# Металлические царапины и текстура
noise = np.random.normal(0, 12, metal.shape).astype(np.int16)
metal = np.clip(metal.astype(np.int16) + noise, 0, 255).astype(np.uint8)

# Рисуем выбитые буквы со светотенью (эмбосс-эффект)
font_cv = cv2.FONT_HERSHEY_SIMPLEX
text_vin = f"*{vin3}*"
# Тень (вдавленный металл)
cv2.putText(metal, text_vin, (50, 175), font_cv, 1.4, (50, 50, 55), 7, cv2.LINE_AA)
# Блик сверху вдавленного символа
cv2.putText(metal, text_vin, (48, 172), font_cv, 1.4, (200, 205, 215), 3, cv2.LINE_AA)
# Сердцевина
cv2.putText(metal, text_vin, (51, 174), font_cv, 1.4, (20, 22, 25), 4, cv2.LINE_AA)

cv2.imwrite(os.path.join(SAMPLES_DIR, "sample3_stamped_metal.jpg"), metal)
print(f"Created sample3_stamped_metal.jpg with VIN: {vin3}")


# Сценарий 4: Снято под углом при плохом освещении (angled & low-light)
vin4 = make_valid_vin("5UXCR6C0", "M9B12345") # BMW X5
# Берем за основу наклейку, накладываем затемнение и перспективу
img4 = Image.new("RGB", (800, 300), color=(180, 180, 185))
d4 = ImageDraw.Draw(img4)
d4.rectangle([20, 20, 780, 280], fill=(220, 220, 225), outline=(50, 50, 50), width=2)
d4.text((40, 40), "BMW AG - VEHICLE IDENTIFICATION", fill=(60, 60, 60), font=font_small)
d4.text((40, 100), vin4, fill=(20, 20, 20), font=font_large)
d4.text((40, 190), "MADE IN USA - SPARTANBURG PLANT", fill=(70, 70, 70), font=font_small)

cv4 = cv2.cvtColor(np.array(img4), cv2.COLOR_RGB2BGR)

# Перспективное искажение (съемка сбоку/под углом 30 градусов)
h, w = cv4.shape[:2]
src_pts = np.float32([[0, 0], [w, 0], [w, h], [0, h]])
dst_pts = np.float32([[40, 60], [w - 90, 20], [w - 30, h - 30], [20, h - 10]])
M = cv2.getPerspectiveTransform(src_pts, dst_pts)
warped = cv2.warpPerspective(cv4, M, (w, h), borderValue=(25, 28, 35))

# Плохое освещение (градиентное затемнение, виньетка)
gradient = np.tile(np.linspace(0.4, 0.85, w), (h, 1))
warped = (warped * gradient[:, :, np.newaxis]).astype(np.uint8)

cv2.imwrite(os.path.join(SAMPLES_DIR, "sample4_angled_low_light.jpg"), warped)
print(f"Created sample4_angled_low_light.jpg with VIN: {vin4}")

print("\nAll test samples prepared successfully in test_samples/!")
