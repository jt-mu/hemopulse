function validateRegistration() {
  const emailInput = document.getElementById("email");
  const contactInput = document.getElementById("contact");

  const email = emailInput ? emailInput.value.trim() : "";
  const contact = contactInput ? contactInput.value.trim() : "";

  if (!email) {
    return showError("Email is required");
  }

  const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (!emailPattern.test(email)) {
    return showError("Invalid email format");
  }

  const contactPattern = /^\d{11}$/;
  if (!contactPattern.test(contact)) {
    return showError("Contact number must be exactly 11 digits");
  }

  clearError();
  return true;
}

function showError(message) {
  const errorElement = document.getElementById("errorMessage");
  if (errorElement) {
    errorElement.textContent = `❌ ${message}`;
  }
  return false;
}

function clearError() {
  const errorElement = document.getElementById("errorMessage");
  if (errorElement) {
    errorElement.textContent = "";
  }
}