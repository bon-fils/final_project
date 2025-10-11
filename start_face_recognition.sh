#!/bin/bash

# Face Recognition Service Startup Script
# This script starts the Python face recognition service for the RP Attendance System

echo "=== RP Attendance System - Face Recognition Service ==="
echo "Starting Python face recognition service..."

# Set environment variables
export FLASK_APP=face_recognition_service.py
export FLASK_ENV=production
export FACE_RECOGNITION_PORT=5000

# Database configuration (update these with your actual values)
export DB_HOST=localhost
export DB_NAME=rp_attendance_system
export DB_USER=root
export DB_PASS=""

# Check if Python is installed
if ! command -v python3 &> /dev/null; then
    echo "❌ Python 3 is not installed. Please install Python 3.7+ first."
    exit 1
fi

# Check if virtual environment exists
if [ ! -d "venv" ]; then
    echo "📦 Creating virtual environment..."
    python3 -m venv venv
fi

# Activate virtual environment
echo "🔧 Activating virtual environment..."
source venv/bin/activate

# Install/update dependencies
echo "📥 Installing dependencies..."
pip install -r requirements.txt

# Check if dlib/face_recognition can be installed (may require system dependencies)
echo "🔍 Checking face_recognition library..."
python3 -c "import face_recognition; print('✅ face_recognition library available')" 2>/dev/null
if [ $? -ne 0 ]; then
    echo "⚠️  face_recognition library not available. Installing..."
    echo "Note: This may require system dependencies. On Ubuntu/Debian:"
    echo "sudo apt-get install -y build-essential cmake pkg-config libx11-dev libatlas-base-dev libgtk-3-dev libboost-python-dev"
    pip install face_recognition
fi

# Start the service
echo "🚀 Starting face recognition service on port 5000..."
echo "Service will be available at: http://localhost:5000"
echo "Health check: http://localhost:5000/health"
echo "Press Ctrl+C to stop the service"
echo ""

python3 face_recognition_service.py