// Login form validation and handling
document.addEventListener("DOMContentLoaded", () => {
  const loginForm = document.getElementById("loginForm")
  const signupForm = document.getElementById("signupForm")
  const emailInput = document.getElementById("email")
  const passwordInput = document.getElementById("password")
  const forgotPasswordForm = document.getElementById("forgotPasswordForm")
  const tabs = document.querySelectorAll(".tab")
  const loginContent = document.getElementById("login-content")
  const signupContent = document.getElementById("signup-content")

  // Tab switching functionality
  tabs.forEach((tab) => {
    tab.addEventListener("click", function () {
      const tabType = this.getAttribute("data-tab")
      switchTab(tabType)
    })
  })

  // Form validation functions
  function validateLoginForm() {
    const email = emailInput.value.trim()
    const password = passwordInput.value.trim()
    let isValid = true

    if (!email) {
      showFieldError("email", "Email is required")
      isValid = false
    } else if (!isValidEmail(email)) {
      showFieldError("email", "Please enter a valid email address")
      isValid = false
    } else {
      clearFieldError("email")
    }

    if (!password) {
      showFieldError("password", "Password is required")
      isValid = false
    } else if (password.length < 6) {
      showFieldError("password", "Password must be at least 6 characters")
      isValid = false
    } else {
      clearFieldError("password")
    }

    return isValid
  }

  function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
    return emailRegex.test(email)
  }

  function showFieldError(fieldId, message) {
    const field = document.getElementById(fieldId)
    if (!field) return

    field.style.borderColor = "#f56565"
    field.style.boxShadow = "0 0 0 2px rgba(245, 101, 101, 0.3)"

    // Remove existing error message
    const existingError = field.parentNode.querySelector(".error-message")
    if (existingError) {
      existingError.remove()
    }

    // Add new error message
    const errorDiv = document.createElement("div")
    errorDiv.className = "error-message"
    errorDiv.textContent = message
    field.parentNode.appendChild(errorDiv)
  }

  function clearFieldError(fieldId) {
    const field = document.getElementById(fieldId)
    if (!field) return

    field.style.borderColor = ""
    field.style.boxShadow = ""

    const errorMessage = field.parentNode.querySelector(".error-message")
    if (errorMessage) {
      errorMessage.remove()
    }
  }

  // Real-time validation
  if (emailInput) {
    emailInput.addEventListener("input", function () {
      if (this.value.trim() && !isValidEmail(this.value.trim())) {
        showFieldError("email", "Please enter a valid email address")
      } else {
        clearFieldError("email")
      }
    })
  }

  if (passwordInput) {
    passwordInput.addEventListener("input", function () {
      if (this.value.trim() && this.value.trim().length < 6) {
        showFieldError("password", "Password must be at least 6 characters")
      } else {
        clearFieldError("password")
      }
    })
  }

  // Form submission
  if (loginForm) {
    loginForm.addEventListener("submit", (e) => {
      if (!validateLoginForm()) {
        e.preventDefault()
        return false
      }

      // Show loading state
      const submitBtn = loginForm.querySelector(".btn-submit")
      const btnText = submitBtn.querySelector(".btn-text")
      const spinner = submitBtn.querySelector(".spinner")

      if (submitBtn && btnText && spinner) {
        submitBtn.disabled = true
        btnText.style.display = "none"
        spinner.classList.remove("d-none")
      }

      // Form will submit normally to PHP
    })
  }

  if (signupForm) {
    signupForm.addEventListener("submit", (e) => {
      e.preventDefault()
      showNotification("Sign up functionality is currently disabled", "info")
    })
  }

  // Forgot password form
  if (forgotPasswordForm) {
    forgotPasswordForm.addEventListener("submit", (e) => {
      e.preventDefault()
      showNotification("Password reset functionality is currently disabled", "info")
    })
  }

  // Auto-focus on email field
  if (emailInput) {
    emailInput.focus()
  }

  // Handle Enter key navigation
  const inputs = document.querySelectorAll("input:not([disabled])")
  inputs.forEach((input, index) => {
    input.addEventListener("keypress", (e) => {
      if (e.key === "Enter" && index < inputs.length - 1) {
        e.preventDefault()
        inputs[index + 1].focus()
      }
    })
  })

  // Auto-hide alerts
  setTimeout(() => {
    const alerts = document.querySelectorAll(".alert")
    alerts.forEach((alert) => {
      alert.style.transition = "opacity 0.5s ease"
      alert.style.opacity = "0"
      setTimeout(() => {
        if (alert.parentNode) {
          alert.remove()
        }
      }, 500)
    })
  }, 5000)
})

// Global functions
function togglePassword() {
  const passwordInput = document.getElementById("password")
  const passwordIcon = document.getElementById("passwordIcon")

  if (passwordInput && passwordIcon) {
    if (passwordInput.type === "password") {
      passwordInput.type = "text"
      passwordIcon.classList.remove("fa-eye")
      passwordIcon.classList.add("fa-eye-slash")
    } else {
      passwordInput.type = "password"
      passwordIcon.classList.remove("fa-eye-slash")
      passwordIcon.classList.add("fa-eye")
    }
  }
}

function switchTab(tabType) {
  const tabs = document.querySelectorAll(".tab")
  const loginContent = document.getElementById("login-content")
  const signupContent = document.getElementById("signup-content")

  if (!tabs || !loginContent || !signupContent) return

  // Update tab appearance
  tabs.forEach((tab) => {
    tab.classList.remove("active")
    if (tab.getAttribute("data-tab") === tabType) {
      tab.classList.add("active")
    }
  })

  // Show/hide content
  if (tabType === "login") {
    loginContent.classList.remove("d-none")
    signupContent.classList.add("d-none")
    const emailInput = document.getElementById("email")
    if (emailInput) emailInput.focus()
  } else {
    loginContent.classList.add("d-none")
    signupContent.classList.remove("d-none")
    const signupName = document.getElementById("signup-name")
    if (signupName) signupName.focus()
  }
}

function showForgotPassword() {
  const modal = document.getElementById("forgotPasswordModal")
  if (modal && window.bootstrap) {
    const bsModal = new window.bootstrap.Modal(modal)
    bsModal.show()
  }
}

function showNotification(message, type = "info") {
  const alertDiv = document.createElement("div")
  alertDiv.className = `alert alert-${type}`
  alertDiv.style.cssText = `
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    min-width: 300px;
    animation: slideInRight 0.3s ease-out;
    padding: 12px 15px;
    border-radius: 8px;
    font-size: 14px;
  `
  alertDiv.innerHTML = `<i class="fas fa-info-circle"></i> ${message}`

  document.body.appendChild(alertDiv)

  setTimeout(() => {
    alertDiv.style.animation = "slideOutRight 0.3s ease-in"
    setTimeout(() => {
      if (alertDiv.parentNode) {
        alertDiv.remove()
      }
    }, 300)
  }, 3000)
}

// Add CSS animations
const style = document.createElement("style")
style.textContent = `
  @keyframes slideInRight {
    from {
      opacity: 0;
      transform: translateX(100%);
    }
    to {
      opacity: 1;
      transform: translateX(0);
    }
  }

  @keyframes slideOutRight {
    from {
      opacity: 1;
      transform: translateX(0);
    }
    to {
      opacity: 0;
      transform: translateX(100%);
    }
  }
`
document.head.appendChild(style)
