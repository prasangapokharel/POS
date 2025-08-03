// Inventory Management JavaScript
document.addEventListener("DOMContentLoaded", () => {
  // Initialize form handlers
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
  // Add Item Form
  const addItemForm = document.getElementById("addItemForm")
  if (addItemForm) {
    addItemForm.addEventListener("submit", function (e) {
      e.preventDefault()
      submitItemForm("add_item", this)
    })
  }

  // Adjust Stock Form
  const adjustStockForm = document.getElementById("adjustStockForm")
  if (adjustStockForm) {
    adjustStockForm.addEventListener("submit", function (e) {
      e.preventDefault()
      submitStockAdjustment(this)
    })
  }

  // Auto-calculate profit margin
  const unitCostInput = document.getElementById("unitCost")
  const sellingPriceInput = document.getElementById("sellingPrice")

  if (unitCostInput && sellingPriceInput) {
    ;[unitCostInput, sellingPriceInput].forEach((input) => {
      input.addEventListener("input", calculateProfitMargin)
    })
  }
}

function showAddItemModal() {
  const modal = new window.bootstrap.Modal(document.getElementById("addItemModal"))
  document.getElementById("addItemForm").reset()
  modal.show()
}

function adjustStock(itemId, itemName, currentStock) {
  document.getElementById("adjustItemId").value = itemId
  document.getElementById("adjustItemName").textContent = itemName
  document.getElementById("adjustCurrentStock").textContent = currentStock + " units"
  document.getElementById("newQuantity").value = currentStock
  document.getElementById("adjustStockForm").reset()

  // Reset form but keep the item info
  document.getElementById("adjustItemId").value = itemId
  document.getElementById("newQuantity").value = currentStock

  const modal = new window.bootstrap.Modal(document.getElementById("adjustStockModal"))
  modal.show()
}

function viewItem(itemId) {
  // Redirect to item details page
  window.location.href = `item_details.php?item_id=${encodeURIComponent(itemId)}`
}

function editItem(itemId) {
  // Redirect to edit item page
  window.location.href = `edit_item.php?item_id=${encodeURIComponent(itemId)}`
}

function submitItemForm(action, form) {
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

  fetch("inventory.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => {
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`)
      }
      return response.json()
    })
    .then((data) => {
      if (data.success) {
        showNotification(data.message, "success")

        // Close modal
        const modal = window.bootstrap.Modal.getInstance(document.getElementById("addItemModal"))
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

function submitStockAdjustment(form) {
  if (!validateForm(form.id)) {
    showNotification("Please fill in all required fields", "error")
    return
  }

  const submitBtn = form.querySelector('button[type="submit"]')
  const originalText = submitBtn.innerHTML

  // Show loading state
  submitBtn.classList.add("loading")
  submitBtn.disabled = true
  submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adjusting...'

  const formData = new FormData(form)
  formData.append("action", "adjust_stock")

  fetch("inventory.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => {
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`)
      }
      return response.json()
    })
    .then((data) => {
      if (data.success) {
        showNotification(data.message, "success")

        // Close modal
        const modal = window.bootstrap.Modal.getInstance(document.getElementById("adjustStockModal"))
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
      showFieldError(field, "This field is required")
    } else {
      field.classList.remove("is-invalid")
      field.classList.add("is-valid")
      hideFieldError(field)
    }
  })

  // Validate numeric fields
  const numericFields = form.querySelectorAll('input[type="number"]')
  numericFields.forEach((field) => {
    const value = field.value.trim()
    if (value && (isNaN(value) || Number.parseFloat(value) < 0)) {
      field.classList.add("is-invalid")
      isValid = false
      showFieldError(field, "Please enter a valid positive number")
    }
  })

  return isValid
}

function showFieldError(field, message) {
  hideFieldError(field)

  const errorDiv = document.createElement("div")
  errorDiv.className = "invalid-feedback"
  errorDiv.textContent = message
  errorDiv.setAttribute("data-field-error", field.name || field.id)

  field.parentNode.appendChild(errorDiv)
}

function hideFieldError(field) {
  const existingError = field.parentNode.querySelector(`[data-field-error="${field.name || field.id}"]`)
  if (existingError) {
    existingError.remove()
  }
}

function calculateProfitMargin() {
  const unitCost = Number.parseFloat(document.getElementById("unitCost").value) || 0
  const sellingPrice = Number.parseFloat(document.getElementById("sellingPrice").value) || 0

  if (unitCost > 0 && sellingPrice > 0) {
    const margin = ((sellingPrice - unitCost) / sellingPrice) * 100
    const marginElement = document.getElementById("profitMargin")

    if (marginElement) {
      marginElement.textContent = margin.toFixed(1) + "%"
      marginElement.className = margin > 0 ? "text-success" : "text-danger"
    }
  }
}

function exportInventory() {
  const items = []
  const itemCards = document.querySelectorAll(".item-card")

  itemCards.forEach((card) => {
    const name = card.querySelector(".card-title").textContent.trim()
    const itemId = card.querySelector(".text-muted.small").textContent.trim()
    const category = card.querySelector(".category-badge")?.textContent.trim() || ""
    const stockText = card.querySelector(".stock-indicator").parentNode.querySelector("span").textContent
    const costs = card.querySelectorAll("strong")

    items.push({
      id: itemId,
      name: name,
      category: category,
      stock: stockText,
      cost: costs[0]?.textContent.trim() || "",
      price: costs[1]?.textContent.trim() || "",
    })
  })

  if (items.length === 0) {
    showNotification("No items to export", "warning")
    return
  }

  // Create CSV content
  const headers = ["Item ID", "Name", "Category", "Stock", "Unit Cost", "Selling Price"]
  const csvContent = [
    headers.join(","),
    ...items.map((item) =>
      [item.id, `"${item.name}"`, `"${item.category}"`, `"${item.stock}"`, item.cost, item.price].join(","),
    ),
  ].join("\n")

  // Download CSV
  const blob = new Blob([csvContent], { type: "text/csv" })
  const url = window.URL.createObjectURL(blob)
  const a = document.createElement("a")
  a.href = url
  a.download = `inventory_${new Date().toISOString().split("T")[0]}.csv`
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  window.URL.revokeObjectURL(url)

  showNotification("Inventory data exported successfully", "success")
}

function showNotification(message, type = "info") {
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

// Stock availability checker for sales
function checkStockAvailability(itemId, quantity, callback) {
  const formData = new FormData()
  formData.append("action", "check_stock")
  formData.append("item_id", itemId)
  formData.append("quantity", quantity)

  fetch("inventory.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (callback) callback(data)
    })
    .catch((error) => {
      console.error("Stock check error:", error)
      if (callback) callback({ available: false, message: "Error checking stock" })
    })
}

// Real-time stock validation for sales forms
function validateStockForSale(itemSelect, quantityInput, feedbackElement) {
  const itemId = itemSelect.value
  const quantity = Number.parseInt(quantityInput.value) || 0

  if (!itemId || quantity <= 0) {
    feedbackElement.textContent = ""
    feedbackElement.className = ""
    return
  }

  checkStockAvailability(itemId, quantity, (result) => {
    if (result.available) {
      feedbackElement.textContent = `✓ Available (${result.current_stock} ${result.unit} in stock)`
      feedbackElement.className = "text-success small"
      quantityInput.classList.remove("is-invalid")
      quantityInput.classList.add("is-valid")
    } else {
      feedbackElement.textContent = `✗ ${result.message} (${result.current_stock || 0} ${result.unit || "units"} available)`
      feedbackElement.className = "text-danger small"
      quantityInput.classList.remove("is-valid")
      quantityInput.classList.add("is-invalid")
    }
  })
}

// Export functions for use in other modules
if (typeof window !== "undefined") {
  window.InventoryManager = {
    checkStockAvailability: checkStockAvailability,
    validateStockForSale: validateStockForSale,
    showNotification: showNotification,
  }
}
