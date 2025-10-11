<!-- Personal Information Section -->
<div class="col-12 mb-4">
    <h6 class="section-title">
        <i class="fas fa-user me-2 text-primary"></i>
        Personal Information
    </h6>
</div>

<div class="col-md-6">
        <div class="mb-3">
            <label for="firstName" class="form-label required-field">
                First Name <span class="text-danger">*</span>
            </label>
            <input type="text" class="form-control" id="firstName" name="first_name"
                   required aria-required="true" aria-label="First Name"
                   maxlength="50" pattern="[A-Za-z\s]+" autocomplete="given-name">
            <div class="invalid-feedback">Please enter a valid first name.</div>
        </div>

        <div class="mb-3">
            <label for="lastName" class="form-label required-field">
                Last Name <span class="text-danger">*</span>
            </label>
            <input type="text" class="form-control" id="lastName" name="last_name"
                   required aria-required="true" aria-label="Last Name"
                   maxlength="50" pattern="[A-Za-z\s]+" autocomplete="family-name">
            <div class="invalid-feedback">Please enter a valid last name.</div>
        </div>

        <div class="mb-3">
            <label for="email" class="form-label required-field">
                Email Address <span class="text-danger">*</span>
            </label>
            <input type="email" class="form-control" id="email" name="email"
                   required aria-required="true" aria-label="Email Address"
                   autocomplete="email">
            <div class="invalid-feedback">Please enter a valid email address.</div>
        </div>

        <div class="mb-3">
            <label for="telephone" class="form-label required-field">
                Phone Number <span class="text-danger">*</span>
            </label>
            <input type="tel" class="form-control" id="telephone" name="telephone"
                   required aria-required="true" aria-label="Phone Number"
                   pattern="^0\d{9}$" maxlength="10" autocomplete="tel">
            <div class="invalid-feedback">Phone number must be exactly 10 digits starting with 0.</div>
            <div class="form-text">Format: 0781234567</div>
        </div>

        <div class="mb-3">
            <label for="dob" class="form-label">Date of Birth</label>
            <input type="date" class="form-control" id="dob" name="dob"
                   aria-label="Date of Birth" min="1950-01-01" max="2010-12-31">
            <div class="invalid-feedback">Please enter a valid date of birth.</div>
            <div class="form-text">Students must be between 16-60 years old</div>
        </div>

        <div class="mb-3">
            <label for="sex" class="form-label required-field">
                Gender <span class="text-danger">*</span>
            </label>
            <select class="form-control" id="sex" name="sex"
                    required aria-required="true" aria-label="Gender">
                <option value="">Select Gender</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
                <option value="Other">Other</option>
            </select>
            <div class="invalid-feedback">Please select a gender.</div>
        </div>
    </div>