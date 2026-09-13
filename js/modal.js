function openAuthModal(tab = 'login') {
  const modal = document.getElementById('authModal');
  if (modal) {
    modal.classList.add('open');
    switchAuthTab(tab);
  }
}

function closeAuthModal() {
  const modal = document.getElementById('authModal');
  const otpPopup = document.getElementById('otpPopup');
  if (modal) modal.classList.remove('open');
  if (otpPopup) otpPopup.classList.add('hide');
}

function switchAuthTab(tab) {
  const loginForm = document.getElementById('loginForm');
  const regForm = document.getElementById('registerForm');
  const tabLogin = document.getElementById('tabLoginBtn');
  const tabReg = document.getElementById('tabRegisterBtn');
  const otpPopup = document.getElementById('otpPopup');

  if (otpPopup) otpPopup.classList.add('hide');

  if (tab === 'login') {
    if (loginForm) loginForm.classList.remove('hide');
    if (regForm) regForm.classList.add('hide');
    if (tabLogin) tabLogin.classList.add('active');
    if (tabReg) tabReg.classList.remove('active');
  } else {
    if (loginForm) loginForm.classList.add('hide');
    if (regForm) regForm.classList.remove('hide');
    if (tabLogin) tabLogin.classList.remove('active');
    if (tabReg) tabReg.classList.add('active');
  }
}

function handleRegisterSubmit(e) {
  e.preventDefault();
  const otpPopup = document.getElementById('otpPopup');
  if (otpPopup) {
    otpPopup.classList.remove('hide');
    const firstInput = document.querySelector('.otp-inputs input');
    if (firstInput) firstInput.focus();
  }
}

function focusNextOtp(current, index) {
  if (current.value.length === 1) {
    const inputs = document.querySelectorAll('.otp-inputs input');
    if (inputs[index]) inputs[index].focus();
  }
}

function verifyOtpAndRedirect() {
  window.location.href = "dashboard.html";
}

function handleLoginSubmit(e) {
  e.preventDefault();
  window.location.href = "dashboard.html";
}