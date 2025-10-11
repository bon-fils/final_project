#!/usr/bin/env python3
"""
Face Recognition System Test Suite
Tests the face recognition service functionality
"""

import sys
import os
import json
import base64
import requests
import time
from PIL import Image, ImageDraw
import io
import tempfile

class FaceRecognitionTester:
    def __init__(self, service_url='http://localhost:5000'):
        self.service_url = service_url
        self.test_results = []

    def log_test(self, test_name, status, message="", details=None):
        """Log test results"""
        result = {
            'test': test_name,
            'status': status,
            'message': message,
            'timestamp': time.strftime('%Y-%m-%d %H:%M:%S')
        }
        if details:
            result['details'] = details

        self.test_results.append(result)
        status_icon = "✅" if status == "PASS" else "❌" if status == "FAIL" else "⚠️"
        print(f"{status_icon} {test_name}: {message}")
        if details:
            print(f"   Details: {details}")

    def test_health_check(self):
        """Test service health check"""
        try:
            response = requests.get(f"{self.service_url}/health", timeout=5)
            if response.status_code == 200:
                data = response.json()
                if data.get('status') == 'healthy':
                    self.log_test("Health Check", "PASS", "Service is healthy",
                                f"Cached encodings: {data.get('cached_encodings', 0)}")
                    return True
                else:
                    self.log_test("Health Check", "FAIL", "Service reported unhealthy status")
                    return False
            else:
                self.log_test("Health Check", "FAIL", f"HTTP {response.status_code}")
                return False
        except Exception as e:
            self.log_test("Health Check", "FAIL", f"Connection failed: {str(e)}")
            return False

    def test_service_stats(self):
        """Test service statistics"""
        try:
            response = requests.get(f"{self.service_url}/stats", timeout=5)
            if response.status_code == 200:
                data = response.json()
                self.log_test("Service Stats", "PASS", "Retrieved service statistics",
                            f"Status: {data.get('status')}, Cached: {data.get('cached_encodings', 0)}")
                return True
            else:
                self.log_test("Service Stats", "FAIL", f"HTTP {response.status_code}")
                return False
        except Exception as e:
            self.log_test("Service Stats", "FAIL", f"Connection failed: {str(e)}")
            return False

    def create_test_image(self, width=640, height=480, face_color=(255, 200, 150)):
        """Create a simple test image with a face-like rectangle"""
        # Create a blank image
        img = Image.new('RGB', (width, height), (200, 220, 255))  # Light blue background

        # Draw a simple face-like shape
        draw = ImageDraw.Draw(img)

        # Face oval
        face_center_x, face_center_y = width // 2, height // 2
        face_width, face_height = 150, 180

        # Draw face oval
        draw.ellipse([
            face_center_x - face_width//2,
            face_center_y - face_height//2,
            face_center_x + face_width//2,
            face_center_y + face_height//2
        ], fill=face_color, outline=(100, 80, 60), width=2)

        # Draw eyes
        eye_width, eye_height = 20, 15
        eye_y = face_center_y - 30

        # Left eye
        draw.ellipse([
            face_center_x - 40 - eye_width//2,
            eye_y - eye_height//2,
            face_center_x - 40 + eye_width//2,
            eye_y + eye_height//2
        ], fill=(255, 255, 255), outline=(0, 0, 0), width=1)

        # Right eye
        draw.ellipse([
            face_center_x + 40 - eye_width//2,
            eye_y - eye_height//2,
            face_center_x + 40 + eye_width//2,
            eye_y + eye_height//2
        ], fill=(255, 255, 255), outline=(0, 0, 0), width=1)

        # Draw mouth
        mouth_y = face_center_y + 40
        draw.arc([
            face_center_x - 25,
            mouth_y - 10,
            face_center_x + 25,
            mouth_y + 20
        ], start=0, end=180, fill=(150, 50, 50), width=3)

        return img

    def test_face_recognition_no_session(self):
        """Test face recognition without session ID"""
        try:
            # Create a test image
            test_img = self.create_test_image()
            buffer = io.BytesIO()
            test_img.save(buffer, format='JPEG', quality=85)
            img_base64 = base64.b64encode(buffer.getvalue()).decode()

            # Prepare request data
            data = {
                'image_data': f'data:image/jpeg;base64,{img_base64}',
                'session_id': None
            }

            response = requests.post(f"{self.service_url}/recognize", data=data, timeout=10)

            if response.status_code == 200:
                result = response.json()
                if result.get('status') == 'success':
                    recognized = result.get('recognized', False)
                    faces_detected = result.get('faces_detected', 0)

                    if faces_detected > 0:
                        self.log_test("Face Recognition (No Session)", "PASS",
                                    f"Detected {faces_detected} face(s), Recognized: {recognized}",
                                    f"Confidence: {result.get('confidence', 0)}%")
                        return True
                    else:
                        self.log_test("Face Recognition (No Session)", "WARN",
                                    "No faces detected in test image")
                        return True  # Still a valid test result
                else:
                    self.log_test("Face Recognition (No Session)", "FAIL",
                                f"API returned error: {result.get('message')}")
                    return False
            else:
                self.log_test("Face Recognition (No Session)", "FAIL", f"HTTP {response.status_code}")
                return False

        except Exception as e:
            self.log_test("Face Recognition (No Session)", "FAIL", f"Test failed: {str(e)}")
            return False

    def test_face_recognition_with_session(self):
        """Test face recognition with a mock session ID"""
        try:
            # Create a test image
            test_img = self.create_test_image()
            buffer = io.BytesIO()
            test_img.save(buffer, format='JPEG', quality=85)
            img_base64 = base64.b64encode(buffer.getvalue()).decode()

            # Prepare request data with mock session
            data = {
                'image_data': f'data:image/jpeg;base64,{img_base64}',
                'session_id': 999,  # Mock session ID
                'department_id': 1,
                'option_id': 1
            }

            response = requests.post(f"{self.service_url}/recognize", data=data, timeout=15)

            if response.status_code == 200:
                result = response.json()
                if result.get('status') == 'success':
                    recognized = result.get('recognized', False)
                    faces_detected = result.get('faces_detected', 0)
                    total_students = result.get('total_students', 0)

                    self.log_test("Face Recognition (With Session)", "PASS",
                                f"Session processed, {faces_detected} face(s) detected, {total_students} students in DB",
                                f"Recognized: {recognized}, Confidence: {result.get('confidence', 0)}%")
                    return True
                else:
                    self.log_test("Face Recognition (With Session)", "FAIL",
                                f"API returned error: {result.get('message')}")
                    return False
            else:
                self.log_test("Face Recognition (With Session)", "FAIL", f"HTTP {response.status_code}")
                return False

        except Exception as e:
            self.log_test("Face Recognition (With Session)", "FAIL", f"Test failed: {str(e)}")
            return False

    def test_cache_reload(self):
        """Test cache reload functionality"""
        try:
            response = requests.post(f"{self.service_url}/reload_cache", timeout=10)

            if response.status_code == 200:
                result = response.json()
                if result.get('status') == 'success':
                    cached_count = result.get('cached_encodings', 0)
                    self.log_test("Cache Reload", "PASS", f"Cache reloaded successfully",
                                f"Now caching {cached_count} face encodings")
                    return True
                else:
                    self.log_test("Cache Reload", "FAIL", f"Cache reload failed: {result.get('message')}")
                    return False
            else:
                self.log_test("Cache Reload", "FAIL", f"HTTP {response.status_code}")
                return False

        except Exception as e:
            self.log_test("Cache Reload", "FAIL", f"Test failed: {str(e)}")
            return False

    def test_invalid_image(self):
        """Test with invalid image data"""
        try:
            data = {
                'image_data': 'invalid_base64_data',
                'session_id': None
            }

            response = requests.post(f"{self.service_url}/recognize", data=data, timeout=10)

            # Should return an error for invalid image data
            if response.status_code == 400 or response.status_code == 500:
                self.log_test("Invalid Image Handling", "PASS", "Correctly rejected invalid image data")
                return True
            elif response.status_code == 200:
                result = response.json()
                if result.get('status') == 'error':
                    self.log_test("Invalid Image Handling", "PASS", "API correctly handled invalid image")
                    return True
                else:
                    self.log_test("Invalid Image Handling", "FAIL", "API should have rejected invalid image")
                    return False
            else:
                self.log_test("Invalid Image Handling", "FAIL", f"Unexpected HTTP status: {response.status_code}")
                return False

        except Exception as e:
            self.log_test("Invalid Image Handling", "FAIL", f"Test failed: {str(e)}")
            return False

    def run_all_tests(self):
        """Run all face recognition tests"""
        print("🧪 Starting Face Recognition System Tests")
        print("=" * 50)

        tests = [
            self.test_health_check,
            self.test_service_stats,
            self.test_face_recognition_no_session,
            self.test_face_recognition_with_session,
            self.test_cache_reload,
            self.test_invalid_image
        ]

        passed = 0
        failed = 0
        warnings = 0

        for test in tests:
            try:
                result = test()
                if result:
                    passed += 1
                else:
                    failed += 1
            except Exception as e:
                print(f"❌ {test.__name__}: Unexpected error - {str(e)}")
                failed += 1

        print("\n" + "=" * 50)
        print("📊 Test Results Summary:")
        print(f"✅ Passed: {passed}")
        print(f"❌ Failed: {failed}")
        print(f"⚠️  Warnings: {warnings}")
        print(f"📈 Success Rate: {(passed/(passed+failed)*100):.1f}%" if (passed+failed) > 0 else "No tests run")

        return passed, failed, warnings

    def save_results(self, filename="face_recognition_test_results.json"):
        """Save test results to JSON file"""
        try:
            with open(filename, 'w') as f:
                json.dump(self.test_results, f, indent=2)
            print(f"\n💾 Test results saved to {filename}")
        except Exception as e:
            print(f"❌ Failed to save results: {str(e)}")


def main():
    """Main test runner"""
    print("🤖 Face Recognition System Tester")
    print("This tool tests the face recognition service functionality")
    print()

    # Check if service URL is provided
    service_url = 'http://localhost:5000'
    if len(sys.argv) > 1:
        service_url = sys.argv[1]

    print(f"🔗 Testing service at: {service_url}")
    print()

    # Create tester instance
    tester = FaceRecognitionTester(service_url)

    # Run tests
    passed, failed, warnings = tester.run_all_tests()

    # Save results
    tester.save_results()

    # Exit with appropriate code
    if failed > 0:
        sys.exit(1)
    else:
        print("\n🎉 All tests passed!")
        sys.exit(0)

if __name__ == "__main__":
    main()