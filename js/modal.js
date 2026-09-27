// js/modal.js - Modal controller and Password Strength Evaluator

function openAuthModal(tab = "register") {
  const modal = document.getElementById("authModal");
  if (!modal) return;

  modal.classList.remove("hide");
  modal.classList.add("open");
  modal.style.setProperty("display", "flex", "important");

  switchAuthTab(tab);
}

function closeAuthModal() {
  const modal = document.getElementById("authModal");
  const otpPopup = document.getElementById("otpPopup");

  if (modal) {
    modal.classList.remove("open");
    modal.classList.add("hide");
    modal.style.setProperty("display", "none", "important");
  }
  if (otpPopup) {
    otpPopup.classList.add("hide");
    otpPopup.style.setProperty("display", "none", "important");
  }
}

function switchAuthTab(tab) {
  const loginForm = document.getElementById("loginForm");
  const regForm = document.getElementById("registerForm");
  const tabLogin = document.getElementById("tabLoginBtn");
  const tabReg = document.getElementById("tabRegisterBtn");
  const otpPopup = document.getElementById("otpPopup");
  const banner = document.getElementById("authAlertBanner");

  if (otpPopup) {
    otpPopup.classList.add("hide");
    otpPopup.style.setProperty("display", "none", "important");
  }
  if (banner) {
    banner.classList.add("hide");
    banner.style.setProperty("display", "none", "important");
  }

  if (tab === "login") {
    if (loginForm) {
      loginForm.classList.remove("hide");
      loginForm.style.setProperty("display", "block", "important");
    }
    if (regForm) {
      regForm.classList.add("hide");
      regForm.style.setProperty("display", "none", "important");
    }
    if (tabLogin) tabLogin.classList.add("active");
    if (tabReg) tabReg.classList.remove("active");
  } else {
    if (loginForm) {
      loginForm.classList.add("hide");
      loginForm.style.setProperty("display", "none", "important");
    }
    if (regForm) {
      regForm.classList.remove("hide");
      regForm.style.setProperty("display", "block", "important");
    }
    if (tabLogin) tabLogin.classList.remove("active");
    if (tabReg) tabReg.classList.add("active");
  }
}

// Bulletproof Password Show / Hide Toggle
function togglePasswordVisibility(fieldId, btnElement) {
  const input = document.getElementById(fieldId);
  const btn = btnElement || (window.event ? window.event.currentTarget : null);
  if (!input) return;

  if (input.type === "password") {
    input.type = "text";
    if (btn) btn.textContent = "Hide";
  } else {
    input.type = "password";
    if (btn) btn.textContent = "Show";
  }
}

// Bulletproof Real-Time Password Strength Evaluator
function checkPasswordStrength(password) {
  const meterWrap = document.getElementById("hpStrengthMeter");
  const barFill = document.getElementById("hpMeterBarFill");
  const label = document.getElementById("hpStrengthLabel");

  if (!meterWrap || !barFill || !label) return;

  if (!password || password.length === 0) {
    meterWrap.style.display = "none";
    return;
  }

  // Force show the strength container
  meterWrap.style.display = "block";

  let score = 0;

  // Criteria
  if (password.length >= 8) score += 1;
  if (password.length >= 12) score += 1;
  if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score += 1; // Mixed case
  if (/\d/.test(password)) score += 1; // Digits
  if (/[^a-zA-Z0-9]/.test(password)) score += 1; // Special characters

  if (score <= 2) {
    barFill.style.width = "33%";
    barFill.style.backgroundColor = "#e11d48"; // Red
    label.style.color = "#be123c";
    label.textContent = "Weak";
  } else if (score === 3 || score === 4) {
    barFill.style.width = "66%";
    barFill.style.backgroundColor = "#f59e0b"; // Amber / Orange
    label.style.color = "#b45309";
    label.textContent = "Medium";
  } else {
    barFill.style.width = "100%";
    barFill.style.backgroundColor = "#10b981"; // Green
    label.style.color = "#047857";
    label.textContent = "Strong";
  }
}

// REAL BACKEND REGISTRATION DISPATCH
async function handleRegisterSubmit(e) {
  e.preventDefault();
  const form = e.target;
  const submitBtn = form.querySelector('button[type="submit"]');

  const terms = form.querySelector('input[name="terms"]');
  if (terms && !terms.checked) {
    alert("Please agree to the Terms and Conditions.");
    return;
  }

  const password = form.querySelector('input[name="password"]').value;
  if (password.length < 12) {
    alert("Password must be at least 12 characters.");
    return;
  }

  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.textContent = "Sending code...";
  }

  try {
    const formData = new FormData(form);
    formData.append("action", "send_otp");

    const response = await fetch("backend/otp_handler.php", {
      method: "POST",
      body: formData,
    });

    const result = await response.json();

    if (result.status === "success") {
      // Demo safety: logs code in console so you're never locked out
      if (result.demo_otp) {
        console.log(
          "%c[HemoPulse OTP Code]: " + result.demo_otp,
          "color: #10b981; font-weight: bold; font-size: 15px;",
        );
      }

      const otpPopup = document.getElementById("otpPopup");
      if (otpPopup) {
        otpPopup.classList.remove("hide");
        otpPopup.style.setProperty("display", "flex", "important");
        const inputs = document.querySelectorAll(".hp-otp-boxes input");
        inputs.forEach((input) => (input.value = ""));
        if (inputs[0]) inputs[0].focus();
      }
    } else {
      alert(result.message || "Failed to dispatch verification code.");
    }
  } catch (err) {
    console.error("OTP send error:", err);
    alert("Server error dispatching verification code.");
  } finally {
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.textContent = "Sign up";
    }
  }
}

function focusNextOtp(current, index) {
  const inputs = document.querySelectorAll(".hp-otp-boxes input");
  if (current.value.length === 1 && inputs[index]) {
    inputs[index].focus();
  }
}

// REAL BACKEND OTP VERIFICATION
async function verifyOtpAndRedirect() {
  const inputs = document.querySelectorAll(".hp-otp-boxes input");
  let fullCode = "";
  inputs.forEach((input) => (fullCode += input.value.trim()));

  if (fullCode.length !== 6) {
    alert("Please enter all 6 digits.");
    return;
  }

  const verifyBtn =
    document.querySelector(".hp-btn-otp") ||
    (window.event ? window.event.currentTarget : null);
  if (verifyBtn) {
    verifyBtn.disabled = true;
    verifyBtn.textContent = "Verifying...";
  }

  try {
    const formData = new FormData();
    formData.append("action", "verify_otp");
    formData.append("otp", fullCode);

    const response = await fetch("backend/otp_handler.php", {
      method: "POST",
      body: formData,
    });

    const result = await response.json();

    if (result.status === "success") {
      window.location.href = result.redirect || "dashboard.php?view=donations";
    } else {
      alert(result.message || "Invalid verification code.");
      if (verifyBtn) {
        verifyBtn.disabled = false;
        verifyBtn.textContent = "Enter Code";
      }
    }
  } catch (err) {
    console.error("OTP verification error:", err);
    alert("Verification failed. Please check server.");
    if (verifyBtn) {
      verifyBtn.disabled = false;
      verifyBtn.textContent = "Enter Code";
    }
  }
}

async function handleLoginSubmit(e) {
  e.preventDefault();
  window.location.href = "dashboard.php?view=donations";
}
