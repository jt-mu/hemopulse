window.initializeDonorDashboard = () => {
// js/donor-dashboard.js - Donor Appointments API & Live Registration Form Validation

// 1. DONOR API PANEL CONTROLLER
(() => {
  const root = document.getElementById("donor-api-panel");
  if (!root || root.dataset.controllerReady) return;
  root.dataset.controllerReady="1";

  let page = 1,
    pages = 1,
    sequence = 0;

  const $ = (id) => root.querySelector("#"+id),
    node = (tag, text) => {
      const el = document.createElement(tag);
      if (text !== undefined) el.textContent = text;
      return el;
    };

  async function load() {
    const current = ++sequence;
    try {
      const params = new URLSearchParams(new FormData($("donor-filters")));
      params.set("page", page);
      const response = await fetch("api/index.php/appointments?" + params);
      const result = await response.json();
      if (!root.isConnected) return;

      if (!response.ok) throw new Error(result.message);
      if (current !== sequence) return;

      $("donor-records").replaceChildren();
      for (const record of result.data) {
        const card = node("article");
        card.className = "record-card";
        card.append(
          node("h4", record.title),
          node(
            "p",
            record.campaign_date +
              " · " +
              record.scheduled_time_slot +
              " · " +
              record.location_venue,
          ),
          node(
            "p",
            "Reference " +
              record.public_reference +
              " · " +
              record.appointment_status,
          ),
        );

        if (
          record.appointment_status === "Cancelled" &&
          record.cancellation_reason
        ) {
          card.append(
            node("p", "Cancellation reason: " + record.cancellation_reason),
          );
        }

        if (["Pending", "Confirmed"].includes(record.appointment_status)) {
          const cancel = node("button", "Cancel registration");
          cancel.type = "button";
          cancel.addEventListener("click", () => window.openCancellation({reference:record.public_reference,onSubmit:async reason => {
            cancel.disabled = true;
            try {
              const response = await fetch(
                "api/index.php/appointments/" + record.appointment_id,
                {
                  method: "DELETE",
                  headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-Token": root.dataset.csrf,
                  },
                  body: JSON.stringify({ cancellation_reason: reason }),
                },
              );
              const result = await response.json();
      if (!root.isConnected) return;
              if (!response.ok) throw new Error(result.message);
              window.showNotice("donor-api-notice",result.message,false);
              await load();
              await summary();
            } catch (error) {
              throw error;
            } finally {
              cancel.disabled = false;
            }
          }}));
          card.append(cancel);
        }
        $("donor-records").append(card);
      }

      if (!result.data.length) {
        $("donor-records").append(
          node("p", "No registrations match these filters."),
        );
      }

      page = result.meta.page;
      pages = result.meta.pages;
      $("donor-page").textContent = "Page " + page + " of " + pages;
      $("donor-previous").disabled = page <= 1;
      $("donor-next").disabled = page >= pages;
    } catch (error) {
      if(root.isConnected)window.showNotice(root.querySelector("#donor-api-notice"),error.message || "Unable to load registrations.");
    }
  }

  async function summary() {
    try {
      const response = await fetch("api/index.php/reports");
      const result = await response.json();
      if (!root.isConnected) return;
      if (!response.ok) throw new Error(result.message);

      $("donor-summary").replaceChildren();
      [
        ["Total", result.data.total],
        ["Pending", result.data.counts.Pending],
        ["Completed", result.data.counts.Completed],
        ["Cancelled", result.data.counts.Cancelled],
      ].forEach(([label, value]) => {
        const col = node("div");
        col.className = "col-6 col-md-3";
        const card = node("div", label + ": " + value);
        card.className = "donor-metric";
        col.append(card);
        $("donor-summary").append(col);
      });
    } catch (error) {
      if(root.isConnected)window.showNotice(root.querySelector("#donor-api-notice"),error.message);
    }
  }

  const filtersForm = $("donor-filters");
  if (filtersForm) {
    filtersForm.addEventListener("submit", (event) => {
      event.preventDefault();
      page = 1;
      load();
    });
  }

  const prevBtn = $("donor-previous");
  if (prevBtn) {
    prevBtn.addEventListener("click", () => {
      if (page > 1) {
        page--;
        load();
      }
    });
  }

  const nextBtn = $("donor-next");
  if (nextBtn) {
    nextBtn.addEventListener("click", () => {
      if (page < pages) {
        page++;
        load();
      }
    });
  }

  load();
  summary();
})();

// 2. LIVE CAMPAIGN REGISTRATION & NUMERIC KEYSTROKE GUARD
(() => {
  const regForm =
    document.getElementById("campaignRegForm") ||
    document.querySelector('form[action*="appointment_handler"]') ||
    document.querySelector("form.campaign-reg-form") ||
    document.querySelector("form.registration-form");

  if (!regForm || regForm.dataset.controllerReady) return;
  regForm.dataset.controllerReady="1";

  const contactInput =
    document.getElementById("regContact") ||
    regForm.querySelector('input[name="contact_number"]');
  const timeInput =
    document.getElementById("regPreferredTime") ||
    regForm.querySelector('select[name="scheduled_time_slot"]') ||
    regForm.querySelector('input[name="preferred_time"]');
  const dateInput =
    document.getElementById("regDonationDate") ||
    regForm.querySelector('input[name="donation_date"]');
  const termsCheck =
    document.getElementById("regTermsCheck") ||
    regForm.querySelector('input[name="agree_terms"]') ||
    regForm.querySelector('input[type="checkbox"]');
  const submitBtn =
    document.getElementById("btnSubmitCampaignReg") ||
    regForm.querySelector('button[type="submit"]');

  const errContact = document.getElementById("err_contact_number");
  const errTime = document.getElementById("err_preferred_time");

  // A. Restrict Contact Number strictly to numbers 0-9
  if (contactInput) {
    contactInput.addEventListener("keydown", (e) => {
      const allowedKeys = [
        "Backspace",
        "Tab",
        "Delete",
        "ArrowLeft",
        "ArrowRight",
        "Home",
        "End",
      ];
      if (allowedKeys.includes(e.key) || e.ctrlKey || e.metaKey) {
        return;
      }
      if (!/^[0-9]$/.test(e.key)) {
        e.preventDefault();
      }
    });

    contactInput.addEventListener("paste", (e) => {
      e.preventDefault();
      const pasteData = (e.clipboardData || window.clipboardData).getData(
        "text",
      );
      const cleanDigits = pasteData.replace(/\D/g, "").slice(0, 11);
      contactInput.value = cleanDigits;
      validateCampaignForm();
    });
  }

  // B. Real-time field validation logic
  function validateCampaignForm() {
    let isValid = true;

    // 1. Contact Number Validation (must be 11 digits: 09XXXXXXXXX)
    if (contactInput) {
      const val = contactInput.value.trim();
      const phoneRegex = /^09\d{9}$/;

      if (val.length === 0) {
        if (errContact) {
          errContact.textContent = "Contact number is required.";
          errContact.classList.remove("hide");
        }
        contactInput.classList.add("input-error");
        isValid = false;
      } else if (!phoneRegex.test(val)) {
        if (errContact) {
          errContact.textContent =
            "Enter a valid 11-digit mobile number starting with 09 (e.g. 09171234567).";
          errContact.classList.remove("hide");
        }
        contactInput.classList.add("input-error");
        isValid = false;
      } else {
        if (errContact) errContact.classList.add("hide");
        contactInput.classList.remove("input-error");
      }
    }

    // 2. Donation Hours / Time Slot Validation
    if (timeInput) {
      const val = timeInput.value;
      if (!val) {
        if (errTime) {
          errTime.textContent = "Preferred donation time is required.";
          errTime.classList.remove("hide");
        }
        timeInput.classList.add("input-error");
        isValid = false;
      } else {
        if (errTime) errTime.classList.add("hide");
        timeInput.classList.remove("input-error");
      }
    }

    // 3. Date check
    if (dateInput && !dateInput.value) {
      isValid = false;
    }

    // 4. Agreement Checkbox
    if (termsCheck && !termsCheck.checked) {
      isValid = false;
    }

    // Toggle submit button state
    if (submitBtn) {
      submitBtn.disabled = !isValid;
    }
  }

  // Hook input/change listeners
  regForm.addEventListener("input", validateCampaignForm);
  regForm.addEventListener("change", validateCampaignForm);

  // Run initial evaluation
  validateCampaignForm();
})();
};
if(document.readyState === "loading") document.addEventListener("DOMContentLoaded",window.initializeDonorDashboard);
else window.initializeDonorDashboard();
