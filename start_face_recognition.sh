#!/bin/bash

# Face Recognition Service Startup Script
# This script starts the Python face recognition service for the RP Attendance System

# Configuration
SERVICE_NAME="Face Recognition Service"
SERVICE_FILE="face_recognition_service.py"
LOG_DIR="logs"
PID_FILE="face_recognition_service.pid"
SERVICE_HOST=${SERVICE_HOST:-"localhost"}
SERVICE_PORT=${SERVICE_PORT:-5000}

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

check_dependencies() {
    log_info "Checking dependencies..."

    # Check if Python is installed
    if ! command -v python3 &> /dev/null; then
        log_error "Python 3 is not installed. Please install Python 3.8 or higher."
        exit 1
    fi

    # Check Python version
    PYTHON_VERSION=$(python3 -c 'import sys; print(".".join(map(str, sys.version_info[:2])))')
    if python3 -c 'import sys; exit(0 if sys.version_info >= (3, 8) else 1)'; then
        log_success "Python $PYTHON_VERSION is compatible"
    else
        log_error "Python $PYTHON_VERSION is not supported. Please use Python 3.8 or higher."
        exit 1
    fi

    # Check if required Python packages are installed
    log_info "Checking Python packages..."
    python3 -c "
import sys
required_packages = ['face_recognition', 'opencv-python', 'flask', 'mysql.connector', 'redis', 'PIL']
missing_packages = []

for package in required_packages:
    try:
        if package == 'PIL':
            import PIL
        else:
            __import__(package.replace('-', '_'))
    except ImportError:
        missing_packages.append(package)

if missing_packages:
    print('Missing packages:', ', '.join(missing_packages))
    sys.exit(1)
else:
    print('All required packages are installed')
" 2>/dev/null

    if [ $? -ne 0 ]; then
        log_error "Some required Python packages are missing."
        log_info "Run: pip install -r requirements.txt"
        exit 1
    fi

    log_success "All dependencies are satisfied"
}

check_database_connection() {
    log_info "Checking database connection..."

    # Try to connect to database using Python
    python3 -c "
import mysql.connector
import os
import sys

try:
    host = os.getenv('DB_HOST', 'localhost')
    database = os.getenv('DB_NAME', 'rp_attendance_system')
    user = os.getenv('DB_USER', 'root')
    password = os.getenv('DB_PASS', '')

    conn = mysql.connector.connect(
        host=host,
        database=database,
        user=user,
        password=password,
        connection_timeout=5
    )
    conn.close()
    print('Database connection successful')
except mysql.connector.Error as e:
    print(f'Database connection failed: {e}')
    sys.exit(1)
" 2>/dev/null

    if [ $? -ne 0 ]; then
        log_error "Cannot connect to database. Please check your database configuration."
        exit 1
    fi

    log_success "Database connection is working"
}

check_redis_connection() {
    log_info "Checking Redis connection..."

    # Try to connect to Redis using Python
    python3 -c "
import redis
import os
import sys

try:
    host = os.getenv('REDIS_HOST', 'localhost')
    port = int(os.getenv('REDIS_PORT', 6379))

    r = redis.Redis(host=host, port=port, db=0, socket_connect_timeout=5)
    r.ping()
    print('Redis connection successful')
except redis.ConnectionError as e:
    print(f'Redis connection failed: {e}')
    sys.exit(1)
" 2>/dev/null

    if [ $? -eq 0 ]; then
        log_success "Redis connection is working"
    else
        log_warning "Redis connection failed. Service will work without caching."
    fi
}

create_directories() {
    log_info "Creating necessary directories..."

    # Create logs directory
    if [ ! -d "$LOG_DIR" ]; then
        mkdir -p "$LOG_DIR"
        log_success "Created logs directory"
    fi

    # Create cache directory if it doesn't exist
    if [ ! -d "cache" ]; then
        mkdir -p "cache"
        log_success "Created cache directory"
    fi
}

start_service() {
    log_info "Starting $SERVICE_NAME..."

    # Check if service is already running
    if [ -f "$PID_FILE" ]; then
        if kill -0 $(cat "$PID_FILE") 2>/dev/null; then
            log_warning "Service is already running (PID: $(cat "$PID_FILE"))"
            exit 1
        else
            log_warning "Removing stale PID file"
            rm "$PID_FILE"
        fi
    fi

    # Start the service in background
    nohup python3 "$SERVICE_FILE" > "$LOG_DIR/service.out" 2> "$LOG_DIR/service.err" &
    SERVICE_PID=$!

    # Wait a moment for service to start
    sleep 2

    # Check if service is still running
    if kill -0 $SERVICE_PID 2>/dev/null; then
        echo $SERVICE_PID > "$PID_FILE"
        log_success "$SERVICE_NAME started successfully (PID: $SERVICE_PID)"

        # Test service health
        test_service_health
    else
        log_error "Failed to start $SERVICE_NAME"
        log_info "Check logs in $LOG_DIR/service.err for details"
        exit 1
    fi
}

test_service_health() {
    log_info "Testing service health..."

    # Wait for service to be ready
    for i in {1..10}; do
        if curl -s "http://$SERVICE_HOST:$SERVICE_PORT/health" > /dev/null 2>&1; then
            log_success "Service health check passed"
            return 0
        fi
        sleep 1
    done

    log_warning "Service health check failed - service may still be starting"
    log_info "Check service logs for details"
}

stop_service() {
    log_info "Stopping $SERVICE_NAME..."

    if [ -f "$PID_FILE" ]; then
        SERVICE_PID=$(cat "$PID_FILE")

        if kill -0 $SERVICE_PID 2>/dev/null; then
            kill $SERVICE_PID
            sleep 2

            if kill -0 $SERVICE_PID 2>/dev/null; then
                log_warning "Service didn't stop gracefully, forcing stop..."
                kill -9 $SERVICE_PID
                sleep 1
            fi

            if kill -0 $SERVICE_PID 2>/dev/null; then
                log_error "Failed to stop service"
                exit 1
            else
                log_success "Service stopped successfully"
            fi
        else
            log_warning "Service was not running"
        fi

        rm "$PID_FILE"
    else
        log_warning "PID file not found - service may not be running"
    fi
}

check_service_status() {
    if [ -f "$PID_FILE" ]; then
        SERVICE_PID=$(cat "$PID_FILE")

        if kill -0 $SERVICE_PID 2>/dev/null; then
            log_success "$SERVICE_NAME is running (PID: $SERVICE_PID)"

            # Test health endpoint
            if curl -s "http://$SERVICE_HOST:$SERVICE_PORT/health" > /dev/null 2>&1; then
                log_success "Service health check: PASSED"
            else
                log_warning "Service health check: FAILED"
            fi
        else
            log_warning "$SERVICE_NAME is not running (stale PID file found)"
            rm "$PID_FILE"
        fi
    else
        log_info "$SERVICE_NAME is not running"
    fi
}

show_usage() {
    echo "Usage: $0 {start|stop|restart|status|check}"
    echo ""
    echo "Commands:"
    echo "  start   - Start the face recognition service"
    echo "  stop    - Stop the face recognition service"
    echo "  restart - Restart the face recognition service"
    echo "  status  - Check service status"
    echo "  check   - Run pre-flight checks without starting service"
    echo ""
    echo "Environment Variables:"
    echo "  SERVICE_HOST - Service host (default: localhost)"
    echo "  SERVICE_PORT - Service port (default: 5000)"
    echo "  DB_HOST      - Database host"
    echo "  DB_NAME      - Database name"
    echo "  DB_USER      - Database user"
    echo "  DB_PASS      - Database password"
    echo "  REDIS_HOST   - Redis host"
    echo "  REDIS_PORT   - Redis port"
}

# Main script logic
case "${1:-}" in
    start)
        check_dependencies
        check_database_connection
        check_redis_connection
        create_directories
        start_service
        ;;
    stop)
        stop_service
        ;;
    restart)
        stop_service
        sleep 2
        check_dependencies
        check_database_connection
        check_redis_connection
        create_directories
        start_service
        ;;
    status)
        check_service_status
        ;;
    check)
        check_dependencies
        check_database_connection
        check_redis_connection
        log_success "All pre-flight checks passed"
        ;;
    *)
        show_usage
        exit 1
        ;;
esac

exit 0