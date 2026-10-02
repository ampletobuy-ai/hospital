$(function () {
  "use strict";

  const cfg = window.hospitalRegisterConfig || {};
  const labels = cfg.labels || {};

  const planSelect = document.getElementById("plan_code");
  const cycleSelect = document.getElementById("billing_cycle");
  const priceDisplay = document.getElementById("planPriceDisplay");
  const reviewNote = document.getElementById("registerReviewNote");
  const submitBtn = document.getElementById("registerSubmitBtn");
  const continueBtn = document.getElementById("registerContinueBtn");
  const backBtn = document.getElementById("registerBackBtn");
  const step1Panel = document.getElementById("registerStep1");
  const step2Panel = document.getElementById("registerStep2");
  const priceNote = document.getElementById("registerPriceNote");
  const stepsWrap = document.getElementById("portalAuthSteps");
  const stepsMeta = document.querySelector(".portal-auth-steps-meta");
  const stepCurrentEl = document.querySelector("[data-step-current]");
  const stepTotalEl = document.querySelector("[data-step-total]");
  const stepItems = document.querySelectorAll(".portal-auth-step");
  const emailInput = document.getElementById("email");
  const phoneInput = document.getElementById("phone");
  const otpSendBtn = document.getElementById("otpSendBtn");
  const otpVerifyBtn = document.getElementById("otpVerifyBtn");
  const otpCodeInput = document.getElementById("otp_code");
  const otpStatus = document.getElementById("otpStatus");
  const otpVerifiedBadge = document.getElementById("otpVerifiedBadge");
  const otpResendHint = document.getElementById("otpResendHint");
  const channelRadios = document.querySelectorAll('input[name="verification_channel"]');
  const form = document.getElementById("registerForm");
  const csrfName = cfg.csrfName || "_token";
  const csrfHash = cfg.csrfHash || "";

  let otpVerified = false;
  let currentStep = 1;
  let resendTimer = null;
  let resendSeconds = 0;

  function toastError(message) {
    if (window.HospitalAuthToast) {
      window.HospitalAuthToast.error(message);
      return;
    }
    if (window.console) {
      console.error(message);
    }
  }

  function toastSuccess(message) {
    if (window.HospitalAuthToast) {
      window.HospitalAuthToast.success(message);
      return;
    }
    if (window.console) {
      console.log(message);
    }
  }

  function isTrialPlan() {
    return !planSelect || planSelect.value === "trial";
  }

  function setWizardPaymentVisible(showPayment) {
    if (!stepsWrap) {
      return;
    }
    stepsWrap.classList.toggle("is-trial-flow", !showPayment);
    stepsWrap.dataset.skipPayment = showPayment ? "0" : "1";
    stepItems.forEach(function (item) {
      if (item.getAttribute("data-step-key") !== "pay") {
        return;
      }
      item.hidden = !showPayment;
      item.classList.toggle("is-skipped", !showPayment);
      if (!showPayment) {
        item.classList.remove("is-active", "is-done");
      }
    });
    if (stepTotalEl) {
      stepTotalEl.textContent = showPayment ? "4" : "3";
    }
  }

  /**
   * Map UI form step (1=account, 2=hospital) to wizard highlight.
   * Paid flow: 1→1, 2→2, then redirect to Pay (3).
   * Trial flow: 1→1, 2→2, Done is step 3 (logical 4 hidden as 3).
   */
  function updateStepIndicator(step) {
    currentStep = step;
    const showPayment = !isTrialPlan();
    setWizardPaymentVisible(showPayment);

    const metaStep = showPayment ? step : Math.min(step, 2);
    if (stepCurrentEl) {
      stepCurrentEl.textContent = String(metaStep);
    }

    stepItems.forEach(function (item) {
      const key = item.getAttribute("data-step-key");
      const logicalNum = parseInt(item.getAttribute("data-step-num"), 10) || 0;
      const indicator = item.querySelector(".portal-auth-step__indicator");

      if (key === "pay") {
        if (!showPayment) {
          return;
        }
        item.classList.remove("is-active", "is-done");
        if (logicalNum < step) {
          item.classList.add("is-done");
          if (indicator) {
            indicator.textContent = "✓";
          }
        } else if (logicalNum === step) {
          item.classList.add("is-active");
          if (indicator) {
            indicator.textContent = "3";
          }
        } else if (indicator) {
          indicator.textContent = "3";
        }
        return;
      }

      item.classList.remove("is-active", "is-done");

      let displayNum = logicalNum;
      if (!showPayment && key === "done") {
        displayNum = 3;
      }

      if (key === "done") {
        // On register page we never reach Done; keep it upcoming.
        if (indicator) {
          indicator.textContent = String(displayNum);
        }
        return;
      }

      if (logicalNum < step) {
        item.classList.add("is-done");
        if (indicator) {
          indicator.textContent = "✓";
        }
      } else if (logicalNum === step) {
        item.classList.add("is-active");
        if (indicator) {
          indicator.textContent = String(displayNum);
        }
      } else if (indicator) {
        indicator.textContent = String(displayNum);
      }
    });
  }

  function showRegisterStep(step) {
    if (step === 1) {
      step1Panel.hidden = false;
      step2Panel.hidden = true;
      priceNote.hidden = true;
      continueBtn.hidden = !otpVerified;
      updateStepIndicator(1);
      return;
    }
    step1Panel.hidden = true;
    step2Panel.hidden = false;
    priceNote.hidden = false;
    continueBtn.hidden = true;
    submitBtn.disabled = false;
    updateStepIndicator(2);
    updatePlanUi();
  }

  function step1FieldsValid() {
    const fields = step1Panel.querySelectorAll("input[required]");
    for (const field of fields) {
      if (!field.checkValidity()) {
        field.reportValidity();
        return false;
      }
    }
    return true;
  }

  function setContinueEnabled() {
    continueBtn.hidden = !otpVerified || currentStep !== 1;
  }

  function selectedChannel() {
    return document.querySelector('input[name="verification_channel"]:checked')?.value || "email";
  }

  function channelValue(channel) {
    return channel === "phone" ? phoneInput.value.trim() : emailInput.value.trim();
  }

  function channelFieldValid(channel) {
    const value = channelValue(channel);
    if (!value) {
      return false;
    }
    if (channel === "email") {
      return emailInput.checkValidity();
    }
    return phoneInput.checkValidity();
  }

  function setOtpStatus(message, isError) {
    otpStatus.textContent = message || "";
    otpStatus.classList.toggle("text-danger", !!isError);
    if (message && isError) {
      toastError(message);
    }
  }

  function setSubmitEnabled() {
    submitBtn.disabled = currentStep !== 2 || !otpVerified;
  }

  function resetVerification() {
    otpVerified = false;
    otpVerifiedBadge.hidden = true;
    otpCodeInput.value = "";
    setOtpStatus("", false);
    setContinueEnabled();
    setSubmitEnabled();
  }

  function startResendCountdown(seconds) {
    resendSeconds = seconds;
    otpSendBtn.disabled = true;
    otpResendHint.hidden = false;
    otpResendHint.textContent = String(labels.otpResendIn || "Resend in :seconds s").replace(
      ":seconds",
      String(resendSeconds)
    );
    if (resendTimer) {
      clearInterval(resendTimer);
    }
    resendTimer = setInterval(function () {
      resendSeconds -= 1;
      if (resendSeconds <= 0) {
        clearInterval(resendTimer);
        resendTimer = null;
        otpSendBtn.disabled = !channelFieldValid(selectedChannel());
        otpResendHint.hidden = true;
        otpSendBtn.textContent = labels.otpResend || "Resend code";
        return;
      }
      otpResendHint.textContent = String(labels.otpResendIn || "Resend in :seconds s").replace(
        ":seconds",
        String(resendSeconds)
      );
    }, 1000);
  }

  async function postJson(url, payload) {
    payload[csrfName] = csrfHash;
    const response = await fetch(url, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      body: JSON.stringify(payload),
    });
    const data = await response.json().catch(function () {
      return {};
    });
    if (!response.ok || data.status === false) {
      const message =
        data.message ||
        (data.errors ? Object.values(data.errors).flat().join(" ") : "") ||
        "Request failed";
      throw new Error(message);
    }
    return data;
  }

  otpSendBtn?.addEventListener("click", async function () {
    const channel = selectedChannel();
    if (!channelFieldValid(channel)) {
      setOtpStatus(labels.otpInvalidIdentifier || "Enter a valid email or phone first.", true);
      return;
    }
    resetVerification();
    otpSendBtn.disabled = true;
    setOtpStatus("", false);
    try {
      const payload = { channel: channel };
      payload[channel] = channelValue(channel);
      const data = await postJson(cfg.sendUrl, payload);
      let statusMessage = data.message || labels.otpSent || "Verification code sent.";
      if (data.debug_otp) {
        otpCodeInput.value = String(data.debug_otp);
        statusMessage += " " + (labels.otpCodeLabel || "Code") + ": " + data.debug_otp;
      }
      setOtpStatus(statusMessage, false);
      otpSendBtn.textContent = labels.otpResend || "Resend code";
      startResendCountdown(60);
    } catch (error) {
      setOtpStatus(error.message, true);
      otpSendBtn.disabled = false;
    }
  });

  otpVerifyBtn?.addEventListener("click", async function () {
    const channel = selectedChannel();
    const code = otpCodeInput.value.trim();
    if (!channelFieldValid(channel) || code.length < 4) {
      setOtpStatus(labels.otpInvalid || "Invalid verification code.", true);
      return;
    }
    otpVerifyBtn.disabled = true;
    try {
      const payload = { channel: channel, otp: code };
      payload[channel] = channelValue(channel);
      const data = await postJson(cfg.verifyUrl, payload);
      otpVerified = true;
      otpVerifiedBadge.hidden = false;
      setOtpStatus(data.message || labels.otpVerified || "Verified", false);
      toastSuccess(data.message || labels.otpVerified || "Verified");
      setContinueEnabled();
    } catch (error) {
      setOtpStatus(error.message, true);
    } finally {
      otpVerifyBtn.disabled = false;
    }
  });

  channelRadios.forEach(function (radio) {
    radio.addEventListener("change", function () {
      resetVerification();
      otpSendBtn.disabled = !channelFieldValid(selectedChannel());
      otpSendBtn.textContent = labels.otpSend || "Send code";
    });
  });

  [emailInput, phoneInput].forEach(function (input) {
    input?.addEventListener("input", function () {
      resetVerification();
      if (!resendTimer) {
        otpSendBtn.disabled = !channelFieldValid(selectedChannel());
      }
    });
  });

  continueBtn?.addEventListener("click", function () {
    if (!otpVerified || !step1FieldsValid()) {
      return;
    }
    showRegisterStep(2);
  });

  backBtn?.addEventListener("click", function () {
    showRegisterStep(1);
    setContinueEnabled();
  });

  function updatePlanUi() {
    const plan = planSelect.value;
    const isTrial = plan === "trial";
    cycleSelect.disabled = isTrial;
    updateStepIndicator(currentStep);
    if (isTrial) {
      priceDisplay.textContent = labels.trialFree || "";
      reviewNote.textContent = labels.reviewTrial || "";
      submitBtn.textContent = labels.submitTrial || "Start free trial";
      return;
    }
    const option = planSelect.selectedOptions[0];
    const cycle = cycleSelect.value;
    const price = cycle === "monthly" ? option.dataset.monthly : option.dataset.annual;
        priceDisplay.textContent =
            (labels.planPrice || "Plan price (excl. GST)") +
            ": ₹" +
            Number(price).toLocaleString("en-IN") +
            " / " +
            cycle +
            " (excl. GST)";
    reviewNote.textContent = labels.reviewPaid || "";
    submitBtn.textContent = labels.submitPaid || "Continue to payment";
  }

  planSelect?.addEventListener("change", updatePlanUi);
  cycleSelect?.addEventListener("change", updatePlanUi);

  $("#registerForm").on("submit", function (e) {
    e.preventDefault();
    if (!otpVerified || currentStep !== 2) {
      toastError(labels.otpInvalid || "Please complete verification first.");
      return;
    }
    const $form = $(this);
    const $btn = $("#registerSubmitBtn");
    const original = $btn.text();
    $btn.prop("disabled", true).html(
      '<span class="spinner-border spinner-border-sm" role="status"></span> Loading...'
    );
    $.ajax({
      type: "POST",
      url: $form.attr("action"),
      data: $form.serialize(),
      dataType: "json",
      headers: {
        "X-Requested-With": "XMLHttpRequest",
        Accept: "application/json",
      },
    })
      .done(function (data) {
        toastSuccess(data.message || "Success");
        if (data.redirect_url && String(data.redirect_url).indexOf("register_checkout") !== -1) {
          updateStepIndicator(3);
        }
        setTimeout(function () {
          window.location.href = data.redirect_url || "site/login";
        }, 800);
      })
      .fail(function (response) {
        $btn.prop("disabled", false).text(original);
        const payload = response.responseJSON || {};
        let message = payload.message || "Registration failed.";
        if (payload.errors) {
          const firstError = Object.values(payload.errors)[0];
          if (Array.isArray(firstError) && firstError.length) {
            message = firstError[0];
          } else if (typeof firstError === "string") {
            message = firstError;
          }
        }
        toastError(message);
      });
  });

  showRegisterStep(1);
  updatePlanUi();
  setContinueEnabled();
  setSubmitEnabled();
});
