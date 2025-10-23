/**
 * Simple Working Fingerprint Integration for Student Registration
 * Based on successful test page approach
 * Version: 3.0 - Simplified and working
 */

class FingerprintIntegration {
    constructor() {
        this.esp32IP = '192.168.137.69';
        this.esp32Port = 80;
        this.isCapturing = false;
        this.fingerprintCaptured = false;
        this.fingerprintData = null;
        
        this.init();
    }
    
    init() {
        console.log('🔐 Initializing Fingerprint Integration System');
        this.bindEvents();
        this.checkESP32Status();
        
        // Check for existing enrollment session
        this.checkEnrollmentStatus();
    }
    
    bindEvents() {
        // Bind to existing form elements
        const captureBtn = document.getElementById('captureFingerprintBtn');
        const enrollBtn = document.getElementById('enrollFingerprintBtn');
        const clearBtn = document.getElementById('clearFingerprintBtn');
        
        if (captureBtn) {
            captureBtn.addEventListener('click', () => this.startCapture());
        }
        
        if (enrollBtn) {
            enrollBtn.addEventListener('click', () => this.startEnrollment());
        }
        
        if (clearBtn) {
            clearBtn.addEventListener('click', () => this.clearFingerprint());
        }
        
        // Bind to form submission to include fingerprint data
        const registrationForm = document.getElementById('registrationForm');
        if (registrationForm) {
            registrationForm.addEventListener('submit', (e) => this.handleFormSubmission(e));
        }
    }
    
    async checkESP32Status() {
        try {
            const response = await this.makeESP32Request('/status', 'GET');
            
            if (response.success !== false) {
                this.updateConnectionStatus('connected', response);
                console.log('✅ ESP32 connected:', response);
            } else {
                throw new Error('ESP32 not responding');
            }
            
        } catch (error) {
            console.warn('⚠️ ESP32 connection check failed:', error.message);
            this.updateConnectionStatus('disconnected', { error: error.message });
        }
    }
    
    updateConnectionStatus(status, data = {}) {
        const statusElement = document.getElementById('esp32ConnectionStatus');
        const statusContainer = document.querySelector('.fingerprint-connection-status');
        
        if (statusElement) {
            if (status === 'connected') {
                statusElement.innerHTML = '<span class="text-success">Connected ✓</span>';
                statusElement.className = 'text-success';
                
                if (data.capacity) {
                    statusElement.innerHTML += ` (Capacity: ${data.capacity})`;
                }
            } else {
                statusElement.innerHTML = '<span class="text-danger">Disconnected ✗</span>';
                statusElement.className = 'text-danger';
            }
        }
        
        if (statusContainer) {
            statusContainer.style.display = 'block';
        }
    }
    
    async startCapture() {
        if (this.isEnrolling) {
            this.showAlert('⚠️ Enrollment already in progress', 'warning');
            return;
        }
        
        try {
            this.showAlert('🔍 Checking ESP32 connection...', 'info');
            
            // Check ESP32 status first
            const statusResponse = await this.makeESP32Request('/status', 'GET');
            
            if (!statusResponse || statusResponse.fingerprint_sensor !== 'connected') {
                throw new Error('Fingerprint sensor not connected to ESP32');
            }
            
            // Update display on ESP32
            await this.makeESP32Request('/display', 'GET', { 
                message: 'Place finger on sensor...' 
            });
            
            this.showAlert('✅ ESP32 ready. Place your finger on the sensor...', 'success');
            this.updateFingerprintUI('capturing');
            
            // Start polling for fingerprint detection
            await this.pollForFingerprint();
            
        } catch (error) {
            console.error('Capture error:', error);
            this.showAlert('❌ Capture failed: ' + error.message, 'error');
            this.updateFingerprintUI('ready');
        }
    }
    
    async pollForFingerprint() {
        const maxAttempts = 150; // 30 seconds at 200ms intervals
        let attempts = 0;
        
        return new Promise((resolve, reject) => {
            const pollInterval = setInterval(async () => {
                attempts++;
                
                try {
                    const response = await this.makeESP32Request('/identify', 'GET');
                    
                    if (response.success) {
                        clearInterval(pollInterval);
                        
                        // Fingerprint captured successfully
                        this.handleCaptureSuccess(response);
                        resolve(response);
                        
                    } else if (attempts >= maxAttempts) {
                        clearInterval(pollInterval);
                        reject(new Error('Fingerprint capture timeout'));
                    }
                    
                    // Update progress
                    const progress = Math.min(20 + (attempts * 0.5), 95);
                    this.updateCaptureProgress(progress);
                    
                } catch (pollError) {
                    console.log(`Polling attempt ${attempts} failed, retrying...`);
                    
                    if (attempts >= maxAttempts) {
                        clearInterval(pollInterval);
                        reject(new Error('Failed to communicate with ESP32'));
                    }
                }
            }, 200);
        });
    }
    
    handleCaptureSuccess(response) {
        this.enrollmentData = {
            captured: true,
            fingerprint_id: response.fingerprint_id,
            confidence: response.confidence,
            captured_at: new Date().toISOString()
        };
        
        this.updateFingerprintUI('captured');
        this.showAlert(`🎉 Fingerprint captured! ID: ${response.fingerprint_id}, Confidence: ${response.confidence}%`, 'success');
        
        // Update ESP32 display
        this.makeESP32Request('/display', 'GET', { 
            message: 'Fingerprint captured!' 
        }).catch(() => {}); // Ignore display errors
    }
    
    async startEnrollment() {
        if (!this.enrollmentData || !this.enrollmentData.captured) {
            this.showAlert('⚠️ Please capture a fingerprint first', 'warning');
            return;
        }
        
        if (this.isEnrolling) {
            this.showAlert('⚠️ Enrollment already in progress', 'warning');
            return;
        }
        
        try {
            this.isEnrolling = true;
            this.showAlert('🔄 Starting fingerprint enrollment...', 'info');
            
            // Get student information from form
            const studentName = this.getFormValue('firstName') + ' ' + this.getFormValue('lastName');
            const regNo = this.getFormValue('reg_no');
            
            if (!studentName.trim() || !regNo.trim()) {
                throw new Error('Student name and registration number are required');
            }
            
            // Generate unique fingerprint ID
            const fingerprintId = Date.now() % 1000;
            
            // Start enrollment on ESP32
            const enrollResponse = await this.makeESP32Request('/enroll', 'POST', {
                id: fingerprintId,
                student_name: studentName,
                reg_no: regNo
            });
            
            if (enrollResponse.success) {
                this.showAlert('🔄 Enrollment started. Follow ESP32 display instructions...', 'info');
                
                // Start monitoring enrollment progress
                await this.monitorEnrollmentProgress(fingerprintId);
                
            } else {
                throw new Error(enrollResponse.error || 'Failed to start enrollment');
            }
            
        } catch (error) {
            console.error('Enrollment error:', error);
            this.showAlert('❌ Enrollment failed: ' + error.message, 'error');
            this.isEnrolling = false;
        }
    }
    
    async monitorEnrollmentProgress(fingerprintId) {
        const maxWaitTime = 120000; // 2 minutes
        const startTime = Date.now();
        
        return new Promise((resolve, reject) => {
            const checkProgress = setInterval(async () => {
                try {
                    // Check enrollment status from ESP32
                    const statusResponse = await this.makeESP32Request('/enroll-status', 'GET');
                    
                    if (!statusResponse.active) {
                        clearInterval(checkProgress);
                        
                        // Check if enrollment completed successfully
                        const finalStatus = await this.checkEnrollmentCompletion(fingerprintId);
                        
                        if (finalStatus.success) {
                            this.handleEnrollmentSuccess(finalStatus);
                            resolve(finalStatus);
                        } else {
                            reject(new Error('Enrollment failed or was cancelled'));
                        }
                        
                        this.isEnrolling = false;
                        return;
                    }
                    
                    // Update progress based on step
                    this.updateEnrollmentProgress(statusResponse);
                    
                    // Check for timeout
                    if (Date.now() - startTime > maxWaitTime) {
                        clearInterval(checkProgress);
                        
                        // Cancel enrollment on ESP32
                        await this.makeESP32Request('/cancel-enroll', 'POST');
                        
                        this.isEnrolling = false;
                        reject(new Error('Enrollment timeout'));
                    }
                    
                } catch (error) {
                    console.error('Progress check error:', error);
                }
            }, 1000); // Check every second
        });
    }
    
    async checkEnrollmentCompletion(fingerprintId) {
        try {
            // Check our PHP API for enrollment status
            const response = await fetch('api/fingerprint-status.php?action=status');
            const data = await response.json();
            
            if (data.success && data.enrollment && data.enrollment.step === 'complete') {
                return {
                    success: true,
                    fingerprint_id: data.enrollment.fingerprint_id,
                    completed_at: data.enrollment.completed_at
                };
            }
            
            return { success: false };
            
        } catch (error) {
            console.error('Completion check error:', error);
            return { success: false };
        }
    }
    
    handleEnrollmentSuccess(data) {
        this.enrollmentData = {
            ...this.enrollmentData,
            enrolled: true,
            fingerprint_id: data.fingerprint_id,
            enrolled_at: data.completed_at || new Date().toISOString()
        };
        
        this.updateFingerprintUI('enrolled');
        this.showAlert('🎉 Fingerprint enrolled successfully!', 'success');
        
        // Update form validation to include fingerprint data
        this.updateFormValidation();
    }
    
    updateEnrollmentProgress(status) {
        const statusElement = document.getElementById('fingerprintStatus');
        if (!statusElement) return;
        
        switch (status.step) {
            case 1:
                statusElement.textContent = 'Place finger on sensor (First scan)';
                break;
            case 2:
                statusElement.textContent = 'Remove finger and wait...';
                break;
            case 3:
                statusElement.textContent = 'Place same finger again (Second scan)';
                break;
            default:
                statusElement.textContent = 'Processing enrollment...';
        }
    }
    
    updateCaptureProgress(progress) {
        const statusElement = document.getElementById('fingerprintStatus');
        if (statusElement) {
            statusElement.textContent = `Scanning... ${Math.round(progress)}%`;
        }
    }
    
    updateFingerprintUI(state) {
        const container = document.querySelector('.fingerprint-container');
        const captureBtn = document.getElementById('captureFingerprintBtn');
        const enrollBtn = document.getElementById('enrollFingerprintBtn');
        const clearBtn = document.getElementById('clearFingerprintBtn');
        const canvas = document.getElementById('fingerprintCanvas');
        const placeholder = document.getElementById('fingerprintPlaceholder');
        
        // Reset classes
        container?.classList.remove('fingerprint-capturing', 'fingerprint-captured', 'fingerprint-enrolled');
        
        switch (state) {
            case 'ready':
                captureBtn.disabled = false;
                enrollBtn.classList.add('d-none');
                clearBtn.classList.add('d-none');
                canvas?.classList.add('d-none');
                placeholder?.classList.remove('d-none');
                break;
                
            case 'capturing':
                container?.classList.add('fingerprint-capturing');
                captureBtn.disabled = true;
                captureBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Capturing...';
                break;
                
            case 'captured':
                container?.classList.add('fingerprint-captured');
                captureBtn.disabled = false;
                captureBtn.innerHTML = '<i class="fas fa-fingerprint me-2"></i>Recapture';
                enrollBtn.classList.remove('d-none');
                clearBtn.classList.remove('d-none');
                canvas?.classList.remove('d-none');
                placeholder?.classList.add('d-none');
                break;
                
            case 'enrolled':
                container?.classList.add('fingerprint-enrolled');
                captureBtn.disabled = true;
                enrollBtn.classList.add('d-none');
                clearBtn.classList.remove('d-none');
                clearBtn.innerHTML = '<i class="fas fa-check me-2"></i>Enrolled';
                clearBtn.disabled = true;
                break;
        }
    }
    
    clearFingerprint() {
        this.enrollmentData = null;
        this.isEnrolling = false;
        
        if (this.statusCheckInterval) {
            clearInterval(this.statusCheckInterval);
        }
        
        this.updateFingerprintUI('ready');
        this.showAlert('Fingerprint data cleared', 'info');
        
        // Clear enrollment session on server
        fetch('api/fingerprint-status.php?action=clear_session')
            .catch(() => {}); // Ignore errors
    }
    
    async handleFormSubmission(event) {
        // Don't prevent form submission, just add fingerprint data if available
        if (this.enrollmentData && this.enrollmentData.enrolled) {
            // Add fingerprint data to form
            const form = event.target;
            const fingerprintInput = document.createElement('input');
            fingerprintInput.type = 'hidden';
            fingerprintInput.name = 'fingerprint_data';
            fingerprintInput.value = JSON.stringify(this.enrollmentData);
            form.appendChild(fingerprintInput);
            
            console.log('✅ Fingerprint data added to form submission:', this.enrollmentData);
        }
    }
    
    async makeESP32Request(endpoint, method = 'GET', data = null) {
        const url = `http://${this.esp32IP}:${this.esp32Port}${endpoint}`;
        
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            timeout: 10000
        };
        
        if (data && method === 'POST') {
            options.body = new URLSearchParams(data).toString();
        } else if (data && method === 'GET') {
            const params = new URLSearchParams(data).toString();
            return this.fetchWithTimeout(`${url}?${params}`, { ...options, method: 'GET' });
        }
        
        return this.fetchWithTimeout(url, options);
    }
    
    async fetchWithTimeout(url, options) {
        const timeout = options.timeout || 10000;
        
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), timeout);
        
        try {
            const response = await fetch(url, {
                ...options,
                signal: controller.signal
            });
            
            clearTimeout(timeoutId);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            return await response.json();
            
        } catch (error) {
            clearTimeout(timeoutId);
            
            if (error.name === 'AbortError') {
                throw new Error('Request timeout');
            }
            
            throw error;
        }
    }
    
    getFormValue(fieldName) {
        const field = document.getElementById(fieldName) || document.querySelector(`[name="${fieldName}"]`);
        return field ? field.value.trim() : '';
    }
    
    updateFormValidation() {
        // Trigger form validation update if available
        if (window.registrationManager && typeof window.registrationManager.validateForm === 'function') {
            window.registrationManager.validateForm();
        }
    }
    
    async checkEnrollmentStatus() {
        try {
            const response = await fetch('api/fingerprint-status.php?action=status');
            const data = await response.json();
            
            if (data.success && data.enrollment && data.enrollment.active) {
                console.log('📋 Found active enrollment session:', data.enrollment);
                
                // Resume enrollment monitoring if needed
                if (data.enrollment.step !== 'complete') {
                    this.isEnrolling = true;
                    this.showAlert('📋 Resuming fingerprint enrollment...', 'info');
                    await this.monitorEnrollmentProgress(data.enrollment.id);
                } else {
                    // Enrollment is complete
                    this.handleEnrollmentSuccess({
                        fingerprint_id: data.enrollment.fingerprint_id,
                        completed_at: data.enrollment.completed_at
                    });
                }
            }
        } catch (error) {
            console.log('No active enrollment session found');
        }
    }
    
    showAlert(message, type = 'info') {
        // Use existing alert system if available
        if (window.registrationManager && typeof window.registrationManager.showAlert === 'function') {
            window.registrationManager.showAlert(message, type);
        } else {
            // Fallback alert system
            console.log(`[${type.toUpperCase()}] ${message}`);
            
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            const container = document.querySelector('.container') || document.body;
            container.insertBefore(alertDiv, container.firstChild);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }
    }
}

// Initialize fingerprint integration when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Initializing Fingerprint Integration');
    window.fingerprintIntegration = new FingerprintIntegration();
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = FingerprintIntegration;
}
