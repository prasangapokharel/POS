// Dashboard functionality
document.addEventListener("DOMContentLoaded", () => {
  // Initialize tooltips if available
  if (window.bootstrap && window.bootstrap.Tooltip) {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map((tooltipTriggerEl) => new window.bootstrap.Tooltip(tooltipTriggerEl))
  }

  // Sidebar toggle for mobile
  const sidebarToggle = document.getElementById("sidebarToggle")
  const sidebar = document.querySelector(".sidebar")

  if (sidebarToggle) {
    sidebarToggle.addEventListener("click", () => {
      sidebar.classList.toggle("show")
    })
  }

  // Close sidebar when clicking outside on mobile
  document.addEventListener("click", (e) => {
    const sidebar = document.querySelector(".sidebar")
    if (sidebar && window.innerWidth <= 768) {
      if (!sidebar.contains(e.target) && !e.target.closest(".navbar-toggler")) {
        sidebar.classList.remove("show")
      }
    }
  })

  // Auto-hide alerts after 5 seconds
  const alerts = document.querySelectorAll(".alert:not(.alert-permanent)")
  alerts.forEach((alert) => {
    setTimeout(() => {
      if (window.bootstrap && window.bootstrap.Alert) {
        const bsAlert = new window.bootstrap.Alert(alert)
        bsAlert.close()
      } else {
        alert.remove()
      }
    }, 5000)
  })

  // Animate counter numbers
  function animateCounters() {
    const counters = document.querySelectorAll(".stat-value")

    counters.forEach((counter) => {
      const text = counter.textContent
      const target = Number.parseInt(text.replace(/[^0-9]/g, ""))

      if (target && target > 0) {
        let current = 0
        const increment = target / 50
        const timer = setInterval(() => {
          current += increment
          if (current >= target) {
            counter.textContent = formatNumber(target, text)
            clearInterval(timer)
          } else {
            counter.textContent = formatNumber(Math.floor(current), text)
          }
        }, 30)
      }
    })
  }

  function formatNumber(num, originalText) {
    if (originalText.includes("$")) {
      return "$" + num.toLocaleString()
    }
    return num.toLocaleString()
  }

  // Run counter animation
  setTimeout(animateCounters, 500)

  // Session timeout warning
  let sessionTimeout
  let warningTimeout

  function resetSessionTimer() {
    clearTimeout(sessionTimeout)
    clearTimeout(warningTimeout)

    // Warn user 5 minutes before session expires (25 minutes)
    warningTimeout = setTimeout(
      () => {
        showSessionWarning()
      },
      25 * 60 * 1000,
    )

    // Auto logout after 30 minutes of inactivity
    sessionTimeout = setTimeout(
      () => {
        window.location.href = "logout.php?reason=timeout"
      },
      30 * 60 * 1000,
    )
  }

  function showSessionWarning() {
    if (confirm("Your session will expire in 5 minutes. Do you want to continue?")) {
      resetSessionTimer()
    } else {
      window.location.href = "logout.php"
    }
  }

  // Track user activity
  const events = ["mousedown", "mousemove", "keypress", "scroll", "touchstart", "click"]
  events.forEach((event) => {
    document.addEventListener(event, resetSessionTimer, true)
  })

  // Initialize session timer
  resetSessionTimer()

  // Logout confirmation
  const logoutLinks = document.querySelectorAll('a[href*="logout.php"]')
  logoutLinks.forEach((link) => {
    link.addEventListener("click", (e) => {
      if (!confirm("Are you sure you want to logout?")) {
        e.preventDefault()
      }
    })
  })

  // Smooth scrolling for anchor links
  document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
    anchor.addEventListener("click", function (e) {
      e.preventDefault()
      const target = document.querySelector(this.getAttribute("href"))
      if (target) {
        target.scrollIntoView({
          behavior: "smooth",
          block: "start",
        })
      }
    })
  })

  // Auto-hide success messages
  const successAlerts = document.querySelectorAll(".alert-success")
  successAlerts.forEach((alert) => {
    setTimeout(() => {
      alert.style.transition = "opacity 0.5s ease"
      alert.style.opacity = "0"
      setTimeout(() => {
        if (alert.parentNode) {
          alert.remove()
        }
      }, 500)
    }, 5000)
  })

  // Handle window resize
  window.addEventListener("resize", () => {
    const sidebar = document.querySelector(".sidebar")
    if (sidebar && window.innerWidth > 768) {
      sidebar.classList.remove("show")
    }
  })

  // Add loading states to buttons
  const buttons = document.querySelectorAll('button[type="submit"]')
  buttons.forEach((button) => {
    button.addEventListener("click", function () {
      if (this.form && this.form.checkValidity()) {
        this.disabled = true
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...'
      }
    })
  })
})

// Global utility functions
function showNotification(message, type = "info") {
  const alertDiv = document.createElement("div")
  alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`
  alertDiv.style.cssText = "top: 20px; right: 20px; z-index: 9999; min-width: 300px;"
  alertDiv.innerHTML = `
    <i class="fas fa-info-circle"></i> ${message}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  `

  document.body.appendChild(alertDiv)

  // Auto remove after 5 seconds
  setTimeout(() => {
    if (alertDiv.parentNode) {
      alertDiv.remove()
    }
  }, 5000)
}

function exportData(format) {
  showNotification(`Exporting data in ${format.toUpperCase()} format...`, "info")
  // Add actual export logic here
}

function printPage() {
  window.print()
}

function toggleMobileMenu() {
  const sidebar = document.getElementById("sidebar")
  if (sidebar) {
    sidebar.classList.toggle("show")
  }
}
