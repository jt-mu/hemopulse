// js/modal.js - Modal controller, Password Strength Evaluator, & Mobile OTP Handler

// Client-side cache for generated demo OTP
let currentGeneratedOtp = "";

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

// Password Show / Hide Toggle
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

// Real-Time Password Strength Evaluator
function checkPasswordStrength(password) {
  const meterWrap = document.getElementById("hpStrengthMeter");
  const barFill = document.getElementById("hpMeterBarFill");
  const label = document.getElementById("hpStrengthLabel");

  if (!meterWrap || !barFill || !label) return;

  if (!password || password.length === 0) {
    meterWrap.style.setProperty("display", "none", "important");
    return;
  }

  // Force show the strength container
  meterWrap.style.setProperty("display", "block", "important");

  let score = 0;

  // Criteria
  if (password.length >= 8) score += 1;
  if (password.length >= 12) score += 1;
  if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score += 1; // Mixed case
  if (/\d/.test(password)) score += 1; // Digits
  if (/[^a-zA-Z0-9]/.test(password)) score += 1; // Special characters

  if (score <= 2) {
    barFill.style.width = "33%";
    barFill.style.setProperty("background-color", "#e11d48", "important"); // Red
    label.style.setProperty("color", "#be123c", "important");
    label.textContent = "Weak";
  } else if (score === 3 || score === 4) {
    barFill.style.width = "66%";
    barFill.style.setProperty("background-color", "#f59e0b", "important"); // Amber / Orange
    label.style.setProperty("color", "#b45309", "important");
    label.textContent = "Medium";
  } else {
    barFill.style.width = "100%";
    barFill.style.setProperty("background-color", "#10b981", "important"); // Green
    label.style.setProperty("color", "#047857", "important");
    label.textContent = "Strong";
  }
}

// Registration Dispatch to Backend (Generates & Sends Real OTP)
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
      // Store OTP and populate the presentation badge
      if (result.demo_otp) {
        currentGeneratedOtp = String(result.demo_otp);
        const demoVal = document.getElementById("demoOtpValue");
        if (demoVal) demoVal.textContent = currentGeneratedOtp;
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

// Auto-advance cursor through the 6 OTP input boxes
function focusNextOtp(current, index) {
  const inputs = document.querySelectorAll(".hp-otp-boxes input");
  if (current.value.length === 1 && inputs[index]) {
    inputs[index].focus();
  }
}

// Tap-To-Fill Helper for Mobile and Defense Demonstrations
function autoFillOtp() {
  if (!currentGeneratedOtp || currentGeneratedOtp.length !== 6) return;
  const inputs = document.querySelectorAll(".hp-otp-boxes input");
  inputs.forEach((input, idx) => {
    input.value = currentGeneratedOtp[idx] || "";
  });
  const verifyBtn = document.querySelector(".hp-btn-otp");
  if (verifyBtn) verifyBtn.focus();
}

// Verifies OTP with backend, saves account to DB, and authenticates session
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
  const form = e.target;
  const submitBtn = form.querySelector('button[type="submit"]');
  const alertBanner = document.getElementById("authAlertBanner");

  if (alertBanner) {
    alertBanner.classList.add("hide");
    alertBanner.style.setProperty("display", "none", "important");
  }

  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.textContent = "Signing in...";
  }

  try {
    const formData = new FormData(form);

    const response = await fetch("backend/login_handler.php", {
      method: "POST",
      body: formData,
    });

    const result = await response.json();

    if (result.status === "success") {
      window.location.href = result.redirect || "dashboard.php?view=donations";
    } else {
      if (alertBanner) {
        alertBanner.textContent = result.message || "Invalid credentials.";
        alertBanner.classList.remove("hide");
        alertBanner.style.setProperty("display", "block", "important");
      } else {
        alert(result.message || "Invalid credentials.");
      }
    }
  } catch (err) {
    console.error("Login request error:", err);
    alert("Network or server error during sign in.");
  } finally {
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.textContent = "Sign in";
    }
  }
}

function openTermsModal() {
  const modal = document.getElementById("termsReaderModal");
  if (modal) {
    modal.classList.remove("hide");
    modal.style.setProperty("display", "flex", "important");
  }
}

function closeTermsModal() {
  const modal = document.getElementById("termsReaderModal");
  if (modal) {
    modal.classList.add("hide");
    modal.style.setProperty("display", "none", "important");
  }
}

function acceptTermsAndClose() {
  const check = document.getElementById("termsCheck");
  if (check) check.checked = true;
  closeTermsModal();
}
// --- CLINICAL LEGAL & PRIVACY POLICY CONTENT TEMPLATES ---
const LEGAL_DOCS = {
  terms: {
    title: "Terms of Service & Clinical Agreement",
    content: `
      <p class="hp-terms-lead">By registering an account on the <strong>HemoPulse Blood Bank & Donor Management System</strong>, you agree to comply with the following clinical and ethical standards:</p>
      
      <h4>1. Voluntary & Truthful Disclosure</h4>
      <p>Blood donation is a humanitarian medical act. You attest that all personal background details, contact numbers, and medical history submitted during account registration and physical donor questionnaires are truthful, complete, and accurate.</p>

      <h4>2. Mandatory Transfusion Safety Testing</h4>
      <p>Under Republic Act No. 7719 (National Blood Services Act) and WHO Blood Safety Standards, all blood collected will undergo strict laboratory screening for Transfusion-Transmissible Infections (TTIs), including HIV 1 & 2, Hepatitis B, Hepatitis C, Syphilis, and Malaria. Confirmatory testing protocols will apply automatically to reactive units.</p>

      <h4>3. Safe Donor Deferral Protocol</h4>
      <p>HemoPulse and partner medical facilities reserve the right to temporarily or permanently defer blood donation based on screening metrics (e.g., hemoglobin levels, recent tattoos/piercings, medication use, foreign travel to endemic regions) to ensure both donor recovery and recipient safety.</p>

      <h4>4. Emergency Alerts & System Communications</h4>
      <p>You authorize HemoPulse to contact you via SMS, email, or system alerts regarding pre-donation reminders, blood availability alerts matching your blood group, or critical notifications regarding your test results.</p>
    `,
  },
  privacy: {
    title: "Clinical Data Privacy Policy",
    content: `
      <p class="hp-terms-lead">HemoPulse operates in strict compliance with <strong>Republic Act No. 10173 (Philippine Data Privacy Act of 2012)</strong>, <strong>HIPAA Security Safeguards</strong>, and international health data protection guidelines.</p>

      <h4>1. Collection of Sensitive Personal Health Information</h4>
      <p>We collect and process your name, contact channels, birth date, blood type, donation history, and clinical eligibility check results solely for the safe operation of blood bank inventory, recipient matching, and clinical intake verification.</p>

      <h4>2. Strict Confidentiality & Medical Technologist Access</h4>
      <p>Your screening data and laboratory test findings are classified as <strong>Strictly Confidential Medical Records</strong>. Only licensed phlebotomists, medical technologists, and attending blood bank medical directors are granted access to verify eligibility or log donation bags.</p>

      <h4>3. Secure Storage & Non-Commercial Handling</h4>
      <p>Personal and clinical records are encrypted in transit using SSL/TLS and safeguarded in structured database storage. <strong>Your personal, biometric, or health data will never be sold, rented, or repurposed for commercial advertising or third-party marketing under any circumstances.</strong></p>

      <h4>4. Donor Data Rights</h4>
      <p>As a data subject, you hold the legal right to inspect your historical donation records, request correction of erroneous contact information, or request account deactivation subject to statutory medical record retention requirements imposed by health regulatory authorities.</p>
    `,
  },
};

// Open Dialog dynamically with chosen document
function openDocModal(docType) {
  const modal = document.getElementById("termsReaderModal");
  const titleElem = document.getElementById("termsDialogTitle");
  const bodyElem = document.getElementById("termsDialogBody");

  if (!modal || !titleElem || !bodyElem) return;

  const doc = LEGAL_DOCS[docType] || LEGAL_DOCS.terms;
  titleElem.textContent = doc.title;
  bodyElem.innerHTML = doc.content;

  modal.classList.remove("hide");
  modal.style.setProperty("display", "flex", "important");
}

function openTermsModal() {
  openDocModal("terms");
}

function openPrivacyModal() {
  openDocModal("privacy");
}

function closeTermsModal() {
  const modal = document.getElementById("termsReaderModal");
  if (modal) {
    modal.classList.add("hide");
    modal.style.setProperty("display", "none", "important");
  }
}

function acceptTermsAndClose() {
  const check = document.getElementById("termsCheck");
  if (check) check.checked = true;
  closeTermsModal();
}

// Bind functions to window so inline onclick handlers always find them
window.openTermsModal = openTermsModal;
window.openPrivacyModal = openPrivacyModal;
window.openDocModal = openDocModal;
window.closeTermsModal = closeTermsModal;
window.acceptTermsAndClose = acceptTermsAndClose;
window.openTermsModal = openTermsModal;
window.closeTermsModal = closeTermsModal;
window.acceptTermsAndClose = acceptTermsAndClose;

// Bind functions to window to avoid scope collisions
window.openAuthModal = openAuthModal;
window.closeAuthModal = closeAuthModal;
window.switchAuthTab = switchAuthTab;
window.togglePasswordVisibility = togglePasswordVisibility;
window.checkPasswordStrength = checkPasswordStrength;
window.handleRegisterSubmit = handleRegisterSubmit;
window.focusNextOtp = focusNextOtp;
window.autoFillOtp = autoFillOtp;
window.verifyOtpAndRedirect = verifyOtpAndRedirect;
window.handleLoginSubmit = handleLoginSubmit;
