<!-- AUTH MODAL WITH INTEGRATED FIGMA STYLING -->
<div id="authModal" class="hp-modal-overlay hide" onclick="if(event.target === this) closeAuthModal()">
  <div class="hp-modal-card">
    
    <!-- Top-Right Close Button -->
    <button type="button" class="hp-modal-close" onclick="closeAuthModal()" aria-label="Close">&times;</button>

    <!-- LEFT ARTWORK SIDE -->
    <div class="hp-modal-left">
      <img src="images/model-art.png" alt="Blood Donation Illustration" >
    </div>
    
    <!-- RIGHT FORM SIDE -->
    <div class="hp-modal-right">

      <!-- Dynamic Top Alert Banner -->
      <div id="authAlertBanner" class="hp-modal-alert hide">
        <span id="authAlertMsg">Invalid credentials.</span>
      </div>

      <!-- Pill Toggle (Login / Register) -->
      <div class="hp-pill-toggle">
        <button type="button" id="tabLoginBtn" class="hp-pill-btn" onclick="switchAuthTab('login')">Login</button>
        <button type="button" id="tabRegisterBtn" class="hp-pill-btn active" onclick="switchAuthTab('register')">Register</button>
      </div>

      <!-- 1. LOGIN FORM -->
      <form id="loginForm" class="hp-modal-form" onsubmit="handleLoginSubmit(event)" novalidate>
        <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
        <h2 class="hp-modal-title">Sign in to your account</h2>

        <div class="hp-field">
          <label for="loginEmail" class="hp-label">Email: <span class="hp-req">*</span></label>
          <input type="email" id="loginEmail" name="email" maxlength="100" class="hp-input" placeholder="name@example.com" required autocomplete="email">
        </div>

        <div class="hp-field">
          <label for="loginPassword" class="hp-label">Password: <span class="hp-req">*</span></label>
          <div class="hp-pw-wrap">
            <input type="password" id="loginPassword" name="password" class="hp-input" placeholder="••••••••••••" required autocomplete="current-password">
            <button type="button" class="hp-pw-eye" onclick="togglePasswordVisibility('loginPassword', this)">Show</button>
          </div>
        </div>

        <button type="submit" class="hp-btn-yellow">Sign in</button>
      </form>

      <!-- 2. REGISTER FORM -->
      <form id="registerForm" class="hp-modal-form" onsubmit="handleRegisterSubmit(event)" novalidate>
        <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
        <h2 class="hp-modal-title">Create your donor account</h2>

        <div class="hp-field">
          <label for="regEmail" class="hp-label">Email: <span class="hp-req">*</span></label>
          <input type="email" id="regEmail" name="email" maxlength="100" class="hp-input" placeholder="name@example.com" required autocomplete="email">
        </div>

        <div class="hp-field">
          <label for="regFirstName" class="hp-label">First Name: <span class="hp-req">*</span></label>
          <input type="text" id="regFirstName" name="first_name" maxlength="50" class="hp-input" placeholder="John" required autocomplete="given-name">
        </div>

        <div class="hp-field">
          <label for="regLastName" class="hp-label">Last Name: <span class="hp-req">*</span></label>
          <input type="text" id="regLastName" name="last_name" maxlength="50" class="hp-input" placeholder="Doe" required autocomplete="family-name">
        </div>

        <div class="hp-field">
          <label for="regMiddleName" class="hp-label">Middle name (optional):</label>
          <input type="text" id="regMiddleName" name="middle_name" maxlength="50" class="hp-input" placeholder="" autocomplete="additional-name">
        </div>

        <div class="hp-field">
          <label for="regPassword" class="hp-label">Password: <span class="hp-req">*</span></label>
          <div class="hp-pw-wrap">
            <input type="password" id="regPassword" name="password" class="hp-input" placeholder="••••••••••••" required minlength="12" autocomplete="new-password" oninput="checkPasswordStrength(this.value)">
            <button type="button" class="hp-pw-eye" onclick="togglePasswordVisibility('regPassword', this)">Show</button>
          </div>

          <!-- DYNAMIC PASSWORD STRENGTH METER -->
          <div id="hpStrengthMeter" class="hp-strength-box" style="display: none; margin-top: 6px;">
            <div class="hp-strength-track" style="width: 100%; height: 6px; background-color: rgba(255, 255, 255, 0.7); border-radius: 999px; overflow: hidden;">
              <div id="hpMeterBarFill" style="height: 100%; width: 0%; border-radius: 999px; transition: width 0.25s ease, background-color 0.25s ease;"></div>
            </div>
            <div class="hp-strength-info" style="display: flex; justify-content: flex-end; align-items: center; gap: 5px; margin-top: 4px;">
              <span class="hp-strength-text" style="font-size: 0.72rem; color: #4A5B79; font-weight: 600;">Strength:</span>
              <span id="hpStrengthLabel" class="hp-strength-tag" style="font-size: 0.75rem; font-weight: 800;">Weak</span>
            </div>
          </div>

          <span class="hp-hint">Use 12–72 bytes, a letter and a number or symbol. Avoid common or repeated passwords.</span>
        </div>

        <div class="hp-terms">
          <input type="checkbox" id="termsCheck" name="terms" value="1" class="hp-checkbox" required>
          <label for="termsCheck">
            I agree to the 
            <a href="javascript:void(0)" class="hp-terms-link" onclick="openTermsModal()">Terms of Service</a> 
            and 
            <a href="javascript:void(0)" class="hp-terms-link" onclick="openPrivacyModal()">Clinical Privacy Policy</a>. 
            <span class="hp-req">*</span>
          </label>
        </div>

        <!-- SINGLE SUBMIT BUTTON -->
        <button type="submit" id="regSubmitBtn" class="hp-btn-yellow" disabled>Sign up</button>
      </form>

      <!-- 3. 6-DIGIT EMAIL CODE POPUP (Yellow Card) -->
      <div id="otpPopup" class="hp-otp-popup hide">
        <h3 class="hp-otp-heading">Verify your email</h3>
        <p class="hp-otp-subheading">Please enter 6-digit code</p>

        <button id="otp-resend" class="hp-resend-code" type="button" onclick="handleRegisterSubmit({preventDefault(){},target:document.getElementById('registerForm')})">Resend code</button>
        <p id="otp-feedback" role="status"></p><div class="hp-otp-boxes">
          <input type="text" maxlength="1" pattern="[0-9]" oninput="focusNextOtp(this, 1)" inputmode="numeric">
          <input type="text" maxlength="1" pattern="[0-9]" oninput="focusNextOtp(this, 2)" inputmode="numeric">
          <input type="text" maxlength="1" pattern="[0-9]" oninput="focusNextOtp(this, 3)" inputmode="numeric">
          <input type="text" maxlength="1" pattern="[0-9]" oninput="focusNextOtp(this, 4)" inputmode="numeric">
          <input type="text" maxlength="1" pattern="[0-9]" oninput="focusNextOtp(this, 5)" inputmode="numeric">
          <input type="text" maxlength="1" pattern="[0-9]" oninput="focusNextOtp(this, 6)" inputmode="numeric">
        </div>

        <button type="button" class="hp-btn-yellow hp-btn-otp" onclick="verifyOtpAndRedirect()">Enter Code</button>
      </div>

    </div>
  </div>

  <!-- 4. ACCESSIBLE TERMS & PRIVACY READER MODAL (AT ROOT DIALOG LEVEL) -->
  <div id="termsReaderModal" class="hp-terms-overlay hide" style="display: none;">
    <div class="hp-terms-dialog">
      <div class="hp-terms-header">
        <h3 id="termsDialogTitle" class="hp-terms-title">Terms of Service & Clinical Agreement</h3>
        <button type="button" class="hp-terms-close" onclick="closeTermsModal()" aria-label="Close dialog">&times;</button>
      </div>
      
      <div id="termsDialogBody" class="hp-terms-content">
        <!-- Dynamically injected via openDocModal() in modal.js -->
      </div>

      <div class="hp-terms-footer">
        <button type="button" class="hp-btn-yellow" style="width: auto; padding: 0 1.5rem;" onclick="acceptTermsAndClose()">I Understand & Accept</button>
      </div>
    </div>
  </div>

</div>

<style>
/* MODAL CONTAINER & OVERLAY */
.hp-modal-overlay {
  position: fixed;
  inset: 0;
  background-color: rgba(25, 42, 77, 0.45);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 1.25rem;
  z-index: 999999;
}
.hp-modal-overlay.hide { display: none !important; }
.hp-modal-overlay.open { display: flex !important; }

.hp-modal-card {
  position: relative;
  background-color: #FFFFFF;
  border-radius: 24px;
  width: 100%;
  max-width: 820px;
  display: grid;
  grid-template-columns: 1fr 1.15fr;
  padding: 16px;
  gap: 16px;
  box-shadow: 0 20px 48px rgba(0, 0, 0, 0.2);
  box-sizing: border-box;
  font-family: 'Poppins', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}

.hp-modal-close {
  position: absolute;
  top: 14px;
  right: 18px;
  background: none;
  border: none;
  font-size: 1.5rem;
  font-weight: 800;
  color: #192a4d;
  cursor: pointer;
  z-index: 10;
  line-height: 1;
}

/* LEFT SIDE ART */
.hp-modal-left {
  background-color: #DDEBF0;
  border-radius: 18px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2.5rem 1.5rem;
}
.hp-modal-left img {
  max-width: 85%;
  max-height: 290px;
  object-fit: contain;
  display: block;
}

/* RIGHT SIDE FORM */
.hp-modal-right {
  background-color: #C8D7EB;
  border-radius: 18px;
  padding: 1.6rem 2rem 2rem;
  display: flex;
  flex-direction: column;
  justify-content: center;
  position: relative;
  box-sizing: border-box;
}

/* TOP TOGGLE */
.hp-pill-toggle {
  background-color: #8EAFDB;
  border-radius: 999px;
  padding: 4px;
  display: flex;
  width: fit-content;
  margin: 0 auto 1rem auto;
}
.hp-pill-btn {
  background: none;
  border: none;
  color: #FFFFFF;
  font-family: inherit;
  font-weight: 700;
  font-size: 0.82rem;
  padding: 0.35rem 1.4rem;
  border-radius: 999px;
  cursor: pointer;
  transition: background-color 0.15s ease;
}
.hp-pill-btn.active {
  background-color: #274C77;
  color: #FFFFFF;
}

.hp-modal-title {
  text-align: center;
  font-size: 1.35rem;
  font-weight: 800;
  color: #192a4d;
  margin: 0 0 1rem 0;
  letter-spacing: -0.01em;
}

.hp-modal-form.hide { display: none !important; }

/* FIELDS */
.hp-field {
  margin-bottom: 0.65rem;
}
.hp-label {
  display: block;
  font-size: 0.78rem;
  font-weight: 700;
  color: #192a4d;
  margin-bottom: 0.25rem;
}
.hp-req {
  color: #e11d48 !important;
  font-weight: 800;
}

.hp-input {
  width: 100%;
  height: 40px;
  background-color: #FFFFFF;
  border: 1px solid #CBDCEE;
  border-radius: 10px;
  padding: 0 0.9rem;
  font-size: 0.86rem;
  font-family: inherit;
  color: #192a4d;
  box-sizing: border-box;
  outline: none;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.hp-input:focus {
  border-color: #1877F2;
  box-shadow: 0 0 0 2px rgba(24, 119, 242, 0.2);
}

/* PASSWORD EYE BUTTON WRAPPER */
.hp-pw-wrap {
  position: relative;
  display: flex;
  align-items: center;
}
.hp-pw-wrap input {
  padding-right: 3.8rem !important;
}
.hp-pw-eye {
  position: absolute;
  right: 0.65rem;
  background: transparent;
  border: none;
  font-size: 0.76rem;
  font-weight: 700;
  color: #274C77;
  cursor: pointer;
  padding: 0.25rem 0.35rem;
  user-select: none;
}

.hp-hint {
  display: block;
  font-size: 0.7rem;
  color: #4A5B79;
  font-weight: 500;
  margin-top: 0.25rem;
}

/* TERMS CHECKBOX */
.hp-terms {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  margin: 0.75rem 0 1rem 0;
}
.hp-checkbox {
  width: 15px;
  height: 15px;
  accent-color: #1877F2;
  cursor: pointer;
}
.hp-terms label {
  font-size: 0.74rem;
  font-weight: 600;
  color: #192a4d;
  cursor: pointer;
  user-select: none;
}
.hp-terms-link {
  color: #1877F2;
  text-decoration: underline;
  font-weight: 700;
}

/* YELLOW FIGMA PILL BUTTON */
.hp-btn-yellow {
  width: 100%;
  height: 44px;
  background-color: #F8D149;
  border: none;
  border-radius: 999px;
  color: #192a4d;
  font-family: inherit;
  font-size: 0.92rem;
  font-weight: 800;
  cursor: pointer;
  box-shadow: 0 3px 8px rgba(25, 42, 77, 0.12);
  transition: background-color 0.2s ease, opacity 0.2s ease, transform 0.1s ease;
}
.hp-btn-yellow:hover:not(:disabled) {
  filter: brightness(0.96);
  transform: translateY(-1px);
}

/* DISABLED / INCOMPLETE FORM STATE */
.hp-btn-yellow:disabled,
.hp-btn-yellow[disabled] {
  background-color: #CBD5E1 !important;
  color: #64748B !important;
  border: 1px solid #CBD5E1 !important;
  cursor: not-allowed !important;
  box-shadow: none !important;
  transform: none !important;
  pointer-events: none !important;
  opacity: 0.65;
}

/* OTP POPUP CARD */
.hp-otp-popup {
  position: absolute;
  inset: 1rem;
  background-color: #FEF7CC;
  border-radius: 16px;
  padding: 2rem 1.5rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
  z-index: 10;
}
.hp-otp-popup.hide { display: none !important; }

.hp-otp-heading { font-size: 1.25rem; font-weight: 800; color: #192a4d; margin-bottom: 0.25rem; }
.hp-otp-subheading { font-size: 0.8rem; font-weight: 600; color: #5D6B82; margin-bottom: 1.25rem; }
.hp-otp-boxes { display: flex; gap: 0.5rem; margin-bottom: 1.4rem; }
.hp-otp-boxes input {
  width: 38px;
  height: 48px;
  background: #FFFFFF;
  border: 1.5px solid #E2D795;
  border-radius: 8px;
  text-align: center;
  font-size: 1.25rem;
  font-weight: 800;
  color: #192a4d;
  outline: none;
}
.hp-btn-otp { width: auto; min-width: 170px; padding: 0 1.8rem; }

.hp-modal-alert {
  background-color: #FCE8E8;
  border: 1px solid #F5C6C6;
  border-radius: 12px;
  padding: 0.65rem 1rem;
  color: #9B1D1D;
  font-size: 0.8rem;
  font-weight: 600;
  text-align: center;
  margin-bottom: 0.75rem;
}
.hp-modal-alert.hide { display: none !important; }

/* DEMO & MOBILE TAP-TO-FILL BADGE */
/* TERMS & PRIVACY VIEWER OVERLAY */
.hp-terms-overlay {
  position: absolute;
  inset: 0;
  background-color: rgba(15, 23, 42, 0.75);
  backdrop-filter: blur(4px);
  z-index: 1050;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}
.hp-terms-dialog {
  background: #FFFFFF;
  border-radius: 14px;
  width: 100%;
  max-width: 500px;
  max-height: 80vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 16px 36px rgba(0, 0, 0, 0.25);
  overflow: hidden;
}
.hp-terms-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 1.25rem;
  border-bottom: 1px solid #E2E8F0;
  background-color: #F8FAFC;
}
.hp-terms-title {
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
  color: #1E293B;
}
.hp-terms-close {
  background: none;
  border: none;
  font-size: 1.5rem;
  line-height: 1;
  color: #64748B;
  cursor: pointer;
}
.hp-terms-content {
  padding: 1.25rem;
  overflow-y: auto;
  font-size: 0.85rem;
  color: #334155;
  line-height: 1.6;
}
.hp-terms-content h4 {
  margin-top: 1rem;
  margin-bottom: 0.25rem;
  color: #0F172A;
  font-size: 0.9rem;
}
.hp-terms-footer {
  padding: 0.75rem 1.25rem;
  border-top: 1px solid #E2E8F0;
  display: flex;
  justify-content: flex-end;
  background-color: #F8FAFC;
}

@media (max-width: 720px) {
  .hp-modal-card { grid-template-columns: 1fr; }
  .hp-modal-left { display: none; }
}
</style>