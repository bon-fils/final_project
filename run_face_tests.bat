@echo off
REM Face Recognition Test Runner for Windows
REM This script runs comprehensive tests for the face recognition system

echo 🧪 Face Recognition System Test Runner
echo ======================================

REM Check if Python 3 is available
python --version >nul 2>&1
if %errorlevel% neq 0 (
    echo ❌ Python is not installed or not in PATH
    echo    Please install Python 3 and make sure it's in your PATH
    pause
    exit /b 1
)

REM Check if face recognition service is running
echo 🔍 Checking if face recognition service is running...
curl -s http://localhost:5000/health >nul 2>&1
if %errorlevel% equ 0 (
    echo ✅ Face recognition service is running
) else (
    echo ⚠️  Face recognition service is not running on localhost:5000
    echo    Please start the service first with: start_face_recognition.bat
    echo.
    echo    Or run tests against a different URL:
    echo    python test_face_recognition.py http://your-service-url:port
    pause
    exit /b 1
)

echo.
echo 🚀 Running face recognition tests...
echo.

REM Run the tests
python test_face_recognition.py

REM Check exit code
if %errorlevel% equ 0 (
    echo.
    echo 🎉 All tests passed!
) else (
    echo.
    echo ❌ Some tests failed. Check the output above for details.
    echo    Test results have been saved to face_recognition_test_results.json
)

pause