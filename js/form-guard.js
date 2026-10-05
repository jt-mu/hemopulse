/**
 * Universal Form State Guardian
 * Automatically disables submit buttons until all required & valid fields are satisfied.
 */
(function () {
  "use strict";

  function evaluateFormValidity(form) {
    if (!form || form.dataset.busy) return;

    // Find submit button(s) belonging to this form
    const submitBtns = form.querySelectorAll(
      'button[type="submit"], input[type="submit"], .btn-submit-action',
    );
    if (!submitBtns.length) return;

    // 1. Native HTML5 validity check (handles required, type="email", minlength, pattern)
    let isFormValid = form.checkValidity();

    // 2. Custom check for minlength edge-cases or special rules (e.g. password fields)
    const passwordInputs = form.querySelectorAll(
      'input[type="password"][minlength]',
    );
    passwordInputs.forEach((pw) => {
      const min = parseInt(pw.getAttribute("minlength"), 10) || 0;
      if (pw.value.length < min) {
        isFormValid = false;
      }
    });

    // 3. Custom check for required checkboxes (like terms & agreements)
    const requiredCheckboxes = form.querySelectorAll(
      'input[type="checkbox"][required]',
    );
    requiredCheckboxes.forEach((cb) => {
      if (!cb.checked) {
        isFormValid = false;
      }
    });

    // 4. Update button states and inline visual feedback
    submitBtns.forEach((btn) => {
      if (isFormValid) {
        btn.removeAttribute("disabled");
        btn.disabled = false;
      } else {
        btn.setAttribute("disabled", "true");
        btn.disabled = true;
      }
    });
  }

  function initUniversalValidation(root = document) {
    if (!root.querySelectorAll) root=document;
    const forms = root.querySelectorAll("form");

    forms.forEach((form) => {
      if(form.dataset.guardReady)return;form.dataset.guardReady="1";
      // Run once on load to disable buttons if empty
      evaluateFormValidity(form);

      // Listen for typing, select dropdown changes, radio/checkbox clicks
      form.addEventListener("input", () => evaluateFormValidity(form));
      form.addEventListener("change", () => evaluateFormValidity(form));
      form.addEventListener("keyup", () => evaluateFormValidity(form));
    });
  }

  // Bind to DOM ready
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initUniversalValidation);
  } else {
    initUniversalValidation();
  }

  window.evaluateFormValidity = evaluateFormValidity;
  window.initializeFormGuard = initUniversalValidation;
})();
