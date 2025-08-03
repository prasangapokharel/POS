// Customer Management JavaScript
document.addEventListener("DOMContentLoaded", () => {
  console.log("Customer management loaded")

  // Initialize tooltips
  var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
  var tooltipList = tooltipTriggerList.map((tooltipTriggerEl) => new window.bootstrap.Tooltip(tooltipTriggerEl))

  // Form submissions
  setupFormHandlers()

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

function setupFormHandlers() {
  // Add Customer Form
  const addCustomerForm = document.getElementById("addCustomerForm")
  if (addCustomerForm) {
    addCustomerForm.addEventListener("submit", function (e) {
      e.preventDefault()
      console.log("Add customer form submitted")
      submitCustomerForm("add_customer", this)
    })
  }

  // Edit Customer Form
  const editCustomerForm = document.getElementById("editCustomerForm")
  if (editCustomerForm) {
    editCustomerForm.addEventListener("submit", function (e) {
      e.preventDefault()
      console.log("Edit customer form submitted")
      submitCustomerForm("update_customer", this)
    })
  }

  // Add Payment Form
  const addPaymentForm = document.getElementById("addPaymentForm")
  if (addPaymentForm) {
    addPaymentForm.addEventListener("submit", function (e) {
      e.preventDefault()
      console.log("Add payment form submitted")
      submitPaymentForm(this)
    })
  }

  // Add Purchase Form
  const addPurchaseForm = document.getElementById("addPurchaseForm")
  if (addPurchaseForm) {
    addPurchaseForm.addEventListener("submit", function (e) {
      e.preventDefault()
      console.log("Add purchase form submitted")
      submitPurchaseForm(this)
    })
  }
}

function showAddCustomerModal() {
  console.log("Showing add customer modal")
  const modal = new window.bootstrap.Modal(document.getElementById("addCustomerModal"))
  document.getElementById("addCustomerForm").reset()
  modal.show()
}

function showAddPaymentModal(customerId) {
  console.log("Showing add payment modal for customer:", customerId)
  document.getElementById("paymentCustomerId").value = customerId
  document.getElementById("addPaymentForm").reset()
  document.getElementById("paymentCustomerId").value = customerId // Reset after form reset

  const modal = new window.bootstrap.Modal(document.getElementById("addPaymentModal"))
  modal.show()
}

function showAddPurchaseModal(customerId) {
  console.log("Showing add purchase modal for customer:", customerId)
  document.getElementById("purchaseCustomerId").value = customerId
  document.getElementById("addPurchaseForm").reset()
  document.getElementById("purchaseCustomerId").value = customerId // Reset after form reset

  const modal = new window.bootstrap.Modal(document.getElementById("addPurchaseModal"))
  modal.show()
}

function calculateTotal() {
  const quantityInput = document.getElementById("quantity")
  const unitPriceInput = document.getElementById("unitPrice")
  const totalPriceInput = document.getElementById("totalPrice")

  if (!quantityInput || !unitPriceInput || !totalPriceInput) {
    return
  }

  const quantity = Number.parseFloat(quantityInput.value) || 0
  const unitPrice = Number.parseFloat(unitPriceInput.value) || 0
  const total = quantity * unitPrice

  totalPriceInput.value = total.toFixed(2)
}

function validateForm(formId) {
  const form = document.getElementById(formId)
  if (!form) return false

  const requiredFields = form.querySelectorAll("[required]")
  let isValid = true

  requiredFields.forEach((field) => {
    const value = field.value.trim()
    if (!value) {
      field.classList.add("is-invalid")
      isValid = false
    } else {
      field.classList.remove("is-invalid")
    }
  })

  // Email validation
  const emailFields = form.querySelectorAll('input[type="email"]')
  emailFields.forEach((field) => {
    const value = field.value.trim()
    if (value && !isValidEmail(value)) {
      field.classList.add("is-invalid")
      isValid = false
    } else if (value || !field.hasAttribute("required")) {
      field.classList.remove("is-invalid")
    }
  })

  return isValid
}

function isValidEmail(email) {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
  return emailRegex.test(email)
}

function submitCustomerForm(action, form) {
  console.log("Submitting customer form with action:", action)

  if (!validateForm(form.id)) {
    showNotification("Please fill in all required fields correctly", "error")
    return
  }

  const submitBtn = form.querySelector('button[type="submit"]')
  const originalText = submitBtn.innerHTML

  // Show loading state
  submitBtn.classList.add("loading")
  submitBtn.disabled = true
  submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...'

  const formData = new FormData(form)
  formData.append("action", action)

  // Log form data for debugging
  for (const [key, value] of formData.entries()) {
    console.log(key, value)
  }

  fetch("list.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => {
      console.log("Response status:", response.status)
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`)
      }
      return response.text().then((text) => {
        console.log("Raw response:", text)
        try {
          return JSON.parse(text)
        } catch (e) {
          console.error("Invalid JSON response:", text)
          throw new Error("Invalid server response: " + text.substring(0, 200))
        }
      })
    })
    .then((data) => {
      console.log("Parsed response:", data)
      if (data.success) {
        showNotification(data.message, "success")

        // Close the appropriate modal
        const modalId = action === "add_customer" ? "addCustomerModal" : "editCustomerModal"
        const modal = window.bootstrap.Modal.getInstance(document.getElementById(modalId))
        if (modal) modal.hide()

        setTimeout(() => {
          window.location.reload()
        }, 1500)
      } else {
        showNotification(data.message, "error")
      }
    })
    .catch((error) => {
      console.error("Error:", error)
      showNotification("An error occurred: " + error.message, "error")
    })
    .finally(() => {
      // Reset button state
      submitBtn.classList.remove("loading")
      submitBtn.disabled = false
      submitBtn.innerHTML = originalText
    })
}

function submitPaymentForm(form) {
  console.log("Submitting payment form")

  const submitBtn = form.querySelector('button[type="submit"]')
  const originalText = submitBtn.innerHTML

  // Show loading state
  submitBtn.classList.add("loading")
  submitBtn.disabled = true
  submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...'

  const formData = new FormData(form)
  formData.append("action", "add_payment")

  fetch("list.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => {
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`)
      }
      return response.text().then((text) => {
        try {
          return JSON.parse(text)
        } catch (e) {
          console.error("Invalid JSON response:", text)
          throw new Error("Invalid server response")
        }
      })
    })
    .then((data) => {
      if (data.success) {
        showNotification(data.message, "success")
        const modal = window.bootstrap.Modal.getInstance(document.getElementById("addPaymentModal"))
        if (modal) modal.hide()
        setTimeout(() => {
          window.location.reload()
        }, 1500)
      } else {
        showNotification(data.message, "error")
      }
    })
    .catch((error) => {
      console.error("Error:", error)
      showNotification("An error occurred while adding payment", "error")
    })
    .finally(() => {
      // Reset button state
      submitBtn.classList.remove("loading")
      submitBtn.disabled = false
      submitBtn.innerHTML = originalText
    })
}

function submitPurchaseForm(form) {
  console.log("Submitting purchase form")

  const submitBtn = form.querySelector('button[type="submit"]')
  const originalText = submitBtn.innerHTML

  // Show loading state
  submitBtn.classList.add("loading")
  submitBtn.disabled = true
  submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...'

  const formData = new FormData(form)
  formData.append("action", "add_purchase")

  fetch("list.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => {
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`)
      }
      return response.text().then((text) => {
        try {
          return JSON.parse(text)
        } catch (e) {
          console.error("Invalid JSON response:", text)
          throw new Error("Invalid server response")
        }
      })
    })
    .then((data) => {
      if (data.success) {
        showNotification(data.message, "success")
        const modal = window.bootstrap.Modal.getInstance(document.getElementById("addPurchaseModal"))
        if (modal) modal.hide()
        setTimeout(() => {
          window.location.reload()
        }, 1500)
      } else {
        showNotification(data.message, "error")
      }
    })
    .catch((error) => {
      console.error("Error:", error)
      showNotification("An error occurred while adding purchase", "error")
    })
    .finally(() => {
      // Reset button state
      submitBtn.classList.remove("loading")
      submitBtn.disabled = false
      submitBtn.innerHTML = originalText
    })
}

function viewCustomer(customerId) {
  console.log("Viewing customer:", customerId)
  const modal = new window.bootstrap.Modal(document.getElementById("customerDetailsModal"))
  const content = document.getElementById("customerDetailsContent")

  // Show loading
  content.innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3">Loading customer details...</p>
        </div>
    `

  modal.show()

  // Fetch customer details from the correct endpoint
  fetch(`customer_details_ajax.php?customer_id=${encodeURIComponent(customerId)}`)
    .then((response) => {
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`)
      }
      return response.text()
    })
    .then((html) => {
      content.innerHTML = html
    })
    .catch((error) => {
      console.error("Error:", error)
      content.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    Failed to load customer details. Please try again.
                    <br><small>Error: ${error.message}</small>
                </div>
            `
    })
}

function editCustomer(customerId, name, email, phone, address, status) {
  console.log("Editing customer:", customerId)
  // Populate the edit form
  document.getElementById("editCustomerId").value = customerId
  document.getElementById("editCustomerName").value = name || ""
  document.getElementById("editCustomerEmail").value = email || ""
  document.getElementById("editCustomerPhone").value = phone || ""
  document.getElementById("editCustomerAddress").value = address || ""
  document.getElementById("editCustomerStatus").value = status || "active"

  // Show the modal
  const modal = new window.bootstrap.Modal(document.getElementById("editCustomerModal"))
  modal.show()
}

function deleteCustomer(customerId) {
  console.log("Deleting customer:", customerId)
  if (confirm("Are you sure you want to delete this customer? This action cannot be undone.")) {
    const formData = new FormData()
    formData.append("action", "delete_customer")
    formData.append("customer_id", customerId)

    fetch("list.php", {
      method: "POST",
      body: formData,
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`)
        }
        return response.text().then((text) => {
          try {
            return JSON.parse(text)
          } catch (e) {
            console.error("Invalid JSON response:", text)
            throw new Error("Invalid server response")
          }
        })
      })
      .then((data) => {
        if (data.success) {
          showNotification(data.message, "success")
          setTimeout(() => {
            window.location.reload()
          }, 1500)
        } else {
          showNotification(data.message, "error")
        }
      })
      .catch((error) => {
        console.error("Error:", error)
        showNotification("An error occurred while deleting customer", "error")
      })
  }
}

function applyFilters() {
  const search = document.getElementById("searchInput")?.value || ""
  const status = document.getElementById("statusFilter")?.value || ""
  const payment = document.getElementById("paymentFilter")?.value || ""

  const params = new URLSearchParams()
  if (search) params.append("search", search)
  if (status) params.append("status", status)
  if (payment) params.append("payment_status", payment)

  window.location.href = "list.php?" + params.toString()
}

function exportCustomers() {
  const customers = []
  const rows = document.querySelectorAll("table tbody tr")

  rows.forEach((row) => {
    const cells = row.querySelectorAll("td")
    if (cells.length > 1) {
      customers.push({
        id: cells[0].textContent.trim(),
        name: cells[1].querySelector("strong")?.textContent.trim() || "",
        email: cells[1].querySelector("small")?.textContent.trim() || "",
        phone: cells[2].textContent.trim(),
        total: cells[3].textContent.trim(),
        paid: cells[4].textContent.trim(),
        pending: cells[5].textContent.trim(),
        status: cells[6].textContent.trim(),
        payment_status: cells[7].textContent.trim(),
      })
    }
  })

  if (customers.length === 0) {
    showNotification("No customers to export", "warning")
    return
  }

  // Create CSV content
  const headers = [
    "Customer ID",
    "Name",
    "Email",
    "Phone",
    "Total Amount",
    "Paid Amount",
    "Pending Amount",
    "Status",
    "Payment Status",
  ]
  const csvContent = [
    headers.join(","),
    ...customers.map((customer) =>
      [
        customer.id,
        `"${customer.name}"`,
        `"${customer.email}"`,
        customer.phone,
        customer.total,
        customer.paid,
        customer.pending,
        customer.status,
        customer.payment_status,
      ].join(","),
    ),
  ].join("\n")

  // Download CSV
  const blob = new Blob([csvContent], { type: "text/csv" })
  const url = window.URL.createObjectURL(blob)
  const a = document.createElement("a")
  a.href = url
  a.download = `customers_${new Date().toISOString().split("T")[0]}.csv`
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  window.URL.revokeObjectURL(url)

  showNotification("Customer data exported successfully", "success")
}

function showNotification(message, type = "info") {
  console.log("Showing notification:", message, type)

  const alertClass =
    type === "success"
      ? "alert-success"
      : type === "error"
        ? "alert-danger"
        : type === "warning"
          ? "alert-warning"
          : "alert-info"

  const icon =
    type === "success"
      ? "fa-check-circle"
      : type === "error"
        ? "fa-exclamation-triangle"
        : type === "warning"
          ? "fa-exclamation-circle"
          : "fa-info-circle"

  const alertDiv = document.createElement("div")
  alertDiv.className = `alert ${alertClass} alert-dismissible fade show position-fixed`
  alertDiv.style.cssText = "top: 20px; right: 20px; z-index: 9999; min-width: 300px; max-width: 500px;"
  alertDiv.innerHTML = `
        <i class="fas ${icon} me-2"></i>
        ${message}
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

// Search functionality
document.getElementById("searchInput")?.addEventListener("keypress", (e) => {
  if (e.key === "Enter") {
    applyFilters()
  }
})

// Auto-calculate total price in purchase form
document.getElementById("quantity")?.addEventListener("input", calculateTotal)
document.getElementById("unitPrice")?.addEventListener("input", calculateTotal)

// Mobile menu toggle
function toggleMobileMenu() {
  const sidebar = document.getElementById("sidebar")
  if (sidebar) {
    sidebar.classList.toggle("show")
  }
}
