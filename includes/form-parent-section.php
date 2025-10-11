<!-- Parent/Guardian Information -->
<div class="row mt-4">
    <div class="col-12 mb-3">
        <h6 class="section-title">
            <i class="fas fa-users me-2 text-info"></i>
            Parent/Guardian Information
            <small class="text-muted">(Optional)</small>
        </h6>
    </div>
    <div class="col-md-4">
        <div class="mb-3">
            <label for="parent_first_name" class="form-label">Parent First Name</label>
            <input type="text" class="form-control" id="parent_first_name" name="parent_first_name"
                   aria-label="Parent First Name" maxlength="50" pattern="[A-Za-z\s]+">
            <div class="invalid-feedback">Please enter a valid parent first name.</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="mb-3">
            <label for="parent_last_name" class="form-label">Parent Last Name</label>
            <input type="text" class="form-control" id="parent_last_name" name="parent_last_name"
                   aria-label="Parent Last Name" maxlength="50" pattern="[A-Za-z\s]+">
            <div class="invalid-feedback">Please enter a valid parent last name.</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="mb-3">
            <label for="parent_contact" class="form-label">Parent Contact</label>
            <input type="tel" class="form-control" id="parent_contact" name="parent_contact"
                   aria-label="Parent Contact" pattern="^0\d{9}$" maxlength="10">
            <div class="invalid-feedback">Parent phone number must be exactly 10 digits starting with 0.</div>
            <div class="form-text">Format: 0781234567</div>
        </div>
    </div>
</div>