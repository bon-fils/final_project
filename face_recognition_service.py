#!/usr/bin/env python3
"""
Face Recognition Service for RP Attendance System
Advanced image processing service using OpenCV and face_recognition library
Handles real-time face detection, recognition, and attendance marking
"""

import cv2
import face_recognition
import numpy as np
import json
import base64
import io
from PIL import Image
import os
import sys
import logging
from datetime import datetime
import mysql.connector
from mysql.connector import Error
import redis
import hashlib
from flask import Flask, request, jsonify
from flask_cors import CORS
import threading
import time
from concurrent.futures import ThreadPoolExecutor
import signal
import atexit

# Configuration
class Config:
    # Database settings
    DB_HOST = os.getenv('DB_HOST', 'localhost')
    DB_NAME = os.getenv('DB_NAME', 'rp_attendance_system')
    DB_USER = os.getenv('DB_USER', 'root')
    DB_PASS = os.getenv('DB_PASS', '')

    # Redis settings
    REDIS_HOST = os.getenv('REDIS_HOST', 'localhost')
    REDIS_PORT = int(os.getenv('REDIS_PORT', 6379))
    REDIS_DB = int(os.getenv('REDIS_DB', 0))

    # Face recognition settings
    FACE_TOLERANCE = float(os.getenv('FACE_TOLERANCE', 0.6))
    MIN_FACE_SIZE = int(os.getenv('MIN_FACE_SIZE', 50))
    MAX_FACES_PER_IMAGE = int(os.getenv('MAX_FACES_PER_IMAGE', 1))
    CONFIDENCE_THRESHOLD = float(os.getenv('CONFIDENCE_THRESHOLD', 0.7))

    # Service settings
    HOST = os.getenv('SERVICE_HOST', 'localhost')
    PORT = int(os.getenv('SERVICE_PORT', 5000))
    DEBUG = os.getenv('DEBUG', 'False').lower() == 'true'
    MAX_WORKERS = int(os.getenv('MAX_WORKERS', 4))

    # Image settings
    MAX_IMAGE_SIZE = int(os.getenv('MAX_IMAGE_SIZE', 1024 * 1024))  # 1MB
    SUPPORTED_FORMATS = ['JPEG', 'PNG', 'JPG']

class FaceRecognitionService:
    def __init__(self):
        self.config = Config()
        self.setup_logging()
        self.db_connection = None
        self.redis_client = None
        self.known_face_encodings = {}
        self.known_face_metadata = {}
        self.executor = ThreadPoolExecutor(max_workers=self.config.MAX_WORKERS)
        self.is_running = False

        # Setup signal handlers
        signal.signal(signal.SIGINT, self.shutdown)
        signal.signal(signal.SIGTERM, self.shutdown)
        atexit.register(self.cleanup)

    def setup_logging(self):
        """Setup comprehensive logging"""
        logging.basicConfig(
            level=logging.INFO if not self.config.DEBUG else logging.DEBUG,
            format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
            handlers=[
                logging.FileHandler('logs/face_recognition_service.log'),
                logging.StreamHandler(sys.stdout)
            ]
        )
        self.logger = logging.getLogger('FaceRecognitionService')

    def connect_database(self):
        """Establish database connection"""
        try:
            self.db_connection = mysql.connector.connect(
                host=self.config.DB_HOST,
                database=self.config.DB_NAME,
                user=self.config.DB_USER,
                password=self.config.DB_PASS
            )
            self.logger.info("Database connection established")
            return True
        except Error as e:
            self.logger.error(f"Database connection failed: {e}")
            return False

    def connect_redis(self):
        """Establish Redis connection"""
        try:
            self.redis_client = redis.Redis(
                host=self.config.REDIS_HOST,
                port=self.config.REDIS_PORT,
                db=self.config.REDIS_DB,
                decode_responses=True
            )
            self.redis_client.ping()
            self.logger.info("Redis connection established")
            return True
        except redis.ConnectionError as e:
            self.logger.error(f"Redis connection failed: {e}")
            return False

    def load_known_faces(self):
        """Load known face encodings from database"""
        try:
            cursor = self.db_connection.cursor(dictionary=True)

            # Query students with biometric data
            query = """
                SELECT s.id, s.first_name, s.last_name, s.student_id,
                       s.face_encoding, s.face_image_path, s.department_id, s.year_level
                FROM students s
                WHERE s.face_encoding IS NOT NULL
                AND s.status = 'active'
            """

            cursor.execute(query)
            students = cursor.fetchall()

            self.known_face_encodings = {}
            self.known_face_metadata = {}

            for student in students:
                try:
                    # Decode face encoding from database
                    encoding_data = json.loads(student['face_encoding'])
                    face_encoding = np.array(encoding_data)

                    student_key = f"student_{student['id']}"
                    self.known_face_encodings[student_key] = face_encoding
                    self.known_face_metadata[student_key] = {
                        'id': student['id'],
                        'student_id': student['student_id'],
                        'name': f"{student['first_name']} {student['last_name']}",
                        'department_id': student['department_id'],
                        'year_level': student['year_level'],
                        'face_image_path': student['face_image_path']
                    }

                except (json.JSONDecodeError, ValueError) as e:
                    self.logger.warning(f"Invalid face encoding for student {student['id']}: {e}")
                    continue

            cursor.close()
            self.logger.info(f"Loaded {len(self.known_face_encodings)} known face encodings")

            # Cache the encodings in Redis
            self.cache_face_encodings()

        except Error as e:
            self.logger.error(f"Failed to load known faces: {e}")

    def cache_face_encodings(self):
        """Cache face encodings in Redis for faster access"""
        try:
            cache_key = "face_encodings_cache"
            cache_data = {
                'encodings': {k: v.tolist() for k, v in self.known_face_encodings.items()},
                'metadata': self.known_face_metadata,
                'timestamp': datetime.now().isoformat()
            }

            self.redis_client.setex(
                cache_key,
                3600,  # 1 hour TTL
                json.dumps(cache_data)
            )

            self.logger.info("Face encodings cached in Redis")

        except Exception as e:
            self.logger.error(f"Failed to cache face encodings: {e}")

    def load_cached_encodings(self):
        """Load face encodings from Redis cache"""
        try:
            cache_key = "face_encodings_cache"
            cached_data = self.redis_client.get(cache_key)

            if cached_data:
                data = json.loads(cached_data)
                self.known_face_encodings = {k: np.array(v) for k, v in data['encodings'].items()}
                self.known_face_metadata = data['metadata']
                self.logger.info(f"Loaded {len(self.known_face_encodings)} face encodings from cache")
                return True

        except Exception as e:
            self.logger.error(f"Failed to load cached encodings: {e}")

        return False

    def decode_image(self, image_data):
        """Decode base64 image data to numpy array"""
        try:
            # Remove data URL prefix if present
            if ',' in image_data:
                image_data = image_data.split(',')[1]

            # Decode base64
            image_bytes = base64.b64decode(image_data)

            # Convert to PIL Image
            image = Image.open(io.BytesIO(image_bytes))

            # Validate image format
            if image.format not in self.config.SUPPORTED_FORMATS:
                raise ValueError(f"Unsupported image format: {image.format}")

            # Validate image size
            image_size = len(image_bytes)
            if image_size > self.config.MAX_IMAGE_SIZE:
                raise ValueError(f"Image too large: {image_size} bytes")

            # Convert to RGB if necessary
            if image.mode != 'RGB':
                image = image.convert('RGB')

            # Convert to numpy array
            image_array = np.array(image)

            return image_array

        except Exception as e:
            self.logger.error(f"Image decoding failed: {e}")
            raise ValueError(f"Invalid image data: {str(e)}")

    def detect_faces(self, image_array):
        """Detect faces in image using face_recognition"""
        try:
            # Find face locations
            face_locations = face_recognition.face_locations(image_array)

            # Filter faces by minimum size
            valid_faces = []
            for face_location in face_locations:
                top, right, bottom, left = face_location
                face_height = bottom - top
                face_width = right - left

                if face_height >= self.config.MIN_FACE_SIZE and face_width >= self.config.MIN_FACE_SIZE:
                    valid_faces.append(face_location)

            # Limit number of faces processed
            if len(valid_faces) > self.config.MAX_FACES_PER_IMAGE:
                self.logger.warning(f"Too many faces detected ({len(valid_faces)}), limiting to {self.config.MAX_FACES_PER_IMAGE}")
                valid_faces = valid_faces[:self.config.MAX_FACES_PER_IMAGE]

            return valid_faces

        except Exception as e:
            self.logger.error(f"Face detection failed: {e}")
            return []

    def extract_face_encodings(self, image_array, face_locations):
        """Extract face encodings from detected faces"""
        try:
            face_encodings = face_recognition.face_encodings(image_array, face_locations)
            return face_encodings

        except Exception as e:
            self.logger.error(f"Face encoding extraction failed: {e}")
            return []

    def recognize_faces(self, face_encodings):
        """Compare face encodings against known faces"""
        results = []

        if not self.known_face_encodings:
            self.logger.warning("No known face encodings available for comparison")
            return results

        known_encodings = list(self.known_face_encodings.values())
        known_keys = list(self.known_face_encodings.keys())

        for face_encoding in face_encodings:
            try:
                # Compare against all known faces
                distances = face_recognition.face_distance(known_encodings, face_encoding)

                # Find best match
                min_distance_idx = np.argmin(distances)
                min_distance = distances[min_distance_idx]

                # Calculate confidence (inverse of distance, normalized)
                confidence = max(0, min(1, 1 - min_distance / self.config.FACE_TOLERANCE))

                if confidence >= self.config.CONFIDENCE_THRESHOLD:
                    matched_key = known_keys[min_distance_idx]
                    student_info = self.known_face_metadata[matched_key]

                    results.append({
                        'student_id': student_info['student_id'],
                        'student_name': student_info['name'],
                        'confidence': round(confidence * 100, 2),
                        'distance': round(min_distance, 4),
                        'metadata': student_info
                    })
                else:
                    results.append({
                        'status': 'unknown_face',
                        'confidence': round(confidence * 100, 2),
                        'distance': round(min_distance, 4)
                    })

            except Exception as e:
                self.logger.error(f"Face recognition comparison failed: {e}")
                results.append({
                    'status': 'recognition_error',
                    'error': str(e)
                })

        return results

    def process_attendance_image(self, image_data, session_data=None):
        """Main method to process attendance image"""
        start_time = time.time()

        try:
            # Decode image
            image_array = self.decode_image(image_data)

            # Detect faces
            face_locations = self.detect_faces(image_array)

            if not face_locations:
                return {
                    'status': 'no_faces_detected',
                    'message': 'No faces detected in the image',
                    'processing_time': time.time() - start_time
                }

            if len(face_locations) > self.config.MAX_FACES_PER_IMAGE:
                return {
                    'status': 'too_many_faces',
                    'message': f'Too many faces detected ({len(face_locations)}). Maximum allowed: {self.config.MAX_FACES_PER_IMAGE}',
                    'processing_time': time.time() - start_time
                }

            # Extract face encodings
            face_encodings = self.extract_face_encodings(image_array, face_locations)

            if not face_encodings:
                return {
                    'status': 'encoding_failed',
                    'message': 'Failed to extract face encodings',
                    'processing_time': time.time() - start_time
                }

            # Recognize faces
            recognition_results = self.recognize_faces(face_encodings)

            # Process results
            successful_recognitions = [r for r in recognition_results if 'student_id' in r]

            result = {
                'status': 'success' if successful_recognitions else 'no_matches',
                'faces_detected': len(face_locations),
                'faces_recognized': len(successful_recognitions),
                'results': recognition_results,
                'processing_time': round(time.time() - start_time, 3)
            }

            if successful_recognitions:
                result['message'] = f"Recognized {len(successful_recognitions)} face(s)"
                # Mark attendance in database
                self.mark_attendance(successful_recognitions, session_data)
            else:
                result['message'] = "No faces matched known students"

            return result

        except Exception as e:
            self.logger.error(f"Image processing failed: {e}")
            return {
                'status': 'error',
                'message': f'Image processing failed: {str(e)}',
                'processing_time': time.time() - start_time
            }

    def mark_attendance(self, recognition_results, session_data=None):
        """Mark attendance in database"""
        try:
            cursor = self.db_connection.cursor()

            for result in recognition_results:
                if 'student_id' in result:
                    # Insert attendance record
                    query = """
                        INSERT INTO attendance_records
                        (student_id, session_id, biometric_method, confidence_score,
                         status, recorded_at, metadata)
                        VALUES (%s, %s, 'face_recognition', %s, 'present', NOW(), %s)
                        ON DUPLICATE KEY UPDATE
                        confidence_score = GREATEST(confidence_score, VALUES(confidence_score)),
                        recorded_at = NOW()
                    """

                    metadata = json.dumps({
                        'confidence': result['confidence'],
                        'distance': result.get('distance'),
                        'processing_time': result.get('processing_time'),
                        'session_data': session_data
                    })

                    cursor.execute(query, (
                        result['student_id'],
                        session_data.get('session_id') if session_data else None,
                        result['confidence'],
                        metadata
                    ))

            self.db_connection.commit()
            cursor.close()

            self.logger.info(f"Marked attendance for {len(recognition_results)} students")

        except Error as e:
            self.logger.error(f"Failed to mark attendance: {e}")
            if self.db_connection:
                self.db_connection.rollback()

    def get_service_stats(self):
        """Get service statistics"""
        return {
            'known_faces_count': len(self.known_face_encodings),
            'service_uptime': time.time() - getattr(self, 'start_time', time.time()),
            'config': {
                'face_tolerance': self.config.FACE_TOLERANCE,
                'min_face_size': self.config.MIN_FACE_SIZE,
                'confidence_threshold': self.config.CONFIDENCE_THRESHOLD,
                'max_faces_per_image': self.config.MAX_FACES_PER_IMAGE
            }
        }

    def reload_face_data(self):
        """Reload face encodings from database"""
        self.logger.info("Reloading face data...")
        self.load_known_faces()
        return {'status': 'success', 'message': f'Reloaded {len(self.known_face_encodings)} face encodings'}

    def shutdown(self, signum=None, frame=None):
        """Graceful shutdown"""
        self.logger.info("Shutting down face recognition service...")
        self.is_running = False
        self.cleanup()

    def cleanup(self):
        """Cleanup resources"""
        if self.executor:
            self.executor.shutdown(wait=True)

        if self.db_connection:
            self.db_connection.close()

        if self.redis_client:
            self.redis_client.close()

        self.logger.info("Face recognition service shut down")

# Flask Application
app = Flask(__name__)
CORS(app)

# Global service instance
service = FaceRecognitionService()

@app.route('/health', methods=['GET'])
def health_check():
    """Health check endpoint"""
    return jsonify({
        'status': 'healthy',
        'timestamp': datetime.now().isoformat(),
        'stats': service.get_service_stats()
    })

@app.route('/recognize', methods=['POST'])
def recognize_face():
    """Face recognition endpoint"""
    try:
        data = request.get_json()

        if not data or 'image' not in data:
            return jsonify({
                'status': 'error',
                'message': 'Missing image data'
            }), 400

        image_data = data['image']
        session_data = data.get('session_data', {})

        # Process image asynchronously
        future = service.executor.submit(service.process_attendance_image, image_data, session_data)
        result = future.result(timeout=30)  # 30 second timeout

        return jsonify(result)

    except Exception as e:
        service.logger.error(f"Recognition endpoint error: {e}")
        return jsonify({
            'status': 'error',
            'message': 'Face recognition failed',
            'error': str(e)
        }), 500

@app.route('/reload-faces', methods=['POST'])
def reload_faces():
    """Reload face encodings endpoint"""
    try:
        result = service.reload_face_data()
        return jsonify(result)

    except Exception as e:
        service.logger.error(f"Reload faces endpoint error: {e}")
        return jsonify({
            'status': 'error',
            'message': 'Failed to reload face data',
            'error': str(e)
        }), 500

@app.route('/stats', methods=['GET'])
def get_stats():
    """Get service statistics"""
    try:
        stats = service.get_service_stats()
        return jsonify({
            'status': 'success',
            'stats': stats
        })

    except Exception as e:
        service.logger.error(f"Stats endpoint error: {e}")
        return jsonify({
            'status': 'error',
            'message': 'Failed to get statistics',
            'error': str(e)
        }), 500

def main():
    """Main application entry point"""
    service.start_time = time.time()

    # Initialize connections
    if not service.connect_database():
        service.logger.error("Failed to connect to database. Exiting.")
        sys.exit(1)

    if not service.connect_redis():
        service.logger.warning("Failed to connect to Redis. Continuing without cache.")

    # Load face encodings
    if not service.load_cached_encodings():
        service.load_known_faces()

    service.is_running = True
    service.logger.info(f"Face Recognition Service starting on {service.config.HOST}:{service.config.PORT}")

    try:
        app.run(
            host=service.config.HOST,
            port=service.config.PORT,
            debug=service.config.DEBUG,
            threaded=True
        )
    except KeyboardInterrupt:
        service.logger.info("Service interrupted by user")
    except Exception as e:
        service.logger.error(f"Service error: {e}")
    finally:
        service.cleanup()

if __name__ == '__main__':
    main()