#!/usr/bin/env python3
"""
Real Face Recognition System
Compares captured face with student photos in database
Uses face_recognition library for accurate face matching
"""

import sys
import json
import warnings

# Suppress all warnings to ensure clean JSON output
warnings.filterwarnings('ignore')

import face_recognition
import numpy as np
import mysql.connector
from PIL import Image
import io
import os

def load_config():
    """Load database configuration from PHP config"""
    config = {
        'host': 'localhost',
        'user': 'root',
        'password': '',
        'database': 'rp_attendance_system'
    }
    return config

def get_face_encoding(image_path):
    """
    Extract face encoding from image file
    Returns: face encoding array or None
    """
    try:
        # Load image
        image = face_recognition.load_image_file(image_path)
        
        # Get face encodings (128-dimensional face descriptor)
        face_encodings = face_recognition.face_encodings(image)
        
        if len(face_encodings) == 0:
            return None, "No face detected in image"
        
        if len(face_encodings) > 1:
            return None, "Multiple faces detected. Please ensure only one person is in frame"
        
        return face_encodings[0], None
        
    except Exception as e:
        return None, f"Error processing image: {str(e)}"

def get_face_encoding_from_blob(blob_data):
    """
    Extract face encoding from BLOB data
    Returns: face encoding array or None
    """
    try:
        # Convert BLOB to image
        image = Image.open(io.BytesIO(blob_data))
        
        # Convert PIL image to numpy array
        image_array = np.array(image)
        
        # Get face encodings
        face_encodings = face_recognition.face_encodings(image_array)
        
        if len(face_encodings) == 0:
            return None
        
        return face_encodings[0]
        
    except Exception as e:
        return None

def compare_faces(captured_image_path, session_id):
    """
    Compare captured face with all students in the session
    Returns: best match student info and confidence
    """
    
    # Get face encoding from captured image
    captured_encoding, error = get_face_encoding(captured_image_path)
    
    if captured_encoding is None:
        return {
            'status': 'error',
            'message': error or 'No face detected',
            'faces_detected': 0
        }
    
    # Connect to database
    try:
        config = load_config()
        conn = mysql.connector.connect(**config)
        cursor = conn.cursor(dictionary=True)
        
        # Get session details
        cursor.execute("""
            SELECT option_id, year_level 
            FROM attendance_sessions 
            WHERE id = %s AND status = 'active'
        """, (session_id,))
        
        session = cursor.fetchone()
        
        if not session:
            return {
                'status': 'error',
                'message': 'Invalid or inactive session'
            }
        
        # Get all students in this session with photos
        cursor.execute("""
            SELECT 
                s.id,
                s.user_id,
                s.reg_no,
                s.student_photos,
                CONCAT(u.first_name, ' ', u.last_name) as name,
                u.first_name,
                u.last_name
            FROM students s
            JOIN users u ON s.user_id = u.id
            WHERE s.option_id = %s 
            AND s.year_level = %s 
            AND s.status = 'active'
            AND s.student_photos IS NOT NULL
        """, (session['option_id'], session['year_level']))
        
        students = cursor.fetchall()
        
        if not students:
            return {
                'status': 'error',
                'message': 'No students with photos found in this class',
                'faces_detected': 1
            }
        
        # Compare captured face with each student's photo
        best_match = None
        best_distance = float('inf')
        matches_found = []
        
        for student in students:
            if not student['student_photos']:
                continue
            
            # Get face encoding from student photo
            student_encoding = get_face_encoding_from_blob(student['student_photos'])
            
            if student_encoding is None:
                continue
            
            # Calculate face distance (lower = better match)
            face_distance = face_recognition.face_distance([student_encoding], captured_encoding)[0]
            
            # Convert distance to confidence percentage (0-100)
            # Distance typically ranges from 0 (perfect match) to 1 (no match)
            # We use 0.6 as threshold (industry standard)
            confidence = max(0, (1 - face_distance) * 100)
            
            matches_found.append({
                'student_id': student['id'],
                'name': student['name'],
                'reg_no': student['reg_no'],
                'distance': float(face_distance),
                'confidence': round(confidence, 2)
            })
            
            # Track best match
            if face_distance < best_distance:
                best_distance = face_distance
                best_match = student
        
        cursor.close()
        conn.close()
        
        # Threshold for face recognition (0.6 is standard, lower = stricter)
        RECOGNITION_THRESHOLD = 0.6
        
        if best_match and best_distance < RECOGNITION_THRESHOLD:
            confidence = round((1 - best_distance) * 100, 2)
            
            return {
                'status': 'success',
                'student_id': best_match['id'],
                'student': {
                    'id': best_match['id'],
                    'name': best_match['name'],
                    'reg_no': best_match['reg_no'],
                    'first_name': best_match['first_name'],
                    'last_name': best_match['last_name']
                },
                'confidence': confidence,
                'face_distance': float(best_distance),
                'faces_detected': 1,
                'matches_checked': len(students),
                'threshold': RECOGNITION_THRESHOLD
            }
        else:
            return {
                'status': 'not_recognized',
                'message': 'No matching face found',
                'faces_detected': 1,
                'matches_checked': len(students),
                'best_distance': float(best_distance) if best_distance != float('inf') else None,
                'threshold': RECOGNITION_THRESHOLD,
                'all_matches': matches_found[:5]  # Top 5 matches for debugging
            }
            
    except mysql.connector.Error as e:
        return {
            'status': 'error',
            'message': f'Database error: {str(e)}'
        }
    except Exception as e:
        return {
            'status': 'error',
            'message': f'Recognition error: {str(e)}'
        }

def main():
    """Main entry point"""
    try:
        if len(sys.argv) < 3:
            print(json.dumps({
                'status': 'error',
                'message': 'Usage: python face_recognition_compare.py <image_path> <session_id>'
            }))
            sys.exit(1)
        
        image_path = sys.argv[1]
        session_id = int(sys.argv[2])
        
        # Validate image file exists
        if not os.path.exists(image_path):
            print(json.dumps({
                'status': 'error',
                'message': 'Image file not found'
            }))
            sys.exit(1)
        
        # Perform face recognition
        result = compare_faces(image_path, session_id)
        
        # Output JSON result (no indentation for clean parsing)
        print(json.dumps(result))
        
    except Exception as e:
        # Catch any unexpected errors and output as JSON
        print(json.dumps({
            'status': 'error',
            'message': f'Unexpected error: {str(e)}',
            'error_type': type(e).__name__
        }))
        sys.exit(1)

if __name__ == '__main__':
    main()
