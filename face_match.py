#!/usr/bin/env python3
"""
Face Recognition Script for RP Attendance System
Simplified version that compares a captured image with stored student photos
"""

import sys
import os
import face_recognition
import pymysql
import json

def match_face(live_image_path):
    """Match face from live image with stored student photos"""
    # Load live image
    try:
        live_image = face_recognition.load_image_file(live_image_path)
        live_encodings = face_recognition.face_encodings(live_image, model='large', num_jitters=10)
        if not live_encodings:
            return {"status": "error", "message": "No face detected in live image"}
        live_encoding = live_encodings[0]
    except Exception as e:
        return {"status": "error", "message": f"Error loading live image: {str(e)}"}

    # Database connection
    try:
        conn = pymysql.connect(
            host='localhost',
            user='root',
            password='',
            database='rp_attendance_system'
        )
        cursor = conn.cursor()
    except Exception as e:
        return {"status": "error", "message": f"Database connection failed: {str(e)}"}

    try:
        # Get all student photos
        cursor.execute("SELECT student_id, photo_path FROM student_photos WHERE is_primary = 1")
        photos = cursor.fetchall()

        best_match = None
        best_distance = 1.0  # Max distance for match

        for student_id, photo_path in photos:
            if photo_path and os.path.exists(photo_path):
                try:
                    stored_image = face_recognition.load_image_file(photo_path)
                    stored_encodings = face_recognition.face_encodings(stored_image, model='small', num_jitters=1)
                    if stored_encodings:
                        distance = face_recognition.face_distance([stored_encodings[0]], live_encoding)[0]
                        if distance < best_distance:  # Find best match
                            best_distance = distance
                            best_match = student_id
                except Exception as e:
                    continue  # Skip bad images

        # Get student details if match found
        student_info = None
        if best_match:
            cursor.execute("SELECT id, reg_no, first_name, last_name FROM students WHERE id = %s", (best_match,))
            student_data = cursor.fetchone()
            if student_data:
                student_info = {
                    "student_id": student_data[0],
                    "reg_no": student_data[1],
                    "name": f"{student_data[2]} {student_data[3]}"
                }

        cursor.close()
        conn.close()

        if best_match and student_info:
            confidence = round((1 - best_distance) * 100, 1)
            return {
                "status": "success",
                "student_id": student_info["student_id"],
                "student_name": student_info["name"],
                "student_reg": student_info["reg_no"],
                "distance": round(best_distance, 4),
                "confidence": confidence,
                "live_faces": len(live_encodings),
                "stored_count": len(photos)
            }
        else:
            return {
                "status": "no_match",
                "message": "No matching face found in database",
                "live_faces": len(live_encodings),
                "stored_count": len(photos)
            }

    except Exception as e:
        cursor.close()
        conn.close()
        return {"status": "error", "message": f"Face matching error: {str(e)}"}

if __name__ == "__main__":
    if len(sys.argv) != 2:
        print(json.dumps({"status": "error", "message": "Usage: python face_match.py <live_image_path>"}))
        sys.exit(1)

    live_image_path = sys.argv[1]
    result = match_face(live_image_path)
    print(json.dumps(result))