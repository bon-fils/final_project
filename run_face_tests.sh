#!/bin/bash

# Face Recognition Test Runner
# This script runs comprehensive tests for the face recognition system

echo "🧪 Face Recognition System Test Runner"
echo "======================================"

# Check if Python 3 is available
if ! command -v python3 &> /dev/null; then
    echo "❌ Python 3 is not installed or not in PATH"
    exit 1
fi

# Check if face recognition service is running
echo "🔍 Checking if face recognition service is running..."
if curl -s http://localhost:5000/health > /dev/null 2>&1; then
    echo "✅ Face recognition service is running"
else
    echo "⚠️  Face recognition service is not running on localhost:5000"
    echo "   Please start the service first with: ./start_face_recognition.sh"
    echo ""
    echo "   Or run tests against a different URL:"
    echo "   python3 test_face_recognition.py http://your-service-url:port"
    exit 1
fi

echo ""
echo "🚀 Running face recognition tests..."
echo ""

# Run the tests
python3 test_face_recognition.py

# Check exit code
if [ $? -eq 0 ]; then
    echo ""
    echo "🎉 All tests passed!"
else
    echo ""
    echo "❌ Some tests failed. Check the output above for details."
    echo "   Test results have been saved to face_recognition_test_results.json"
fi