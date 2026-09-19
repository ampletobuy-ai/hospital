<?php
$titleresult = $this->customlib->getTitleName();
$logoresult  = $this->customlib->getLogoImage();
$title_name  = !empty($titleresult['name']) ? $titleresult['name'] : product_name();
$product     = product_name();
$favicon     = !empty($logoresult['mini_logo']) ? base_url('uploads/hospital_content/logo/' . $logoresult['mini_logo']) : base_url('backend/images/brand/qubex-track-logo.png');
$brand_logo  = base_url('backend/images/brand/qubex-track-logo.png');
$page_title  = 'Create hospital account';
$csrf_name = $this->security->get_csrf_token_name();
$csrf_hash = $this->security->get_csrf_hash();
$hospital_plans = isset($hospital_plans) ? $hospital_plans : array();
$starter = isset($hospital_plans['hospital_starter']) ? $hospital_plans['hospital_starter'] : array();
$business = isset($hospital_plans['hospital_business']) ? $hospital_plans['hospital_business'] : array();
$enterprise = isset($hospital_plans['hospital_enterprise']) ? $hospital_plans['hospital_enterprise'] : array();
$plan_price_inr = function ($plan, $cycle = 'annual') {
    $key = ($cycle === 'monthly') ? 'monthly_price_paise' : 'annual_price_paise';
    return isset($plan[$key]) ? (int) round($plan[$key] / 100) : 0;
};
$feature_cell = function ($value) {
    if ($value === true) {
        return '✓';
    }
    if ($value === false || $value === null || $value === '') {
        return '—';
    }
    return html_escape(ucfirst((string) $value));
};
$this->load->view('site/partials/register_layout_start', compact('page_title', 'title_name', 'product', 'favicon', 'brand_logo', 'csrf_name', 'csrf_hash', 'hospital_plans'));
?>

<header class="portal-auth-header">
  <div class="app-auth-brand-desktop mb-2">
    <img src="<?php echo $brand_logo; ?>" alt="<?php echo html_escape($product); ?>" class="track-auth-logo track-auth-logo--lg mx-auto" width="815" height="331" decoding="async">
  </div>
  <h1 class="portal-auth-title">Create your hospital account</h1>
  <p class="portal-auth-subtitle">Verify your contact, choose a plan, and activate your workspace.</p>
</header>

<?php $this->load->view('site/partials/register_steps', array('current' => 1, 'skip_payment' => true)); ?>

<div class="portal-flash portal-flash--info portal-auth-info-note" id="registerPriceNote" hidden>All prices exclude GST. 18% GST is added at checkout.</div>

<form method="post" action="<?php echo site_url('site/register'); ?>" class="portal-auth-form portal-auth-form--register" id="registerForm" novalidate>
  <input type="hidden" name="<?php echo html_escape($csrf_name); ?>" value="<?php echo html_escape($csrf_hash); ?>">

  <div id="registerStep1">
    <fieldset class="portal-auth-fieldset">
      <legend class="portal-auth-fieldset__legend">
        <span class="portal-auth-fieldset__num">1</span>
        Account
      </legend>
      <div class="row g-3">
        <div class="col-12 col-md-6">
          <label for="first_name" class="form-label">First name <span class="portal-required">*</span></label>
          <input type="text" class="form-control portal-auth-input" id="first_name" name="first_name" required autocomplete="given-name">
        </div>
        <div class="col-12 col-md-6">
          <label for="last_name" class="form-label">Last name</label>
          <input type="text" class="form-control portal-auth-input" id="last_name" name="last_name" autocomplete="family-name">
        </div>
        <div class="col-12 col-md-6">
          <label for="email" class="form-label">Email <span class="portal-required">*</span></label>
          <input type="email" class="form-control portal-auth-input" id="email" name="email" required autocomplete="email" placeholder="you@hospital.com">
        </div>
        <div class="col-12 col-md-6">
          <label for="phone" class="form-label">Phone <span class="portal-required">*</span></label>
          <input type="tel" class="form-control portal-auth-input" id="phone" name="phone" required autocomplete="tel" inputmode="tel" minlength="10" maxlength="40" placeholder="10-digit mobile">
        </div>
        <div class="col-12 col-md-6">
          <label for="password" class="form-label">Password <span class="portal-required">*</span></label>
          <input type="password" class="form-control portal-auth-input" id="password" name="password" required minlength="8" autocomplete="new-password" placeholder="At least 8 characters">
        </div>
        <div class="col-12 col-md-6">
          <label for="password_confirmation" class="form-label">Confirm password <span class="portal-required">*</span></label>
          <input type="password" class="form-control portal-auth-input" id="password_confirmation" name="password_confirmation" required minlength="8" autocomplete="new-password">
        </div>
        <div class="col-12">
          <div class="portal-otp-panel" id="portalOtpPanel">
            <p class="portal-muted-note mb-2">Verify your email or phone before continuing.</p>
            <div class="d-flex flex-wrap gap-3 mb-2" role="radiogroup" aria-label="Verification channel">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="verification_channel" id="verification_channel_email" value="email" checked>
                <label class="form-check-label" for="verification_channel_email">Email</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="verification_channel" id="verification_channel_phone" value="phone">
                <label class="form-check-label" for="verification_channel_phone">Phone</label>
              </div>
            </div>
            <div class="d-flex flex-wrap gap-2 mb-2">
              <button type="button" class="btn btn-outline-primary" id="otpSendBtn">Send code</button>
              <span class="portal-muted-note align-self-center" id="otpResendHint" hidden></span>
            </div>
            <div class="row g-2 align-items-end">
              <div class="col-12 col-sm-7">
                <label for="otp_code" class="form-label">Verification code</label>
                <input type="text" class="form-control portal-auth-input" id="otp_code" inputmode="numeric" pattern="[0-9]*" maxlength="6" placeholder="6-digit code" autocomplete="one-time-code">
              </div>
              <div class="col-12 col-sm-5">
                <button type="button" class="btn portal-btn-primary w-100" id="otpVerifyBtn">Verify</button>
              </div>
            </div>
            <p class="portal-muted-note mt-2 mb-0" id="otpStatus" role="status" aria-live="polite"></p>
            <p class="portal-flash portal-flash--success mt-2 mb-0" id="otpVerifiedBadge" hidden>Verified</p>
          </div>
        </div>
      </div>
    </fieldset>
    <button type="button" class="btn portal-btn-primary portal-auth-submit w-100" id="registerContinueBtn" hidden>Continue</button>
  </div>

  <div id="registerStep2" hidden>
    <fieldset class="portal-auth-fieldset">
      <legend class="portal-auth-fieldset__legend">
        <span class="portal-auth-fieldset__num">2</span>
        Hospital
      </legend>
      <div class="row g-3">
        <div class="col-12 col-md-8">
          <label for="store_name" class="form-label">Hospital name <span class="portal-required">*</span></label>
          <input type="text" class="form-control portal-auth-input" id="store_name" name="store_name" required autocomplete="organization" placeholder="City Care Hospital">
        </div>
        <div class="col-12 col-md-4">
          <label for="city" class="form-label">City</label>
          <input type="text" class="form-control portal-auth-input" id="city" name="city" maxlength="80" autocomplete="address-level2" placeholder="e.g. Bhubaneswar">
        </div>
        <div class="col-12 col-md-6">
          <label for="facility_type" class="form-label">Facility type</label>
          <select class="form-select portal-auth-input" id="facility_type" name="facility_type">
            <option value="">Select…</option>
            <option value="clinic">Clinic</option>
            <option value="nursing_home">Nursing home</option>
            <option value="hospital">Hospital</option>
            <option value="multi_branch">Multi-branch / chain</option>
          </select>
        </div>
        <div class="col-12 col-md-6">
          <label for="bed_count" class="form-label">Bed capacity</label>
          <select class="form-select portal-auth-input" id="bed_count" name="bed_count">
            <option value="">Select…</option>
            <option value="under_20">Under 20 (clinic / OPD)</option>
            <option value="20_50">20–50 beds</option>
            <option value="51_100">51–100 beds</option>
            <option value="100_plus">100+ beds</option>
          </select>
        </div>
        <div class="col-12 col-md-6">
          <label for="contact_role" class="form-label">Your role</label>
          <select class="form-select portal-auth-input" id="contact_role" name="contact_role">
            <option value="">Select…</option>
            <option value="owner">Owner / Promoter</option>
            <option value="administrator">Hospital administrator</option>
            <option value="doctor">Doctor</option>
            <option value="it">IT / Operations</option>
            <option value="other">Other</option>
          </select>
        </div>
        <div class="col-12 col-md-6">
          <label for="go_live_timeline" class="form-label">When do you want to go live?</label>
          <select class="form-select portal-auth-input" id="go_live_timeline" name="go_live_timeline">
            <option value="">Select…</option>
            <option value="immediate">Immediately</option>
            <option value="1_2_weeks">In 1–2 weeks</option>
            <option value="1_month">In about a month</option>
            <option value="exploring">Just exploring</option>
          </select>
        </div>
        <div class="col-12 col-md-6">
          <label for="referral_source" class="form-label">How did you hear about us?</label>
          <select class="form-select portal-auth-input" id="referral_source" name="referral_source">
            <option value="">Select…</option>
            <option value="google">Google search</option>
            <option value="referral">Friend / colleague referral</option>
            <option value="partner">Partner / reseller</option>
            <option value="whatsapp">WhatsApp</option>
            <option value="social">Social media</option>
            <option value="event">Event / webinar</option>
            <option value="other">Other</option>
          </select>
        </div>
        <div class="col-12 col-md-6">
          <label for="tax_number" class="form-label">GSTIN</label>
          <input type="text" class="form-control portal-auth-input text-uppercase" id="tax_number" name="tax_number" maxlength="15" autocomplete="off" placeholder="Optional">
        </div>
        <div class="col-12 col-md-6">
          <label for="partner_code" class="form-label">Partner code</label>
          <input type="text" class="form-control portal-auth-input text-uppercase" id="partner_code" name="partner_code" value="<?php echo html_escape($partner_code_prefill ?? ''); ?>" maxlength="32" autocomplete="off">
        </div>
        <div class="col-12 col-md-6">
          <label for="coupon_code" class="form-label">Coupon code</label>
          <input type="text" class="form-control portal-auth-input text-uppercase" id="coupon_code" name="coupon_code" value="<?php echo html_escape($coupon_code_prefill ?? ''); ?>" maxlength="64" autocomplete="off">
        </div>
        <div class="col-12">
          <div class="portal-auth-check">
            <input class="form-check-input" type="checkbox" value="1" id="whatsapp_opt_in" name="whatsapp_opt_in">
            <label class="form-check-label" for="whatsapp_opt_in">
              Yes, contact me on WhatsApp for onboarding help and trial reminders before my trial ends.
            </label>
          </div>
        </div>
      </div>
    </fieldset>

    <fieldset class="portal-auth-fieldset portal-auth-fieldset--follow">
      <legend class="portal-auth-fieldset__legend">Package</legend>
      <div class="row g-3">
        <div class="col-12 col-md-7">
          <label for="plan_code" class="form-label">Plan</label>
          <select class="form-select portal-auth-input" id="plan_code" name="plan_code" required>
            <option value="trial" selected>14-day Business trial (free)</option>
            <option value="hospital_starter"
              data-annual="<?php echo $plan_price_inr($starter, 'annual'); ?>"
              data-monthly="<?php echo $plan_price_inr($starter, 'monthly'); ?>">
              Starter — ₹<?php echo number_format($plan_price_inr($starter, 'annual')); ?>/year excl. GST
            </option>
            <option value="hospital_business"
              data-annual="<?php echo $plan_price_inr($business, 'annual'); ?>"
              data-monthly="<?php echo $plan_price_inr($business, 'monthly'); ?>">
              Business ⭐ — ₹<?php echo number_format($plan_price_inr($business, 'annual')); ?>/year excl. GST
            </option>
            <option value="hospital_enterprise"
              data-annual="<?php echo $plan_price_inr($enterprise, 'annual'); ?>"
              data-monthly="<?php echo $plan_price_inr($enterprise, 'monthly'); ?>">
              Enterprise — ₹<?php echo number_format($plan_price_inr($enterprise, 'annual')); ?>/year excl. GST
            </option>
          </select>
        </div>
        <div class="col-12 col-md-5">
          <label for="billing_cycle" class="form-label">Billing cycle</label>
          <select class="form-select portal-auth-input" id="billing_cycle" name="billing_cycle">
            <option value="annual" selected>Annual</option>
            <option value="monthly">Monthly</option>
          </select>
        </div>
        <div class="col-12">
          <p class="portal-muted-note mb-1" id="planPriceDisplay"></p>
          <button type="button" class="btn btn-link portal-plan-compare-trigger p-0" data-bs-toggle="modal" data-bs-target="#planCompareModal">
            Compare plans &amp; features
          </button>
        </div>
      </div>
    </fieldset>

    <div class="portal-auth-review">
      <p class="portal-muted-note mb-0" id="registerReviewNote">Start a free trial — no payment required now.</p>
    </div>

    <button type="submit" class="btn portal-btn-primary portal-auth-submit w-100" id="registerSubmitBtn" disabled>Start free trial</button>
    <button type="button" class="btn btn-link portal-auth-back-step w-100 mt-2" id="registerBackBtn">Back</button>
  </div>
</form>

<p class="portal-auth-switch">
  Already have an account?
  <a href="<?php echo site_url('site/login'); ?>">Sign in</a>
</p>

<div class="modal fade" id="planCompareModal" tabindex="-1" aria-labelledby="planCompareModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
    <div class="modal-content portal-plan-compare-modal">
      <div class="modal-header">
        <h2 class="modal-title fs-5" id="planCompareModalLabel">Compare plans</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        <div class="portal-plan-compare-wrap portal-plan-compare-wrap--modal">
          <table class="portal-plan-compare table table-sm mb-0">
            <thead>
              <tr>
                <th scope="col"></th>
                <th scope="col">Starter</th>
                <th scope="col">Business ⭐</th>
                <th scope="col">Enterprise</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <th scope="row">Price/year (excl. GST)</th>
                <td>₹<?php echo number_format($plan_price_inr($starter, 'annual')); ?></td>
                <td>₹<?php echo number_format($plan_price_inr($business, 'annual')); ?></td>
                <td>₹<?php echo number_format($plan_price_inr($enterprise, 'annual')); ?></td>
              </tr>
              <tr>
                <th scope="row">Target</th>
                <td><?php echo html_escape($starter['target'] ?? ''); ?></td>
                <td><?php echo html_escape($business['target'] ?? ''); ?></td>
                <td><?php echo html_escape($enterprise['target'] ?? ''); ?></td>
              </tr>
              <?php
              $compare_rows = array(
                  'opd' => 'OPD',
                  'patient_registration' => 'Patient Registration',
                  'appointment' => 'Appointment',
                  'billing' => 'Billing',
                  'ipd' => 'IPD',
                  'bed_ward' => 'Bed/Ward',
                  'pharmacy' => 'Pharmacy',
                  'laboratory' => 'Laboratory',
                  'inventory' => 'Inventory',
                  'tpa_insurance' => 'TPA/Insurance',
                  'doctor_commission' => 'Doctor Commission',
                  'reports' => 'Reports',
                  'whatsapp_sms' => 'WhatsApp/SMS',
                  'multi_branch' => 'Multi-branch',
                  'api_integration' => 'API Integration',
                  'customization' => 'Customization',
                  'support' => 'Support',
              );
              foreach ($compare_rows as $key => $label):
                  $b = $starter['features'][$key] ?? false;
                  $p = $business['features'][$key] ?? false;
                  $e = $enterprise['features'][$key] ?? false;
              ?>
              <tr>
                <th scope="row"><?php echo html_escape($label); ?></th>
                <td><?php echo $feature_cell($b); ?></td>
                <td><?php echo $feature_cell($p); ?></td>
                <td><?php echo $feature_cell($e); ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <p class="portal-muted-note mb-0 me-auto">All prices exclude GST. 18% GST is added at checkout.</p>
        <button type="button" class="btn portal-btn-primary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<?php
ob_start();
?>
<script src="<?php echo base_url('backend/js/hospital-register.js'); ?>"></script>
<script>
window.hospitalRegisterConfig = {
  sendUrl: <?php echo json_encode(site_url('site/register_otp_send')); ?>,
  verifyUrl: <?php echo json_encode(site_url('site/register_otp_verify')); ?>,
  csrfName: <?php echo json_encode($csrf_name); ?>,
  csrfHash: <?php echo json_encode($csrf_hash); ?>,
  labels: {
    otpInvalidIdentifier: 'Enter a valid email or phone first.',
    otpSent: 'Verification code sent.',
    otpCodeLabel: 'Code',
    otpResend: 'Resend code',
    otpSend: 'Send code',
    otpResendIn: 'Resend in :seconds s',
    otpInvalid: 'Invalid verification code.',
    otpVerified: 'Verified',
    stepOf: 'Step :current of 4',
    trialFree: 'Free for 14 days — then Business plan pricing applies (excl. GST).',
    reviewTrial: 'Start a free trial — no payment required now.',
    reviewPaid: 'Continue to secure checkout to activate your hospital.',
    planPrice: 'Plan price (excl. GST)',
    submitTrial: 'Start free trial',
    submitPaid: 'Continue to payment',
    stepOf: 'Step :current of :total'
  }
};
</script>
<?php
$register_footer_scripts = ob_get_clean();
$this->load->view('site/partials/register_layout_end', array(
    'title_name' => $title_name,
    'register_footer_scripts' => $register_footer_scripts,
));
?>
