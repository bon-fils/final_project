#!/usr/bin/env python3
"""
Simple Face Recognition Script for RP Attendance System
Based on user's sample code, adapted for existing database schema
"""

import sys
import os
import json
import face_recognition
import mysql.connector
from mysql.connector import Error

def match_face(live_image_path):
    """Simple face matching function"""
    try:
        # Load live image
        live_image = face_recognition.load_image_file(live_image_path)
        live_encodings = face_recognition.face_encodings(live_image)

        if not live_encodings:
            return {"status": "error", "message": "No face detected in live image"}

        live_encoding = live_encodings[0]

    except Exception as e:
        return {"status": "error", "message": f"Error loading live image: {str(e)}"}

    # Database connection
    try:
        conn = mysql.connector.connect(
            host=os.getenv('DB_HOST', 'localhost'),
            database=os.getenv('DB_NAME', 'rp_attendance_system'),
            user=os.getenv('DB_USER', 'root'),
            password=os.getenv('DB_PASS', '')
        )
        cursor = conn.cursor(dictionary=True)

        # Get all student photos from the JSON structure
        cursor.execute("""
            SELECT s.id, s.reg_no, u.first_name, u.last_name, s.student_photos
            FROM students s
            INNER JOIN users u ON s.user_id = u.id
            WHERE s.student_photos IS NOT NULL AND s.student_photos != ''
            AND s.status = 'active'
        """)

        students = cursor.fetchall()

        best_match = None
        best_distance = 1.0  # Max distance for match
        best_student_info = None

        for student in students:
            try:
                # Parse biometric data
                bio_data = json.loads(student['student_photos']) if isinstance(student['student_photos'], str) else student['student_photos']
                face_images = bio_data.get('biometric_data', {}).get('face_images', [])

                for face_img in face_images:
                    image_path = face_img.get('path') or face_img.get('image_path')
                    if image_path and os.path.exists(image_path):
                        try:
                            stored_image = face_recognition.load_image_file(image_path)
                            stored_encodings = face_recognition.face_encodings(stored_image)

                            if stored_encodings:
                                distance = face_recognition.face_distance([stored_encodings[0]], live_encoding)[0]
                                if distance < best_distance:
                                    best_distance = distance
                                    best_match = student['id']
                                    best_student_info = {
                                        'student_id': student['id'],
                                        'reg_no': student['reg_no'],
                                        'name': f"{student['first_name']} {student['last_name']}"
                                    }
                        except Exception as e:
                            continue  # Skip bad images

            except Exception as e:
                continue  # Skip bad student records

        cursor.close()
        conn.close()

        if best_match and best_distance < 0.6:  # Good match threshold
            return {
                "status": "success",
                "student_id": best_match,
                "student_name": best_student_info['name'],
                "student_reg": best_student_info['reg_no'],
                "distance": float(best_distance),
                "confidence": round((1 - best_distance) * 100, 1)
            }
        else:
            return {
                "status": "no_match",
                "message": "No matching face found",
                "best_distance": float(best_distance) if best_match else None
            }

    except Error as e:
        return {"status": "error", "message": f"Database error: {str(e)}"}
    except Exception as e:
        return {"status": "error", "message": f"Unexpected error: {str(e)}"}

if __name__ == "__main__":
    if len(sys.argv) != 2:
        print(json.dumps({"status": "error", "message": "Usage: python simple_face_match.py <live_image_path>"}))
        sys.exit(1)

    live_image_path = sys.argv[1]
    result = match_face(live_image_path)
    print(json.dumps(result))