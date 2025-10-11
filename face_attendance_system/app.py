from flask import Flask, render_template, request, jsonify
import os
import cv2
# try:
#     import face_recognition
#     print("Face recognition imported successfully")
# except Exception as e:
#     print(f"Failed to import face_recognition: {e}")
#     exit(1)
import numpy as np
from datetime import datetime

app = Flask(__name__)

DATASET_DIR = 'dataset'
if not os.path.exists(DATASET_DIR):
    os.makedirs(DATASET_DIR)

# --- Helper functions ---
def load_known_faces():
    known_faces = []
    known_names = []
    for file in os.listdir(DATASET_DIR):
        if file.endswith((".jpg", ".png")):
            path = os.path.join(DATASET_DIR, file)
            image = face_recognition.load_image_file(path)
            encoding = face_recognition.face_encodings(image)
            if encoding:
                known_faces.append(encoding[0])
                known_names.append(file.split("_")[0])
    return known_faces, known_names

def mark_attendance(name):
    with open("attendance.csv", "a") as f:
        time = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        f.write(f"{name},{time}\n")

# --- Routes ---
@app.route('/')
def home():
    return render_template('register.html')

@app.route('/attendance')
def attendance_page():
    return render_template('attendance.html')

@app.route('/register', methods=['POST'])
def register_student():
    name = request.form['name']
    file = request.files['image']

    if name and file:
        filepath = os.path.join(DATASET_DIR, f"{name}_{datetime.now().strftime('%H%M%S')}.jpg")
        file.save(filepath)
        return jsonify({"status": "success", "message": f"{name} registered successfully"})
    return jsonify({"status": "error", "message": "Missing name or image"})

@app.route('/check', methods=['POST'])
def check_attendance():
    file = request.files['image']
    if not file:
        return jsonify({"status": "error", "message": "No image uploaded"})

    # Save temp frame
    file.save("temp.jpg")
    test_img = face_recognition.load_image_file("temp.jpg")
    test_encodings = face_recognition.face_encodings(test_img)

    if not test_encodings:
        return jsonify({"status": "error", "message": "No face detected"})

    test_encoding = test_encodings[0]
    known_faces, known_names = load_known_faces()

    if not known_faces:
        return jsonify({"status": "error", "message": "No registered students"})

    face_distances = face_recognition.face_distance(known_faces, test_encoding)
    best_match = np.argmin(face_distances)

    if face_distances[best_match] < 0.5:
        name = known_names[best_match]
        mark_attendance(name)
        return jsonify({"status": "success", "name": name})
    else:
        return jsonify({"status": "error", "message": "Unknown face"})

if __name__ == '__main__':
    app.run(debug=True)
