<!-- Location Information -->
<div class="row mt-4">
    <div class="col-12 mb-3">
        <h6 class="section-title">
            <i class="fas fa-map-marker-alt me-2 text-warning"></i>
            Location Information
            <small class="text-muted">(Optional but recommended)</small>
        </h6>
        <p class="text-muted small mb-3">
            Please select your complete location in Rwanda (Province → District → Sector → Cell)
        </p>
    </div>
    <div class="col-md-3">
        <div class="mb-3">
            <label for="province" class="form-label d-flex align-items-center">
                <i class="fas fa-city me-2 text-success"></i>
                <strong>Province</strong>
            </label>
            <select class="form-control" id="province" name="province" aria-label="Province">
                <option value="">Select Province</option>
                <?php foreach ($provinces as $province): ?>
                    <option value="<?= htmlspecialchars($province['id']) ?>">
                        <?= htmlspecialchars($province['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="col-md-3">
        <div class="mb-3">
            <label for="district" class="form-label d-flex align-items-center">
                <i class="fas fa-building me-2 text-primary"></i>
                <strong>District</strong>
            </label>
            <select class="form-control" id="district" name="district" disabled aria-label="District">
                <option value="">Select Province First</option>
            </select>
        </div>
    </div>
    <div class="col-md-3">
        <div class="mb-3">
            <label for="sector" class="form-label d-flex align-items-center">
                <i class="fas fa-home me-2 text-info"></i>
                <strong>Sector</strong>
            </label>
            <select class="form-control" id="sector" name="sector" disabled aria-label="Sector">
                <option value="">Select District First</option>
            </select>
        </div>
    </div>
    <div class="col-md-3">
        <div class="mb-3">
            <label for="cell" class="form-label d-flex align-items-center justify-content-between">
                <span class="d-flex align-items-center">
                    <i class="fas fa-map-pin me-2 text-warning fs-5"></i>
                    <strong>Cell</strong>
                </span>
                <span class="badge bg-warning text-dark rounded-pill">
                    <i class="fas fa-home me-1"></i>Final
                </span>
            </label>
            <select class="form-control" id="cell" name="cell" disabled aria-label="Cell">
                <option value="">Select Sector First</option>
            </select>
            <div class="mt-2" id="cellSearchContainer" style="display: none;">
                <input type="text" class="form-control form-control-sm" id="cellSearch"
                       placeholder="🔍 Search cells..." aria-label="Search cells">
            </div>
            <div class="mt-2 alert alert-info py-2 px-3 border-0 d-none" id="cellInfo"
                 style="background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);">
                <div class="d-flex align-items-center">
                    <i class="fas fa-map-marker-alt text-info me-2 fs-5"></i>
                    <div>
                        <strong class="text-info">Location Selected</strong><br>
                        <small id="cellInfoText" class="text-info-emphasis fw-medium"></small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>