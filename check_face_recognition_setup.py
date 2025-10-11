#!/usr/bin/env python3
"""
Face Recognition Setup Checker
Verifies that all required dependencies are installed and configured correctly
"""

import sys
import os
import importlib

def check_python_version():
    """Check Python version compatibility"""
    print("🐍 Checking Python version...")
    version = sys.version_info
    if version.major >= 3 and version.minor >= 7:
        print(f"✅ Python {version.major}.{version.minor}.{version.micro} - Compatible")
        return True
    else:
        print(f"❌ Python {version.major}.{version.minor}.{version.micro} - Requires Python 3.7+")
        return False

def check_dependencies():
    """Check if all required packages are installed"""
    print("\n📦 Checking dependencies...")

    required_packages = [
        'flask',
        'flask_cors',
        'face_recognition',
        'PIL',
        'numpy',
        'mysql.connector'
    ]

    missing_packages = []
    warnings = []

    for package in required_packages:
        try:
            if package == 'PIL':
                importlib.import_module('PIL')
            elif package == 'mysql.connector':
                importlib.import_module('mysql.connector')
            else:
                importlib.import_module(package)
            print(f"✅ {package}")
        except ImportError:
            print(f"❌ {package} - MISSING")
            missing_packages.append(package)
        except Exception as e:
            print(f"⚠️  {package} - WARNING: {str(e)}")
            warnings.append(f"{package}: {str(e)}")

    if missing_packages:
        print(f"\n❌ Missing packages: {', '.join(missing_packages)}")
        print("   Install with: pip install -r requirements.txt")
        return False

    if warnings:
        print(f"\n⚠️  Warnings: {len(warnings)}")
        for warning in warnings:
            print(f"   {warning}")

    return True

def check_face_recognition():
    """Test face_recognition library specifically"""
    print("\n🤖 Testing face_recognition library...")

    try:
        import face_recognition
        print("✅ face_recognition imported successfully")

        # Test basic functionality
        test_image = face_recognition.load_image_file.__doc__
        if test_image:
            print("✅ face_recognition functions available")
        else:
            print("⚠️  face_recognition functions may not work properly")

        return True
    except ImportError:
        print("❌ face_recognition not available")
        print("   This is likely due to missing system dependencies.")
        print("   On Ubuntu/Debian:")
        print("   sudo apt-get install -y build-essential cmake pkg-config")
        print("   sudo apt-get install -y libx11-dev libatlas-base-dev libgtk-3-dev libboost-python-dev")
        return False
    except Exception as e:
        print(f"❌ face_recognition error: {str(e)}")
        return False

def check_database_connection():
    """Test database connection (optional)"""
    print("\n🗄️  Checking database connection...")

    # Try to import mysql connector
    try:
        import mysql.connector
    except ImportError:
        print("⚠️  mysql-connector-python not available - database tests will be skipped")
        return None

    # Check environment variables
    db_config = {
        'host': os.getenv('DB_HOST', 'localhost'),
        'database': os.getenv('DB_NAME', 'rp_attendance_system'),
        'user': os.getenv('DB_USER', 'root'),
        'password': os.getenv('DB_PASS', ''),
    }

    print(f"   Host: {db_config['host']}")
    print(f"   Database: {db_config['database']}")
    print(f"   User: {db_config['user']}")

    try:
        conn = mysql.connector.connect(**db_config)
        cursor = conn.cursor()

        # Test basic query
        cursor.execute("SELECT 1")
        result = cursor.fetchone()

        cursor.close()
        conn.close()

        if result:
            print("✅ Database connection successful")
            return True
        else:
            print("⚠️  Database connection made but test query failed")
            return False

    except Exception as e:
        print(f"❌ Database connection failed: {str(e)}")
        print("   Make sure MySQL server is running and credentials are correct")
        return False

def check_service_file():
    """Check if the service file exists and is readable"""
    print("\n📄 Checking service file...")

    service_file = 'face_recognition_service.py'
    if os.path.exists(service_file):
        print(f"✅ {service_file} found")

        # Try to read the file
        try:
            with open(service_file, 'r') as f:
                content = f.read()
                if 'face_recognition' in content and 'Flask' in content:
                    print("✅ Service file appears to be valid")
                    return True
                else:
                    print("⚠️  Service file may be corrupted or incomplete")
                    return False
        except Exception as e:
            print(f"❌ Cannot read service file: {str(e)}")
            return False
    else:
        print(f"❌ {service_file} not found")
        return False

def main():
    """Main setup checker"""
    print("🔍 Face Recognition Setup Checker")
    print("=================================")
    print("This tool verifies your environment is ready for face recognition testing")
    print()

    checks = [
        check_python_version,
        check_dependencies,
        check_face_recognition,
        check_service_file,
        check_database_connection
    ]

    results = []
    for check in checks:
        try:
            result = check()
            results.append(result)
        except Exception as e:
            print(f"❌ {check.__name__} failed with error: {str(e)}")
            results.append(False)

    print("\n" + "=" * 50)
    print("📊 Setup Check Results:")

    passed = sum(1 for r in results if r is True)
    failed = sum(1 for r in results if r is False)
    skipped = sum(1 for r in results if r is None)

    print(f"✅ Passed: {passed}")
    print(f"❌ Failed: {failed}")
    if skipped > 0:
        print(f"⏭️  Skipped: {skipped}")

    success_rate = (passed / (passed + failed)) * 100 if (passed + failed) > 0 else 0
    print(f"📈 Success Rate: {success_rate:.1f}%")

    if failed == 0:
        print("\n🎉 Setup check passed! You can now run the face recognition tests.")
        print("   Run: python test_face_recognition.py")
        return 0
    else:
        print("\n❌ Setup check failed. Please fix the issues above before running tests.")
        return 1


if __name__ == "__main__":
    sys.exit(main())