/**
 * Fixed loadOptions function for attendance-session.js
 * This fixes the dropdown population issue
 */

// Replace the loadOptions function in the SessionManager object
const fixedLoadOptions = async function(departmentId) {
    const optionSelect = document.getElementById('option');
    if (!optionSelect) {
        console.error('❌ Option select element not found');
        return;
    }

    console.log('🔄 Starting to load options for department:', departmentId);
    
    // Show loading state
    optionSelect.innerHTML = '<option value="" disabled selected>Loading options...</option>';
    optionSelect.disabled = true;

    try {
        console.log('📡 Making API call to get options...');
        const response = await API.getOptions(departmentId);
        console.log('📡 API Response received:', response);

        // Check if response is successful and has data
        if (response.status === 'success' && response.data && response.data.length > 0) {
            console.log('✅ Valid response with data:', response.data.length, 'options');
            
            // Clear existing options
            optionSelect.innerHTML = '<option value="" disabled selected>Choose an academic option</option>';

            // Add each option to the dropdown
            response.data.forEach((option, index) => {
                console.log(`📝 Adding option ${index + 1}:`, option);
                const optionElement = document.createElement('option');
                optionElement.value = option.id;
                optionElement.textContent = option.name;
                optionSelect.appendChild(optionElement);
            });

            // Enable the dropdown
            optionSelect.disabled = false;
            
            // Update feedback
            const feedbackEl = document.getElementById('option-feedback');
            if (feedbackEl) {
                feedbackEl.innerHTML = '<i class="fas fa-check text-success me-1"></i>Options loaded successfully';
                feedbackEl.className = 'form-text text-success';
            }

            console.log('✅ Options loaded successfully');
            
        } else if (response.status === 'success' && response.count === 0) {
            // No options found
            optionSelect.innerHTML = '<option value="" disabled selected>No options available</option>';
            optionSelect.disabled = true;
            
            const feedbackEl = document.getElementById('option-feedback');
            if (feedbackEl) {
                feedbackEl.innerHTML = '<i class="fas fa-exclamation-triangle text-warning me-1"></i>No academic options found';
                feedbackEl.className = 'form-text text-warning';
            }
            
            console.log('⚠️ No options found for department:', departmentId);
            
        } else {
            // Error response
            throw new Error(response.message || 'Invalid response format');
        }
        
    } catch (error) {
        console.error('❌ Failed to load options:', error);
        
        optionSelect.innerHTML = '<option value="" disabled selected>Error loading options</option>';
        optionSelect.disabled = true;
        
        const feedbackEl = document.getElementById('option-feedback');
        if (feedbackEl) {
            feedbackEl.innerHTML = '<i class="fas fa-times text-danger me-1"></i>Failed to load options';
            feedbackEl.className = 'form-text text-danger';
        }
    }
};

// Apply the fix when the page loads
document.addEventListener('DOMContentLoaded', function() {
    // Replace the original loadOptions function
    if (window.SessionManager && window.SessionManager.loadOptions) {
        window.SessionManager.loadOptions = fixedLoadOptions;
        console.log('✅ Fixed loadOptions function applied');
    }
});

// Also make it available globally for manual testing
window.fixedLoadOptions = fixedLoadOptions;
