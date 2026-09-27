/**
 * Universal Form State Guardian
 * Automatically disables submit buttons until all required & valid fields are satisfied.
 */
(function () {
  "use strict";

  function evaluateFormValidity(form) {
    if (!form) return;

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

  function initUniversalValidation() {
    const forms = document.querySelectorAll("form");

    forms.forEach((form) => {
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

  // Observe dynamically opened modals and tabs (e.g., authModal, dynamic view panels)
  const observer = new MutationObserver(() => {
    const forms = document.querySelectorAll("form");
    forms.forEach(evaluateFormValidity);
  });

  observer.observe(document.body, {
    childList: true,
    subtree: true,
    attributes: true,
    attributeFilter: ["style", "class"],
  });

  window.evaluateFormValidity = evaluateFormValidity;
})();
