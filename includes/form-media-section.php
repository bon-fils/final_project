<!-- Media Section -->
<div class="row mt-4">
    <div class="col-md-6">
        <div class="mb-3">
            <label for="faceImagesInput" class="form-label">
                <i class="fas fa-camera me-2 text-primary"></i>
                Face Recognition Images
                <small class="text-muted">(2-5 images required for face recognition)</small>
            </label>
            <input type="file" class="form-control d-none" id="faceImagesInput" name="face_images[]"
                   accept="image/jpeg,image/png,image/webp" multiple aria-label="Face Recognition Images">
            <div class="face-images-upload-area" id="faceImagesUploadArea">
                <div class="face-images-placeholder">
                    <i class="fas fa-images fa-2x text-muted mb-2"></i>
                    <p class="mb-2">Click to select face images</p>
                    <small class="text-muted">JPEG, PNG, WebP (Max 5MB each, 2-5 images)</small>
                </div>
                <div id="faceImagesPreview" class="face-images-preview d-none">
                    <!-- Image previews will be inserted here -->
                </div>
            </div>
            <div class="mt-2">
                <label for="faceImagesInput" class="btn btn-sm btn-outline-primary me-2 mb-0" style="cursor: pointer;">
                    <i class="fas fa-folder-open me-1"></i>Choose Images
                </label>
                <button type="button" class="btn btn-sm btn-outline-danger d-none" id="clearFaceImages">
                    <i class="fas fa-times me-1"></i>Clear All
                </button>
            </div>
            <div class="mt-2">
                <small id="faceImagesCount" class="text-muted">0 images selected</small>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="mb-3">
            <label class="form-label">
                <i class="fas fa-fingerprint me-2 text-info"></i>
                Fingerprint Capture
                <small class="text-muted">(Optional)</small>
            </label>
            <div class="fingerprint-container border rounded p-3">
                <div class="fingerprint-display d-flex justify-content-center align-items-center mb-3"
                     style="min-height: 150px; border: 2px dashed #dee2e6; border-radius: 10px; background: #f8f9fa;">
                    <canvas id="fingerprintCanvas" width="150" height="150" class="d-none" style="border-radius: 10px;"></canvas>
                    <div id="fingerprintPlaceholder" class="text-center">
                        <i class="fas fa-fingerprint fa-2x text-muted mb-2"></i>
                        <p class="text-muted mb-1">No fingerprint captured</p>
                        <small class="text-muted">Click capture to begin</small>
                    </div>
                </div>
                <div class="fingerprint-controls d-flex gap-2 justify-content-center flex-wrap">
                    <button type="button" class="btn btn-outline-info btn-sm" id="captureFingerprintBtn">
                        <i class="fas fa-fingerprint me-1"></i>Capture
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm d-none" id="clearFingerprintBtn">
                        <i class="fas fa-times me-1"></i>Clear
                    </button>
                    <button type="button" class="btn btn-outline-success btn-sm d-none" id="enrollFingerprintBtn">
                        <i class="fas fa-save me-1"></i>Enroll
                    </button>
                </div>
                <div class="fingerprint-status text-center mt-2">
                    <small id="fingerprintStatus" class="text-muted">Ready to capture fingerprint</small>
                </div>
            </div>
        </div>
    </div>
</div>