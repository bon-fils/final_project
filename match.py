import sys
import json
import face_recognition
import cv2
import numpy as np

# Ensure we have both image paths
if len(sys.argv) != 3:
    print(json.dumps({"error": "Usage: python match.py <image1> <image2>"}))
    sys.exit(1)

image1_path = sys.argv[1]
image2_path = sys.argv[2]

try:
    # Load both images
    img1 = face_recognition.load_image_file(image1_path)
    img2 = face_recognition.load_image_file(image2_path)

    # Encode faces (extract facial embeddings)
    encodings1 = face_recognition.face_encodings(img1)
    encodings2 = face_recognition.face_encodings(img2)

    if len(encodings1) == 0 or len(encodings2) == 0:
        print(json.dumps({"match": False, "score": 0.0, "error": f"No face detected in image 1: {len(encodings1)} faces, image 2: {len(encodings2)} faces"}))
        sys.exit(0)

    # Compare first face found in each image
    face1 = encodings1[0]
    face2 = encodings2[0]

    # Compute Euclidean distance
    distance = np.linalg.norm(face1 - face2)
    # Lower distance = better match. We convert it to similarity score
    # Typical good match threshold: 0.6 or lower
    score = max(0.0, 1.0 - distance)  # Convert distance into a 0-1 similarity

    matched = distance < 0.8  # More lenient threshold

    print(json.dumps({
        "match": bool(matched),
        "score": float(score),
        "distance": float(distance)
    }))

except Exception as e:
    print(json.dumps({"error": str(e)}))
    sys.exit(1)
