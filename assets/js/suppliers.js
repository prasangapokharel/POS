// Supplier Management JavaScript
document.addEventListener("DOMContentLoaded", () => {
  // Bootstrap form validation
  const forms = document.querySelectorAll(".needs-validation")

  Array.from(forms).forEach((form) => {
    form.addEventListener(
      "submit",
      (event) => {
        if (!form.checkValidity()) {
          event.preventDefault()
          event.stopPropagation()
        }
        form.classList.add("was-validated")
      },
      false,
    )
  })

  // Phone number formatting and validation
  const phoneInputs = document.querySelectorAll('input[type="tel"]')
  phoneInputs.forEach((input) => {
    input.addEventListener("input", (e) => {
      // Remove non-numeric characters
      let value = e.target.value.replace(/\D/g, "")

      // Limit to reasonable phone number length
      if (value.length > 15) {
        value = value.substring(0, 15)
      }

      e.target.value = value
    })
  })

  // Email validation on blur
  const emailInputs = document.querySelectorAll('input[type="email"]')
  emailInputs.forEach((input) => {
    input.addEventListener("blur", (e) => {
      const email = e.target.value.trim()
      if (email && !isValidEmail(email)) {
        e.target.classList.add("is-invalid")
        showFieldError(e.target, "Please enter a valid email address")
      } else {
        e.target.classList.remove("is-invalid")
        hideFieldError(e.target)
      }
    })
  })

  // Search functionality with debounce
  const searchInput = document.querySelector('input[name="search"]')
  if (searchInput) {
    let searchTimeout
    searchInput.addEventListener("input", (e) => {
      clearTimeout(searchTimeout)
      searchTimeout = setTimeout(() => {
        // Auto-submit search form after 500ms of no typing
        if (e.target.value.length >= 2 || e.target.value.length === 0) {
          e.target.closest("form").submit()
        }
      }, 500)
    })
  }

  // Auto-hide alerts after 5 seconds
  const alerts = document.querySelectorAll(".alert:not(.alert-info):not(.alert-warning)")
  alerts.forEach((alert) => {
    setTimeout(() => {
      if (window.bootstrap && window.bootstrap.Alert) {
        const bsAlert = new window.bootstrap.Alert(alert)
        bsAlert.close()
      } else {
        alert.style.display = "none"
      }
    }, 5000)
  })

  // Enhanced mobile experience
  if (window.innerWidth <= 768) {
    // Make supplier cards more touch-friendly
    const supplierCards = document.querySelectorAll(".supplier-card")
    supplierCards.forEach((card) => {
      card.style.cursor = "pointer"
      card.addEventListener("click", function (e) {
        // Don't trigger if clicking on action buttons
        if (!e.target.closest(".btn")) {
          const viewButton = this.querySelector(".btn-outline-primary")
          if (viewButton && viewButton.href) {
            window.location.href = viewButton.href
          }
        }
      })
    })

    // Enhance touch targets for buttons
    const buttons = document.querySelectorAll(".btn-sm")
    buttons.forEach((btn) => {
      btn.style.minHeight = "44px"
      btn.style.minWidth = "44px"
      btn.style.padding = "8px 12px"
    })
  }

  // Form submission with loading states
  const submitButtons = document.querySelectorAll('button[type="submit"]')
  submitButtons.forEach((button) => {
    button.addEventListener("click", function (e) {
      const form = this.closest("form")
      if (form && form.checkValidity()) {
        // Show loading state
        const originalText = this.innerHTML
        this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...'
        this.disabled = true

        // Re-enable after 10 seconds as fallback
        setTimeout(() => {
          this.innerHTML = originalText
          this.disabled = false
        }, 10000)
      }
    })
  })

  // Currency formatting for Nepali Rupees
  const currencyInputs = document.querySelectorAll('input[data-currency="npr"]')
  currencyInputs.forEach((input) => {
    input.addEventListener("blur", (e) => {
      const value = Number.parseFloat(e.target.value)
      if (!isNaN(value)) {
        e.target.value = value.toFixed(2)
      }
    })

    input.addEventListener("input", (e) => {
      // Allow only numbers and decimal point
      e.target.value = e.target.value.replace(/[^0-9.]/g, "")

      // Ensure only one decimal point
      const parts = e.target.value.split(".")
      if (parts.length > 2) {
        e.target.value = parts[0] + "." + parts.slice(1).join("")
      }
    })
  })

  // Confirmation dialogs for delete actions
  const deleteButtons = document.querySelectorAll('[data-action="delete"]')
  deleteButtons.forEach((button) => {
    button.addEventListener("click", function (e) {
      e.preventDefault()
      const supplierName = this.getAttribute("data-supplier-name") || "this supplier"

      if (confirm(`Are you sure you want to delete ${supplierName}? This action cannot be undone.`)) {
        // Proceed with deletion
        const form = document.createElement("form")
        form.method = "POST"
        form.action = this.href

        const csrfToken = document.querySelector('meta[name="csrf-token"]')
        if (csrfToken) {
          const tokenInput = document.createElement("input")
          tokenInput.type = "hidden"
          tokenInput.name = "_token"
          tokenInput.value = csrfToken.getAttribute("content")
          form.appendChild(tokenInput)
        }

        document.body.appendChild(form)
        form.submit()
      }
    })
  })

  // Real-time validation feedback
  const requiredInputs = document.querySelectorAll("input[required], select[required], textarea[required]")
  requiredInputs.forEach((input) => {
    input.addEventListener("blur", function () {
      validateField(this)
    })

    input.addEventListener("input", function () {
      if (this.classList.contains("is-invalid")) {
        validateField(this)
      }
    })
  })
})

// Helper function to validate email
function isValidEmail(email) {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
  return emailRegex.test(email)
}

// Helper function to format phone numbers
function formatPhoneNumber(phoneNumber) {
  const cleaned = phoneNumber.replace(/\D/g, "")

  // Nepal phone number format
  if (cleaned.length === 10 && cleaned.startsWith("9")) {
    return cleaned.replace(/(\d{3})(\d{3})(\d{4})/, "$1-$2-$3")
  }

  return phoneNumber
}

// Helper function to validate individual fields
function validateField(field) {
  const value = field.value.trim()
  let isValid = true
  let errorMessage = ""

  // Required field validation
  if (field.hasAttribute("required") && !value) {
    isValid = false
    errorMessage = "This field is required"
  }

  // Email validation
  else if (field.type === "email" && value && !isValidEmail(value)) {
    isValid = false
    errorMessage = "Please enter a valid email address"
  }

  // Phone validation
  else if (field.type === "tel" && value && value.length < 10) {
    isValid = false
    errorMessage = "Please enter a valid phone number"
  }

  // Number validation
  else if (field.type === "number" && value && isNaN(Number.parseFloat(value))) {
    isValid = false
    errorMessage = "Please enter a valid number"
  }

  // Update field state
  if (isValid) {
    field.classList.remove("is-invalid")
    field.classList.add("is-valid")
    hideFieldError(field)
  } else {
    field.classList.remove("is-valid")
    field.classList.add("is-invalid")
    showFieldError(field, errorMessage)
  }

  return isValid
}

// Helper function to show field error
function showFieldError(field, message) {
  hideFieldError(field) // Remove existing error first

  const errorDiv = document.createElement("div")
  errorDiv.className = "invalid-feedback"
  errorDiv.textContent = message
  errorDiv.setAttribute("data-field-error", field.name || field.id)

  field.parentNode.appendChild(errorDiv)
}

// Helper function to hide field error
function hideFieldError(field) {
  const existingError = field.parentNode.querySelector(`[data-field-error="${field.name || field.id}"]`)
  if (existingError) {
    existingError.remove()
  }
}

// Helper function to format currency for display
function formatCurrency(amount, currency = "NPR") {
  const formatter = new Intl.NumberFormat("en-NP", {
    style: "currency",
    currency: currency,
    minimumFractionDigits: 2,
  })

  return formatter.format(amount).replace("NPR", "Rs.")
}

// Export functions for use in other scripts
if (typeof window !== "undefined") {
  window.SupplierManager = {
    isValidEmail: isValidEmail,
    formatPhoneNumber: formatPhoneNumber,
    validateField: validateField,
    formatCurrency: formatCurrency,
  }
}

// Handle AJAX requests with proper error handling
function makeAjaxRequest(url, options = {}) {
  const defaultOptions = {
    method: "GET",
    headers: {
      "Content-Type": "application/json",
      "X-Requested-With": "XMLHttpRequest",
    },
  }

  const finalOptions = { ...defaultOptions, ...options }

  return fetch(url, finalOptions)
    .then((response) => {
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`)
      }
      return response.json()
    })
    .catch((error) => {
      console.error("AJAX request failed:", error)
      showNotification("An error occurred. Please try again.", "error")
      throw error
    })
}

// Show notification function
function showNotification(message, type = "info") {
  const alertDiv = document.createElement("div")
  alertDiv.className = `alert alert-${type} alert-dismissible fade show`
  alertDiv.innerHTML = `
    <i class="fas fa-${type === "error" ? "exclamation-circle" : "info-circle"} me-2"></i>
    ${message}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  `

  const container = document.querySelector(".main-content") || document.body
  container.insertBefore(alertDiv, container.firstChild)

  // Auto-hide after 5 seconds
  setTimeout(() => {
    if (alertDiv.parentNode) {
      alertDiv.remove()
    }
  }, 5000)
}
