#!/usr/bin/env python3
"""
Face Recognition Service for RP Attendance System
Uses face_recognition library for accurate face detection and comparison
"""

import sys
import os
import json
import base64
import tempfile
import logging
from flask import Flask, request, jsonify
from flask_cors import CORS
import face_recognition
import numpy as np
from PIL import Image
import io
import time
from datetime import datetime

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler('face_recognition.log'),
        logging.StreamHandler(sys.stdout)
    ]
)
logger = logging.getLogger(__name__)

app = Flask(__name__)
CORS(app)  # Enable CORS for PHP requests

# Configuration
class Config:
    UPLOAD_FOLDER = 'uploads/students/'
    MAX_IMAGE_SIZE = 10 * 1024 * 1024  # 10MB
    FACE_RECOGNITION_TOLERANCE = 0.35  # Lower = more strict matching (tightened from 0.4)
    MIN_FACE_SIZE = 80  # Minimum face size in pixels (increased for better quality)
    CONFIDENCE_THRESHOLD_HIGH = 0.85  # Increased from 0.8 for stricter matching
    CONFIDENCE_THRESHOLD_MEDIUM = 0.65  # Increased from 0.6
    MIN_FACE_RATIO = 0.05  # Minimum face size as percentage of image (5%)
    CENTER_TOLERANCE = 0.3  # Maximum allowed face offset from center (30%)
    CONFIDENCE_MARGIN = 0.15  # Minimum confidence difference from second best match (15%)
    NUM_JITTERS = 3  # Number of times to jitter image for better encoding
    UPSAMPLE_FACTOR = 1  # How many times to upsample image for face detection

config = Config()

# Global face encodings cache
face_encodings_cache = {}
cache_timestamp = 0
CACHE_DURATION = 300  # 5 minutes

def load_student_faces():
    """
    Load and cache face encodings for all students with photos
    Supports multiple images per student from student_images table
    """
    global face_encodings_cache, cache_timestamp

    # Check if cache is still valid
    if time.time() - cache_timestamp < CACHE_DURATION and face_encodings_cache:
        return face_encodings_cache

    logger.info("Loading student face encodings...")
    encodings = {}

    try:
        # Connect to database to get student photos
        import mysql.connector
        from mysql.connector import Error

        # Database connection (configure these)
        db_config = {
            'host': os.getenv('DB_HOST', 'localhost'),
            'database': os.getenv('DB_NAME', 'rp_attendance_system'),
            'user': os.getenv('DB_USER', 'root'),
            'password': os.getenv('DB_PASS', ''),
            'charset': 'utf8mb4',
            'collation': 'utf8mb4_unicode_ci'
        }

        conn = mysql.connector.connect(**db_config)
        cursor = conn.cursor(dictionary=True)

        # Get students with photos from students table (including JSON biometric data)
        query = """
        SELECT DISTINCT s.id, s.reg_no, s.first_name, s.last_name, s.student_photos
        FROM students s
        WHERE s.student_photos IS NOT NULL AND s.student_photos != ''
        AND s.status = 'active'
        """
        cursor.execute(query)
        students = cursor.fetchall()

        logger.info(f"Found {len(students)} students with biometric data")

        for student in students:
            try:
                student_id = student['id']
                student_encodings = []

                # Parse biometric data from JSON
                biometric_data = student.get('student_photos')
                if biometric_data:
                    try:
                        bio_json = json.loads(biometric_data) if isinstance(biometric_data, str) else biometric_data
                        face_images = bio_json.get('biometric_data', {}).get('face_images', [])

                        for face_img in face_images:
                            image_path = face_img.get('image_path')
                            if image_path:
                                # Handle both relative and absolute paths
                                if not os.path.isabs(image_path):
                                    full_path = os.path.join(os.getcwd(), image_path)
                                else:
                                    full_path = image_path

                                if os.path.exists(full_path):
                                    try:
                                        # Load and encode face
                                        image = face_recognition.load_image_file(full_path)
                                        face_encodings = face_recognition.face_encodings(image)

                                        if face_encodings:
                                            student_encodings.append(face_encodings[0])
                                            logger.info(f"Loaded face encoding for student {student['reg_no']} from {full_path}")
                                    except Exception as e:
                                        logger.warning(f"Failed to process image {full_path} for student {student['reg_no']}: {str(e)}")
                                        continue
                                else:
                                    logger.warning(f"Image file not found: {full_path} for student {student['reg_no']}")
                    except json.JSONDecodeError as e:
                        logger.warning(f"Invalid JSON in student_photos for student {student['reg_no']}: {str(e)}")
                        continue

                # If we have encodings for this student, store them
                if student_encodings:
                    # Use the first encoding as primary, but store all for potential future use
                    encodings[student_id] = {
                        'encoding': student_encodings[0].tolist(),  # Convert numpy array to list
                        'all_encodings': [enc.tolist() for enc in student_encodings],  # Store all encodings
                        'student_id': student_id,
                        'reg_no': student['reg_no'],
                        'name': f"{student['first_name']} {student['last_name']}",
                        'photo_count': len(student_encodings)
                    }
                    logger.info(f"Loaded {len(student_encodings)} face encodings for student {student['reg_no']}")

            except Exception as e:
                logger.error(f"Error loading faces for student {student['reg_no']}: {str(e)}")
                continue

        cursor.close()
        conn.close()

    except Error as e:
        logger.error(f"Database error: {str(e)}")
    except Exception as e:
        logger.error(f"Error loading student faces: {str(e)}")

    face_encodings_cache = encodings
    cache_timestamp = time.time()

    logger.info(f"Loaded face encodings for {len(encodings)} students")
    return encodings

def process_image_data(image_data):
    """
    Process base64 image data and return PIL Image
    """
    try:
        # Remove data URL prefix if present
        if image_data.startswith('data:image'):
            image_data = image_data.split(',')[1]

        # Decode base64
        image_bytes = base64.b64decode(image_data)

        # Convert to PIL Image
        image = Image.open(io.BytesIO(image_bytes))

        # Convert to RGB if necessary
        if image.mode != 'RGB':
            image = image.convert('RGB')

        return image

    except Exception as e:
        logger.error(f"Error processing image data: {str(e)}")
        raise

def preprocess_image(image):
    """
    Preprocess image for better face recognition
    """
    try:
        # Convert to RGB if necessary
        if image.mode != 'RGB':
            image = image.convert('RGB')

        # Enhance contrast and brightness for better recognition
        from PIL import ImageEnhance
        enhancer = ImageEnhance.Contrast(image)
        image = enhancer.enhance(1.2)  # Increase contrast by 20%

        enhancer = ImageEnhance.Brightness(image)
        image = enhancer.enhance(1.1)  # Increase brightness by 10%

        return image
    except Exception as e:
        logger.warning(f"Image preprocessing failed: {str(e)}")
        return image

def recognize_face(captured_image, student_encodings, session_filter=None):
    """
    Enhanced face recognition with improved accuracy and multiple validation checks
    """
    try:
        # Preprocess the captured image
        captured_image = preprocess_image(captured_image)

        # Convert PIL to numpy array
        captured_array = np.array(captured_image)

        # Find faces in captured image using CNN model for better accuracy
        face_locations = face_recognition.face_locations(captured_array, model="cnn", number_of_times_to_upsample=config.UPSAMPLE_FACTOR)
        face_encodings = face_recognition.face_encodings(captured_array, face_locations, num_jitters=config.NUM_JITTERS)

        if not face_encodings:
            return {
                'recognized': False,
                'message': 'No faces detected in captured image. Please ensure good lighting and face visibility.',
                'faces_detected': 0
            }

        if len(face_encodings) > 1:
            return {
                'recognized': False,
                'message': 'Multiple faces detected. Please ensure only one person is in frame.',
                'faces_detected': len(face_encodings)
            }

        captured_encoding = face_encodings[0]
        best_match = None
        best_distance = float('inf')
        all_matches = []
        second_best_distance = float('inf')

        # Compare with student encodings using multiple validation checks
        for student_id, student_data in student_encodings.items():
            # Skip if session filter is provided and student not in session
            if session_filter and student_id not in session_filter:
                continue

            try:
                # Use all encodings for this student for better recognition
                student_all_encodings = student_data.get('all_encodings', [student_data['encoding']])

                # Find the best match among all encodings for this student
                student_best_distance = float('inf')
                student_best_encoding = None

                for encoding_data in student_all_encodings:
                    stored_encoding = np.array(encoding_data)
                    distance = face_recognition.face_distance([stored_encoding], captured_encoding)[0]

                    if distance < student_best_distance:
                        student_best_distance = distance
                        student_best_encoding = encoding_data

                # Use the best distance for this student
                distance = student_best_distance

                confidence = 1 - distance

                all_matches.append({
                    'student_id': student_id,
                    'name': student_data['name'],
                    'reg_no': student_data['reg_no'],
                    'distance': float(distance),
                    'confidence': confidence,
                    'photo_count': student_data.get('photo_count', 1)
                })

                # Track best and second best matches for validation
                if distance < best_distance:
                    second_best_distance = best_distance
                    best_distance = distance
                    best_match = student_data
                elif distance < second_best_distance:
                    second_best_distance = distance

            except Exception as e:
                logger.error(f"Error comparing with student {student_id}: {str(e)}")
                continue

        # Sort matches by confidence
        all_matches.sort(key=lambda x: x['confidence'], reverse=True)

        if not best_match:
            return {
                'recognized': False,
                'message': 'No matching faces found in database. Student may not be registered.',
                'faces_detected': len(face_encodings)
            }

        confidence = 1 - best_distance

        # Enhanced validation: Check if match is significantly better than second best
        if second_best_distance < float('inf'):
            second_best_confidence = 1 - second_best_distance
            confidence_difference = confidence - second_best_confidence

            if confidence_difference < config.CONFIDENCE_MARGIN:
                logger.warning(f"Low confidence margin: {confidence_difference:.3f} (required: {config.CONFIDENCE_MARGIN})")
                return {
                    'recognized': False,
                    'message': f'Face match uncertain. Please try again with better lighting. Confidence: {confidence:.1%}',
                    'faces_detected': len(face_encodings),
                    'confidence': round(confidence * 100, 1),
                    'confidence_level': 'uncertain'
                }

        # Determine confidence level with stricter thresholds
        if confidence >= config.CONFIDENCE_THRESHOLD_HIGH:
            confidence_level = 'high'
            auto_mark = True
        elif confidence >= config.CONFIDENCE_THRESHOLD_MEDIUM:
            confidence_level = 'medium'
            auto_mark = True  # Allow auto-mark for medium confidence with validation
        else:
            confidence_level = 'low'
            auto_mark = False

        # Additional validation for face quality
        face_location = face_locations[0]
        face_width = face_location[2] - face_location[0]
        face_height = face_location[3] - face_location[1]
        face_area = face_width * face_height
        image_area = captured_array.shape[0] * captured_array.shape[1]

        # Check if face is large enough
        face_ratio = face_area / image_area
        if face_ratio < config.MIN_FACE_RATIO:
            logger.warning(f"Face too small: {face_ratio:.3f} (minimum: {config.MIN_FACE_RATIO})")
            return {
                'recognized': False,
                'message': 'Face too small or too far from camera. Please move closer.',
                'faces_detected': len(face_encodings),
                'face_ratio': round(face_ratio * 100, 1)
            }

        # Check face position (should be reasonably centered)
        face_center_x = (face_location[0] + face_location[2]) / 2
        face_center_y = (face_location[1] + face_location[3]) / 2
        image_center_x = captured_array.shape[1] / 2
        image_center_y = captured_array.shape[0] / 2

        # Allow some tolerance for face positioning
        x_offset = abs(face_center_x - image_center_x) / image_center_x
        y_offset = abs(face_center_y - image_center_y) / image_center_y

        if x_offset > config.CENTER_TOLERANCE or y_offset > config.CENTER_TOLERANCE:
            logger.warning(f"Face not centered: x_offset={x_offset:.2f}, y_offset={y_offset:.2f}")

        return {
            'recognized': confidence >= config.CONFIDENCE_THRESHOLD_MEDIUM,
            'student_id': best_match['student_id'],
            'student_name': best_match['name'],
            'student_reg': best_match['reg_no'],
            'confidence': round(confidence * 100, 1),
            'confidence_level': confidence_level,
            'auto_mark': auto_mark,
            'distance': float(best_distance),
            'faces_detected': len(face_encodings),
            'face_ratio': round(face_ratio * 100, 1),
            'top_matches': all_matches[:3],  # Return top 3 matches
            'validation': {
                'face_size_ok': face_ratio >= config.MIN_FACE_RATIO,
                'confidence_margin_ok': confidence_difference >= config.CONFIDENCE_MARGIN if 'confidence_difference' in locals() else True,
                'face_centered': x_offset <= config.CENTER_TOLERANCE and y_offset <= config.CENTER_TOLERANCE
            }
        }

    except Exception as e:
        logger.error(f"Error in face recognition: {str(e)}")
        return {
            'recognized': False,
            'message': f'Face recognition error: {str(e)}',
            'faces_detected': 0
        }

@app.route('/health', methods=['GET'])
def health_check():
    """Health check endpoint"""
    return jsonify({
        'status': 'healthy',
        'timestamp': datetime.now().isoformat(),
        'cached_encodings': len(face_encodings_cache)
    })

@app.route('/recognize', methods=['POST'])
def recognize():
    """Main face recognition endpoint"""
    try:
        # Get request data
        image_data = request.form.get('image_data')
        session_id = request.form.get('session_id')
        department_id = request.form.get('department_id')
        option_id = request.form.get('option_id')

        if not image_data:
            return jsonify({
                'status': 'error',
                'message': 'No image data provided',
                'recognized': False
            }), 400

        logger.info(f"Processing face recognition request for session {session_id}")

        # Load student faces
        student_encodings = load_student_faces()

        if not student_encodings:
            return jsonify({
                'status': 'error',
                'message': 'No student face data available',
                'recognized': False
            }), 503

        # Process captured image
        captured_image = process_image_data(image_data)

        # Filter students by session if provided
        session_filter = None
        if session_id:
            try:
                # Get students for this session from database
                import mysql.connector
                conn = mysql.connector.connect(
                    host=os.getenv('DB_HOST', 'localhost'),
                    database=os.getenv('DB_NAME', 'rp_attendance_system'),
                    user=os.getenv('DB_USER', 'root'),
                    password=os.getenv('DB_PASS', '')
                )
                cursor = conn.cursor()

                query = """
                SELECT DISTINCT s.id
                FROM students s
                INNER JOIN attendance_sessions sess ON (
                    (sess.department_id = s.department_id) OR
                    (sess.option_id = s.option_id)
                )
                WHERE sess.id = %s
                """
                cursor.execute(query, (session_id,))
                session_students = [row[0] for row in cursor.fetchall()]
                cursor.close()
                conn.close()

                if session_students:
                    session_filter = session_students
                    logger.info(f"Filtered to {len(session_filter)} students for session {session_id}")

            except Exception as e:
                logger.warning(f"Could not filter by session: {str(e)}")

        # Perform face recognition
        result = recognize_face(captured_image, student_encodings, session_filter)

        # Add metadata
        result.update({
            'status': 'success',
            'timestamp': datetime.now().isoformat(),
            'session_id': session_id,
            'total_students': len(student_encodings)
        })

        logger.info(f"Face recognition result: {result['recognized']} (confidence: {result.get('confidence', 0)}%)")

        return jsonify(result)

    except Exception as e:
        logger.error(f"Face recognition request failed: {str(e)}")
        return jsonify({
            'status': 'error',
            'message': str(e),
            'recognized': False,
            'timestamp': datetime.now().isoformat()
        }), 500

@app.route('/reload_cache', methods=['POST'])
def reload_cache():
    """Force reload of face encodings cache"""
    global face_encodings_cache, cache_timestamp
    face_encodings_cache = {}
    cache_timestamp = 0

    load_student_faces()

    return jsonify({
        'status': 'success',
        'message': 'Face encodings cache reloaded',
        'cached_encodings': len(face_encodings_cache),
        'timestamp': datetime.now().isoformat()
    })

@app.route('/stats', methods=['GET'])
def get_stats():
    """Get service statistics"""
    return jsonify({
        'status': 'success',
        'cached_encodings': len(face_encodings_cache),
        'cache_age': time.time() - cache_timestamp,
        'cache_duration': CACHE_DURATION,
        'config': {
            'tolerance': config.FACE_RECOGNITION_TOLERANCE,
            'min_face_size': config.MIN_FACE_SIZE,
            'confidence_high': config.CONFIDENCE_THRESHOLD_HIGH,
            'confidence_medium': config.CONFIDENCE_THRESHOLD_MEDIUM,
            'min_face_ratio': config.MIN_FACE_RATIO,
            'center_tolerance': config.CENTER_TOLERANCE,
            'confidence_margin': config.CONFIDENCE_MARGIN,
            'num_jitters': config.NUM_JITTERS,
            'upsample_factor': config.UPSAMPLE_FACTOR
        },
        'timestamp': datetime.now().isoformat()
    })

if __name__ == '__main__':
    # Load initial face encodings
    load_student_faces()

    # Start Flask app
    port = int(os.getenv('FACE_RECOGNITION_PORT', 5000))
    debug = os.getenv('FLASK_DEBUG', 'False').lower() == 'true'

    logger.info(f"Starting Face Recognition Service on port {port}")
    app.run(host='0.0.0.0', port=port, debug=debug, threaded=True)