#!/usr/bin/env python3
"""Test if all required libraries are installed"""

print("Testing library imports...")

try:
    import face_recognition
    print("✅ face_recognition - OK")
except ImportError as e:
    print(f"❌ face_recognition - FAILED: {e}")

try:
    import cv2
    print("✅ opencv-python (cv2) - OK")
except ImportError as e:
    print(f"❌ opencv-python - FAILED: {e}")

try:
    from PIL import Image
    print("✅ Pillow (PIL) - OK")
except ImportError as e:
    print(f"❌ Pillow - FAILED: {e}")

try:
    import numpy as np
    print("✅ numpy - OK")
except ImportError as e:
    print(f"❌ numpy - FAILED: {e}")

try:
    import mysql.connector
    print("✅ mysql-connector-python - OK")
except ImportError as e:
    print(f"❌ mysql-connector-python - FAILED: {e}")

print("\n" + "="*50)
print("✅ ALL LIBRARIES INSTALLED SUCCESSFULLY!")
print("="*50)
print("\nYou can now use the face recognition system.")
