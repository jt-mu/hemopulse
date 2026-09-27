  <!-- AUTH MODAL OVERLAY -->
  <div id="authModal" class="auth-overlay">
    <div class="auth-modal-card">
      <button type="button" class="modal-close" aria-label="Close account dialog" onclick="closeAuthModal()">&times;</button>
      
      <div class="modal-art-side">
        <img src="images/model-art.png" alt="Blood donation" onerror="this.src='images/logo.png'">
      </div>

      <div class="modal-form-side">
        <div class="auth-toggle-pill">
          <button type="button" id="tabLoginBtn" class="toggle-btn active" onclick="switchAuthTab('login')">Login</button>
          <button type="button" id="tabRegisterBtn" class="toggle-btn" onclick="switchAuthTab('register')">Register</button>
        </div>

        <!-- LOGIN FORM -->
        <form id="loginForm" class="auth-form" method="post" action="backend/auth_handler.php">
<input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>"><input type="hidden" name="action" value="login">
          <h2 class="auth-title">Welcome Back!</h2>
          <div class="field-group">
            <label for="auth-field-1">Email:</label><input name="email" autocomplete="email" maxlength="100" type="email" required placeholder="name@example.com" id="auth-field-1">
          </div>
          <div class="field-group">
            <label for="auth-field-2">Password:</label><input name="password" autocomplete="current-password" maxlength="72" type="password" required placeholder="••••••••" id="auth-field-2">
          </div>
          <button type="submit" class="btn-modal-yellow">Login</button>
          <a href="contact.php" class="forgot-link">Need help signing in?</a>
        </form>

        <!-- REGISTER FORM -->
        <form id="registerForm" class="auth-form hide" method="post" action="backend/auth_handler.php">
<input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>"><input type="hidden" name="action" value="register">
          <h2 class="auth-title">Create your donor account</h2>
          <div class="field-group">
            <label for="auth-field-3">Email:</label><input name="email" autocomplete="email" maxlength="100" type="email" required placeholder="name@example.com" id="auth-field-3">
          </div>
          <div class="field-group">
            <label for="auth-field-4">First Name:</label><input type="text" name="first_name" autocomplete="given-name" maxlength="50" required placeholder="John" id="auth-field-4">
          </div>
          <div class="field-group">
            <label for="auth-field-5">Last Name:</label><input type="text" name="last_name" autocomplete="family-name" maxlength="50" required placeholder="Doe" id="auth-field-5">
          </div>
          <div class="field-group">
            <label for="auth-middle">Middle name (optional):</label><input name="middle_name" autocomplete="additional-name" maxlength="50" id="auth-middle">
          </div>
          <div class="field-group">
            <label for="auth-field-6">Password:</label><input name="password" autocomplete="new-password" minlength="12" maxlength="72" type="password" required placeholder="••••••••" id="auth-field-6">
            <p class="password-help">Use a password of at least 12 characters.</p>
          </div>
          <div class="terms-check">
            <input type="checkbox" id="termsCheck" name="terms" value="1" required>
            <label for="termsCheck">I agree to the Terms and Conditions.</label>
          </div>
          <button type="submit" class="btn-modal-yellow">Sign up</button>
        </form>
      </div>
    </div>
  </div>
