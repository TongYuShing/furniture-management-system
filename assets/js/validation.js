/**
 * validation.js - Client-Side Validation and Interactive Features
 * ITP4523M - Internet & Multimedia Applications Development
 * 
 * This external JavaScript file is reused across all web pages to provide:
 * - Form validation (real-time and on submit)
 * - Interactive table row highlighting
 * - Sorting functionality
 * - Modal dialogs
 * - AJAX requests
 */

// =====================================================
// DOM Ready Event Listener
// =====================================================
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all forms with validation
    initFormValidation();
    
    // Initialize table sorting
    initTableSorting();
    
    // Initialize row highlighting
    initRowHighlighting();
    
    // Initialize confirmation dialogs
    initConfirmationDialogs();
    
    // Initialize date picker restrictions
    initDateRestrictions();
    
    // Initialize image preview
    initImagePreview();
    
    // Initialize search/filter functionality
    initSearchFilter();
});

// =====================================================
// FORM VALIDATION
// =====================================================

/**
 * Initialize form validation for all forms with 'data-validate' attribute
 */
function initFormValidation() {
    const forms = document.querySelectorAll('form[data-validate]');
    forms.forEach(form => {
        // Add real-time validation
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('blur', () => validateField(input));
            input.addEventListener('input', () => {
                if (input.classList.contains('is-invalid')) {
                    validateField(input);
                }
            });
        });
        
        // Add submit validation
        form.addEventListener('submit', (e) => {
            if (!validateForm(form)) {
                e.preventDefault();
                showFormErrors(form);
            }
        });
    });
}

/**
 * Validate a single form field
 * @param {HTMLElement} field - The field to validate
 * @returns {boolean} - True if valid, false otherwise
 */
function validateField(field) {
    const value = field.value.trim();
    const type = field.getAttribute('data-type') || field.type;
    const errorElement = document.getElementById(`${field.id}_error`) || createErrorElement(field);
    
    // Remove existing error class
    field.classList.remove('is-invalid');
    errorElement.style.display = 'none';
    errorElement.innerHTML = '';
    
    // Check required
    if (field.hasAttribute('required') && value === '') {
        showFieldError(field, errorElement, 'This field is required.');
        return false;
    }
    
    // Type-specific validation
    switch(type) {
        case 'email':
            const emailRegex = /^[^\s@]+@([^\s@]+\.)+[^\s@]+$/;
            if (value && !emailRegex.test(value)) {
                showFieldError(field, errorElement, 'Please enter a valid email address.');
                return false;
            }
            break;
            
        case 'number':
            const num = parseFloat(value);
            const min = field.getAttribute('min');
            const max = field.getAttribute('max');
            
            if (value && isNaN(num)) {
                showFieldError(field, errorElement, 'Please enter a valid number.');
                return false;
            }
            if (min && num < parseFloat(min)) {
                showFieldError(field, errorElement, `Value must be at least ${min}.`);
                return false;
            }
            if (max && num > parseFloat(max)) {
                showFieldError(field, errorElement, `Value cannot exceed ${max}.`);
                return false;
            }
            break;
            
        case 'tel':
        case 'phone':
            const phoneRegex = /^[0-9]{8}$|^852-[0-9]{8}$/;
            if (value && !phoneRegex.test(value)) {
                showFieldError(field, errorElement, 'Please enter a valid Hong Kong phone number (8 digits).');
                return false;
            }
            break;
            
        case 'password':
            if (value && value.length < 6) {
                showFieldError(field, errorElement, 'Password must be at least 6 characters.');
                return false;
            }
            
            // Check password confirmation if present
            const confirmField = document.getElementById(field.id + '_confirm');
            if (confirmField && value !== confirmField.value) {
                showFieldError(confirmField, document.getElementById(`${confirmField.id}_error`), 'Passwords do not match.');
                return false;
            }
            break;
            
        case 'date':
            if (value) {
                const selectedDate = new Date(value);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                
                const minDate = field.getAttribute('min');
                const maxDate = field.getAttribute('max');
                
                if (minDate && selectedDate < new Date(minDate)) {
                    showFieldError(field, errorElement, `Date cannot be earlier than ${minDate}.`);
                    return false;
                }
                if (maxDate && selectedDate > new Date(maxDate)) {
                    showFieldError(field, errorElement, `Date cannot be later than ${maxDate}.`);
                    return false;
                }
            }
            break;
            
        case 'text':
        case 'textarea':
            const maxLength = field.getAttribute('maxlength');
            if (maxLength && value.length > parseInt(maxLength)) {
                showFieldError(field, errorElement, `Maximum ${maxLength} characters allowed.`);
                return false;
            }
            const minLength = field.getAttribute('minlength');
            if (minLength && value.length < parseInt(minLength) && value !== '') {
                showFieldError(field, errorElement, `Minimum ${minLength} characters required.`);
                return false;
            }
            break;
    }
    
    return true;
}

/**
 * Validate entire form
 * @param {HTMLFormElement} form - The form to validate
 * @returns {boolean} - True if all fields valid, false otherwise
 */
function validateForm(form) {
    const inputs = form.querySelectorAll('input, select, textarea');
    let isValid = true;
    
    inputs.forEach(input => {
        if (!validateField(input)) {
            isValid = false;
        }
    });
    
    return isValid;
}

/**
 * Show error messages for all invalid fields
 * @param {HTMLFormElement} form - The form containing errors
 */
function showFormErrors(form) {
    const errorSummary = document.getElementById('error-summary') || createErrorSummary(form);
    const errors = [];
    
    const invalidFields = form.querySelectorAll('.is-invalid');
    invalidFields.forEach(field => {
        const label = document.querySelector(`label[for="${field.id}"]`);
        const fieldName = label ? label.innerText : field.name;
        errors.push(`${fieldName} is invalid.`);
    });
    
    if (errors.length > 0) {
        errorSummary.innerHTML = `
            <div class="alert alert-danger">
                <strong>Please fix the following errors:</strong>
                <ul>${errors.map(e => `<li>${e}</li>`).join('')}</ul>
            </div>
        `;
        errorSummary.style.display = 'block';
        
        // Scroll to error summary
        errorSummary.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

/**
 * Show error for a specific field
 */
function showFieldError(field, errorElement, message) {
    field.classList.add('is-invalid');
    errorElement.innerHTML = message;
    errorElement.style.display = 'block';
}

/**
 * Create error element for a field
 */
function createErrorElement(field) {
    const errorElement = document.createElement('div');
    errorElement.className = 'invalid-feedback';
    errorElement.id = `${field.id}_error`;
    errorElement.style.display = 'none';
    field.parentNode.insertBefore(errorElement, field.nextSibling);
    return errorElement;
}

/**
 * Create error summary element
 */
function createErrorSummary(form) {
    const summary = document.createElement('div');
    summary.id = 'error-summary';
    summary.style.display = 'none';
    form.insertBefore(summary, form.firstChild);
    return summary;
}

// =====================================================
// TABLE SORTING FUNCTIONALITY
// =====================================================

/**
 * Initialize table sorting for tables with 'data-sortable' attribute
 */
function initTableSorting() {
    const tables = document.querySelectorAll('table[data-sortable]');
    tables.forEach(table => {
        const headers = table.querySelectorAll('th.sortable');
        headers.forEach((header, index) => {
            header.addEventListener('click', () => sortTable(table, index));
        });
    });
}

/**
 * Sort table by column
 * @param {HTMLTableElement} table - The table to sort
 * @param {number} columnIndex - Index of column to sort by
 */
function sortTable(table, columnIndex) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    const headers = table.querySelectorAll('th');
    const isAscending = headers[columnIndex].classList.contains('asc');
    
    // Determine data type
    const firstRowValue = rows[0].querySelectorAll('td')[columnIndex]?.innerText || '';
    const isNumeric = !isNaN(parseFloat(firstRowValue)) && isFinite(firstRowValue);
    
    // Sort rows
    rows.sort((a, b) => {
        let aValue = a.querySelectorAll('td')[columnIndex]?.innerText || '';
        let bValue = b.querySelectorAll('td')[columnIndex]?.innerText || '';
        
        if (isNumeric) {
            aValue = parseFloat(aValue) || 0;
            bValue = parseFloat(bValue) || 0;
            return isAscending ? aValue - bValue : bValue - aValue;
        } else {
            return isAscending ? aValue.localeCompare(bValue) : bValue.localeCompare(aValue);
        }
    });
    
    // Update DOM
    rows.forEach(row => tbody.appendChild(row));
    
    // Update sort indicators
    headers.forEach(header => {
        header.classList.remove('asc', 'desc');
    });
    headers[columnIndex].classList.add(isAscending ? 'desc' : 'asc');
}

// =====================================================
// ROW HIGHLIGHTING (as required in assignment)
// =====================================================

/**
 * Initialize row highlighting for tables
 * Highlights row when mouse moves over it
 */
function initRowHighlighting() {
    const tables = document.querySelectorAll('table');
    tables.forEach(table => {
        const rows = table.querySelectorAll('tbody tr');
        rows.forEach(row => {
            row.addEventListener('mouseenter', () => {
                row.style.backgroundColor = 'rgba(44, 95, 45, 0.1)';
                row.style.transition = 'background-color 0.2s ease';
            });
            row.addEventListener('mouseleave', () => {
                row.style.backgroundColor = '';
            });
        });
    });
}

// =====================================================
// CONFIRMATION DIALOGS
// =====================================================

/**
 * Initialize confirmation dialogs for delete buttons
 */
function initConfirmationDialogs() {
    const confirmButtons = document.querySelectorAll('[data-confirm]');
    confirmButtons.forEach(button => {
        button.addEventListener('click', (e) => {
            const message = button.getAttribute('data-confirm') || 'Are you sure?';
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
    });
}

/**
 * Show custom confirmation modal
 * @param {string} message - Confirmation message
 * @param {function} onConfirm - Callback when confirmed
 * @param {function} onCancel - Callback when cancelled
 */
function showConfirmModal(message, onConfirm, onCancel) {
    // Check if modal already exists
    let modal = document.getElementById('confirmModal');
    
    if (!modal) {
        // Create modal
        modal = document.createElement('div');
        modal.id = 'confirmModal';
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-content" style="max-width: 400px;">
                <div class="modal-header">
                    <h3>Confirm Action</h3>
                    <button class="modal-close" onclick="closeConfirmModal()">&times;</button>
                </div>
                <div class="modal-body" id="confirmMessage">
                    ${message}
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="confirmCancelBtn">Cancel</button>
                    <button class="btn btn-danger" id="confirmOkBtn">Confirm</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }
    
    const messageElement = document.getElementById('confirmMessage');
    if (messageElement) messageElement.innerHTML = message;
    
    const okBtn = document.getElementById('confirmOkBtn');
    const cancelBtn = document.getElementById('confirmCancelBtn');
    
    const handleConfirm = () => {
        closeConfirmModal();
        if (onConfirm) onConfirm();
    };
    
    const handleCancel = () => {
        closeConfirmModal();
        if (onCancel) onCancel();
    };
    
    okBtn.onclick = handleConfirm;
    cancelBtn.onclick = handleCancel;
    
    modal.classList.add('show');
}

/**
 * Close confirmation modal
 */
function closeConfirmModal() {
    const modal = document.getElementById('confirmModal');
    if (modal) modal.classList.remove('show');
}

// =====================================================
// DATE RESTRICTIONS
// =====================================================

/**
 * Initialize date picker restrictions
 * Sets min/max dates based on attributes
 */
function initDateRestrictions() {
    const dateInputs = document.querySelectorAll('input[type="date"]');
    dateInputs.forEach(input => {
        const minDays = input.getAttribute('data-min-days');
        const maxDays = input.getAttribute('data-max-days');
        
        if (minDays) {
            const minDate = new Date();
            minDate.setDate(minDate.getDate() + parseInt(minDays));
            input.min = minDate.toISOString().split('T')[0];
        }
        
        if (maxDays) {
            const maxDate = new Date();
            maxDate.setDate(maxDate.getDate() + parseInt(maxDays));
            input.max = maxDate.toISOString().split('T')[0];
        }
        
        // Add validation on change
        input.addEventListener('change', () => validateField(input));
    });
}

// =====================================================
// IMAGE PREVIEW
// =====================================================

/**
 * Initialize image preview for file uploads
 */
function initImagePreview() {
    const fileInputs = document.querySelectorAll('input[type="file"][data-preview]');
    fileInputs.forEach(input => {
        const previewId = input.getAttribute('data-preview');
        const previewElement = document.getElementById(previewId);
        
        if (previewElement) {
            input.addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (file && file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = (event) => {
                        previewElement.src = event.target.result;
                        previewElement.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
    });
}

// =====================================================
// SEARCH/FILTER FUNCTIONALITY
// =====================================================

/**
 * Initialize search/filter functionality
 */
function initSearchFilter() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            filterTable(this.value);
        });
    }
}

/**
 * Filter table rows based on search term
 * @param {string} searchTerm - Term to search for
 */
function filterTable(searchTerm) {
    const tables = document.querySelectorAll('table');
    const term = searchTerm.toLowerCase();
    
    tables.forEach(table => {
        const rows = table.querySelectorAll('tbody tr');
        rows.forEach(row => {
            const text = row.innerText.toLowerCase();
            row.style.display = text.includes(term) ? '' : 'none';
        });
    });
}

// =====================================================
// AJAX HELPER FUNCTIONS
// =====================================================

/**
 * Make AJAX GET request
 * @param {string} url - Request URL
 * @param {function} successCallback - Success callback
 * @param {function} errorCallback - Error callback
 */
function ajaxGet(url, successCallback, errorCallback) {
    fetch(url)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (successCallback) successCallback(data);
        })
        .catch(error => {
            console.error('AJAX Error:', error);
            if (errorCallback) errorCallback(error);
        });
}

/**
 * Make AJAX POST request
 * @param {string} url - Request URL
 * @param {FormData|Object} data - Data to send
 * @param {function} successCallback - Success callback
 * @param {function} errorCallback - Error callback
 */
function ajaxPost(url, data, successCallback, errorCallback) {
    const options = {
        method: 'POST',
        headers: {}
    };
    
    if (data instanceof FormData) {
        options.body = data;
    } else {
        options.headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(data);
    }
    
    fetch(url, options)
        .then(response => response.json())
        .then(data => {
            if (data.success && successCallback) {
                successCallback(data);
            } else if (!data.success && errorCallback) {
                errorCallback(data.message || 'Request failed');
            }
        })
        .catch(error => {
            console.error('AJAX Error:', error);
            if (errorCallback) errorCallback(error);
        });
}

// =====================================================
// UTILITY FUNCTIONS
// =====================================================

/**
 * Format currency for display
 * @param {number} amount - Amount to format
 * @returns {string} - Formatted currency string
 */
function formatCurrency(amount) {
    return '$' + parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

/**
 * Format date for display
 * @param {string} dateString - Date string (YYYY-MM-DD)
 * @returns {string} - Formatted date (DD/MM/YYYY)
 */
function formatDate(dateString) {
    if (!dateString) return '';
    const parts = dateString.split('-');
    return `${parts[2]}/${parts[1]}/${parts[0]}`;
}

/**
 * Show toast notification
 * @param {string} message - Notification message
 * @param {string} type - Type: 'success', 'error', 'warning', 'info'
 */
function showToast(message, type = 'info') {
    let toastContainer = document.getElementById('toastContainer');
    
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toastContainer';
        toastContainer.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9999;
        `;
        document.body.appendChild(toastContainer);
    }
    
    const toast = document.createElement('div');
    toast.className = `alert alert-${type}`;
    toast.style.cssText = `
        margin-top: 10px;
        animation: slideIn 0.3s ease;
        cursor: pointer;
    `;
    toast.innerHTML = message;
    
    toast.addEventListener('click', () => {
        toast.remove();
    });
    
    toastContainer.appendChild(toast);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        if (toast.parentNode) toast.remove();
    }, 3000);
}

/**
 * Show loading spinner
 * @param {string} elementId - ID of element to show spinner in
 */
function showLoading(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        const originalContent = element.innerHTML;
        element.setAttribute('data-original-content', originalContent);
        element.innerHTML = '<div class="spinner"></div> Loading...';
        element.disabled = true;
    }
}

/**
 * Hide loading spinner
 * @param {string} elementId - ID of element to restore
 */
function hideLoading(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        const originalContent = element.getAttribute('data-original-content');
        if (originalContent) {
            element.innerHTML = originalContent;
            element.removeAttribute('data-original-content');
        }
        element.disabled = false;
    }
}

/**
 * Validate delivery date (at least 2 days before delivery for deletion)
 * @param {string} deliveryDate - Delivery date (YYYY-MM-DD)
 * @returns {boolean} - True if can be deleted
 */
function canDeleteOrder(deliveryDate) {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const delivery = new Date(deliveryDate);
    const diffTime = delivery - today;
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    return diffDays >= 2;
}

/**
 * Debounce function for search inputs
 * @param {function} func - Function to debounce
 * @param {number} delay - Delay in milliseconds
 * @returns {function} - Debounced function
 */
function debounce(func, delay) {
    let timeout;
    return function() {
        const context = this;
        const args = arguments;
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(context, args), delay);
    };
}

// =====================================================
// PAGE-SPECIFIC INITIALIZATIONS
// =====================================================

/**
 * Initialize order page specific functionality
 */
function initOrderPage() {
    const quantityInput = document.getElementById('order_quantity');
    const priceElement = document.getElementById('product_price');
    const totalElement = document.getElementById('total_amount');
    
    if (quantityInput && priceElement) {
        const updateTotal = debounce(() => {
            const quantity = parseInt(quantityInput.value) || 0;
            const price = parseFloat(priceElement.getAttribute('data-price')) || 0;
            const total = quantity * price;
            if (totalElement) {
                totalElement.innerHTML = formatCurrency(total);
            }
        }, 100);
        
        quantityInput.addEventListener('input', updateTotal);
        updateTotal();
    }
}

/**
 * Initialize report page with chart functionality
 */
function initReportPage() {
    const chartCanvas = document.getElementById('salesChart');
    if (chartCanvas && typeof Chart !== 'undefined') {
        // Chart.js initialization would go here
        console.log('Chart.js available for reports');
    }
}

// Export functions for use in inline scripts
window.validation = {
    validateField,
    validateForm,
    showConfirmModal,
    formatCurrency,
    formatDate,
    showToast,
    showLoading,
    hideLoading,
    canDeleteOrder,
    debounce
};