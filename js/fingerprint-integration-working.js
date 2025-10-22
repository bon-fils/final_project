/**
 * Simple Working Fingerprint Integration for Student Registration
 * Based on successful test page approach
 * Version: 3.0 - Simplified and working
 */

class FingerprintIntegration {
    constructor() {
        this.esp32IP = '192.168.137.40';
        this.esp32Port = 80;
        this.isCapturing = false;
        this.fingerprintCaptured = false;
        this.fingerprintData = null;
        
        this.init();
    }
    
    init() {
        console.log('🔐 Initializing Simple Fingerprint Integration');
        this.bindEvents();
        this.checkESP32Status();
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
        
        console.log('✅ Event listeners bound to fingerprint buttons');
    }
    
    async checkESP32Status() {
        try {
            const response = await this.makeESP32Request('/status');
            
            if (response.success && response.data) {
                this.updateConnectionStatus('connected', response.data);
                console.log('✅ ESP32 connected:', response.data);
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
        if (statusElement) {
            if (status === 'connected') {
                statusElement.textContent = 'Connected ✓';
                statusElement.className = 'text-success';
            } else {
                statusElement.textContent = 'Disconnected ✗';
                statusElement.className = 'text-danger';
            }
        }
    }
    
    async makeESP32Request(endpoint, method = 'GET', data = null) {
        const url = `http://${this.esp32IP}:${this.esp32Port}${endpoint}`;
        console.log(`🔄 ${method} ${url}`);

        try {
            const options = {
                method: method,
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                mode: 'cors'
            };

            if (data && method === 'POST') {
                options.body = new URLSearchParams(data).toString();
            } else if (data && method === 'GET') {
                const params = new URLSearchParams(data).toString();
                const separator = url.includes('?') ? '&' : '?';
                return this.makeESP32Request(`${endpoint}${separator}${params}`, 'GET');
            }

            const response = await fetch(url, options);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const responseText = await response.text();
            let responseData;
            
            try {
                responseData = JSON.parse(responseText);
            } catch (e) {
                responseData = responseText;
            }

            console.log(`✅ Response:`, responseData);
            return { success: true, data: responseData };

        } catch (error) {
            console.error(`❌ Error: ${error.message}`);
            return { success: false, error: error.message };
        }
    }
    
    updateFingerprintUI(state) {
        const captureBtn = document.getElementById('captureFingerprintBtn');
        const enrollBtn = document.getElementById('enrollFingerprintBtn');
        const clearBtn = document.getElementById('clearFingerprintBtn');
        const status = document.getElementById('fingerprintStatus');

        if (!captureBtn || !enrollBtn || !clearBtn || !status) {
            console.warn('⚠️ Fingerprint UI elements not found');
            return;
        }

        switch (state) {
            case 'capturing':
                captureBtn.disabled = true;
                captureBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Capturing...';
                enrollBtn.classList.add('d-none');
                clearBtn.classList.add('d-none');
                status.textContent = 'Place finger on ESP32 sensor...';
                status.className = 'text-info';
                break;

            case 'captured':
                captureBtn.classList.add('d-none');
                enrollBtn.classList.remove('d-none');
                clearBtn.classList.remove('d-none');
                status.textContent = `✅ Fingerprint captured! ID: ${this.fingerprintData?.fingerprint_id || 'Unknown'}`;
                status.className = 'text-success';
                break;

            case 'enrolled':
                captureBtn.classList.add('d-none');
                enrollBtn.classList.add('d-none');
                clearBtn.classList.remove('d-none');
                status.textContent = '✅ Fingerprint enrolled successfully!';
                status.className = 'text-success';
                break;

            default: // ready
                captureBtn.disabled = false;
                captureBtn.innerHTML = '<i class="fas fa-fingerprint me-2"></i>Capture from ESP32';
                captureBtn.classList.remove('d-none');
                enrollBtn.classList.add('d-none');
                clearBtn.classList.add('d-none');
                status.textContent = 'Ready to capture fingerprint from ESP32 sensor';
                status.className = 'text-muted';
        }
    }
    
    async startCapture() {
        if (this.isCapturing) return;

        this.isCapturing = true;
        this.fingerprintCaptured = false;
        this.updateFingerprintUI('capturing');

        try {
            // Step 1: Check ESP32 status
            const statusResult = await this.makeESP32Request('/status');
            if (!statusResult.success) {
                throw new Error('ESP32 not responding');
            }

            if (!statusResult.data.fingerprint_sensor || statusResult.data.fingerprint_sensor !== 'connected') {
                throw new Error('Fingerprint sensor not connected');
            }

            // Step 2: Send display message
            await this.makeESP32Request('/display', 'GET', { message: 'Place finger on sensor...' });

            // Step 3: Start polling for fingerprint
            const maxAttempts = 50; // 10 seconds at 200ms intervals
            let attempts = 0;

            const pollInterval = setInterval(async () => {
                attempts++;

                try {
                    const identifyResult = await this.makeESP32Request('/identify');
                    
                    if (identifyResult.success && identifyResult.data.success) {
                        // Fingerprint detected!
                        clearInterval(pollInterval);
                        this.fingerprintCaptured = true;
                        this.fingerprintData = identifyResult.data;
                        
                        this.updateFingerprintUI('captured');
                        this.isCapturing = false;

                        // Update display
                        await this.makeESP32Request('/display', 'GET', { message: 'Fingerprint captured!' });
                        
                        // Visual feedback in canvas
                        this.updateFingerprintCanvas();
                        
                        console.log('🎉 Fingerprint captured:', this.fingerprintData);

                    } else if (attempts >= maxAttempts) {
                        // Timeout
                        clearInterval(pollInterval);
                        throw new Error('Timeout: No fingerprint detected');
                    }

                } catch (pollError) {
                    console.log(`Poll attempt ${attempts} failed: ${pollError.message}`);
                }

            }, 200);

        } catch (error) {
            console.error(`Capture failed: ${error.message}`);
            this.updateFingerprintUI('ready');
            this.isCapturing = false;
            this.showAlert(`❌ Capture failed: ${error.message}`, 'error');
        }
    }
    
    updateFingerprintCanvas() {
        const canvas = document.getElementById('fingerprintCanvas');
        const placeholder = document.getElementById('fingerprintPlaceholder');
        
        if (canvas && placeholder) {
            canvas.classList.remove('d-none');
            placeholder.classList.add('d-none');
            
            // Simple visual representation
            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            
            // Draw fingerprint pattern
            ctx.strokeStyle = '#28a745';
            ctx.lineWidth = 2;
            ctx.beginPath();
            
            const centerX = canvas.width / 2;
            const centerY = canvas.height / 2;
            
            // Draw concentric circles
            for (let i = 1; i <= 5; i++) {
                ctx.beginPath();
                ctx.arc(centerX, centerY, i * 15, 0, 2 * Math.PI);
                ctx.stroke();
            }
        }
    }
    
    async startEnrollment() {
        if (!this.fingerprintCaptured) {
            this.showAlert('⚠️ No fingerprint captured to enroll. Please capture a fingerprint first.', 'warning');
            return;
        }

        // Get student info from form
        const firstName = document.getElementById('firstName')?.value || '';
        const lastName = document.getElementById('lastName')?.value || '';
        const regNo = document.getElementById('reg_no')?.value || '';
        
        const studentName = `${firstName} ${lastName}`.trim();

        if (!studentName || !regNo) {
            this.showAlert('❌ Student name and registration number required for enrollment', 'error');
            return;
        }

        try {
            console.log('🔄 Starting fingerprint enrollment...');

            const fingerprintId = Date.now() % 1000;
            const enrollResult = await this.makeESP32Request('/enroll', 'POST', {
                id: fingerprintId,
                student_name: studentName,
                reg_no: regNo
            });

            if (enrollResult.success && enrollResult.data.success) {
                this.updateFingerprintUI('enrolled');
                await this.makeESP32Request('/display', 'GET', { message: 'Enrollment complete!' });
                console.log(`✅ Fingerprint enrolled for ${studentName} (${regNo})`);
                this.showAlert(`✅ Fingerprint enrolled successfully for ${studentName}!`, 'success');
            } else {
                throw new Error(enrollResult.data?.error || 'Enrollment failed');
            }

        } catch (error) {
            console.error(`Enrollment failed: ${error.message}`);
            this.showAlert(`❌ Enrollment failed: ${error.message}`, 'error');
        }
    }
    
    clearFingerprint() {
        this.fingerprintCaptured = false;
        this.fingerprintData = null;
        this.isCapturing = false;
        
        // Clear canvas
        const canvas = document.getElementById('fingerprintCanvas');
        const placeholder = document.getElementById('fingerprintPlaceholder');
        
        if (canvas && placeholder) {
            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            canvas.classList.add('d-none');
            placeholder.classList.remove('d-none');
        }
        
        this.updateFingerprintUI('ready');
        console.log('🧹 Fingerprint cleared');
        this.showAlert('Fingerprint cleared', 'info');
    }
    
    showAlert(message, type = 'info') {
        // Try to use the main app's alert system
        if (window.registrationApp && window.registrationApp.showAlert) {
            window.registrationApp.showAlert(message, type);
        } else {
            // Fallback to console
            console.log(`[${type.toUpperCase()}] ${message}`);
        }
    }
    
    // Method to get fingerprint data for form submission
    getFingerprintData() {
        return this.fingerprintData;
    }
    
    // Method to check if fingerprint is captured
    isFingerprintCaptured() {
        return this.fingerprintCaptured;
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    // Only initialize if fingerprint elements exist
    if (document.getElementById('captureFingerprintBtn')) {
        window.fingerprintIntegration = new FingerprintIntegration();
        console.log('✅ Fingerprint Integration initialized');
    }
});
