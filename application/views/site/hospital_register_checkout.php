<?php
$titleresult = $this->customlib->getTitleName();
$title_name  = !empty($titleresult['name']) ? $titleresult['name'] : product_name();
$product     = product_name();
$logoresult  = $this->customlib->getLogoImage();
$favicon     = !empty($logoresult['mini_logo']) ? base_url('uploads/hospital_content/logo/' . $logoresult['mini_logo']) : base_url('backend/images/brand/qubex-track-logo.png');
$brand_logo  = base_url('backend/images/brand/qubex-track-logo.png');
$page_title  = 'Checkout';
$csrf_name = $this->security->get_csrf_token_name();
$csrf_hash = $this->security->get_csrf_hash();
$this->load->view('site/partials/register_layout_start', compact('page_title', 'title_name', 'product', 'favicon', 'brand_logo', 'csrf_name', 'csrf_hash'));
?>

<header class="portal-auth-header">
  <h1 class="portal-auth-title">Complete payment</h1>
  <p class="portal-auth-subtitle"><?php echo !empty($isComplimentary) ? 'Your coupon covers this signup — activate to continue.' : 'Pay securely to activate your hospital workspace.'; ?></p>
</header>

<?php $this->load->view('site/partials/register_steps', array('current' => 3)); ?>

<?php if (!empty($error_message)): ?>
  <div class="portal-flash portal-flash--error"><?php echo html_escape($error_message); ?></div>
<?php elseif ($this->session->flashdata('message')): ?>
  <div class="portal-flash portal-flash--error"><?php echo $this->session->flashdata('message'); ?></div>
<?php elseif (!empty($isComplimentary)): ?>
  <div class="portal-flash portal-flash--success">Complimentary activation — no charge.</div>
<?php elseif (empty($checkout['key_id']) && !empty($allowLocal)): ?>
  <div class="portal-flash portal-flash--info">Local payment mode is enabled for this environment.</div>
<?php endif; ?>

<div class="portal-auth-checkout-card">
  <div class="portal-auth-checkout-card__head">
    <span class="portal-auth-fieldset__num">3</span>
    <div>
      <h2 class="portal-auth-checkout-card__title"><?php echo html_escape($planName ?? ''); ?></h2>
      <p class="portal-auth-checkout-card__hint"><?php echo html_escape($pending['store_name'] ?? ''); ?> · <?php echo html_escape($pending['billing_cycle'] ?? ''); ?></p>
    </div>
  </div>
  <div class="portal-auth-checkout-card__body">
    <div class="portal-details-grid mb-3">
      <div class="portal-details-row">
        <span class="portal-details-row__label">Email</span>
        <span class="portal-details-row__value"><?php echo html_escape($pending['email'] ?? ''); ?></span>
      </div>
      <div class="portal-details-row">
        <span class="portal-details-row__label">List price (excl. GST)</span>
        <span class="portal-details-row__value">₹<?php echo number_format(($listPaise ?? $subtotalPaise) / 100, 2); ?></span>
      </div>
      <?php if (($discountPaise ?? 0) > 0): ?>
      <div class="portal-details-row">
        <span class="portal-details-row__label">Discount (<?php echo html_escape($pending['coupon_code'] ?? ''); ?>)</span>
        <span class="portal-details-row__value text-success">−₹<?php echo number_format($discountPaise / 100, 2); ?></span>
      </div>
      <?php endif; ?>
      <div class="portal-details-row">
        <span class="portal-details-row__label">Subtotal</span>
        <span class="portal-details-row__value">₹<?php echo number_format(($subtotalPaise ?? 0) / 100, 2); ?></span>
      </div>
      <?php if (($gstPaise ?? 0) > 0): ?>
      <div class="portal-details-row">
        <span class="portal-details-row__label">GST (<?php echo (int) ($gstPercent ?? 18); ?>%)</span>
        <span class="portal-details-row__value">₹<?php echo number_format($gstPaise / 100, 2); ?></span>
      </div>
      <?php endif; ?>
      <div class="portal-details-row">
        <span class="portal-details-row__label">Amount due</span>
        <span class="portal-checkout-amount">₹<?php echo number_format(($totalPaise ?? 0) / 100, 2); ?></span>
      </div>
    </div>
    <p class="portal-muted-note mb-0 mt-2">List prices exclude GST. <?php echo (int) ($gstPercent ?? 18); ?>% GST is added at checkout.</p>
  </div>
</div>

<div class="portal-auth-checkout-actions">
  <?php if (!empty($isComplimentary)): ?>
    <form method="post" action="<?php echo site_url('site/register_checkout_activate/' . rawurlencode($pending['uuid'])); ?>">
      <input type="hidden" name="<?php echo html_escape($csrf_name); ?>" value="<?php echo html_escape($csrf_hash); ?>">
      <button type="submit" class="btn portal-btn-primary portal-auth-submit w-100">Activate hospital</button>
    </form>
  <?php else: ?>
    <?php if (!empty($checkout['key_id'])): ?>
      <button type="button" id="hospitalSignupPayBtn" class="btn portal-btn-primary portal-auth-submit w-100">Pay now</button>
    <?php endif; ?>
    <?php if (!empty($allowLocal)): ?>
      <form method="post" action="<?php echo site_url('site/register_checkout_simulate/' . rawurlencode($pending['uuid'])); ?>">
        <input type="hidden" name="<?php echo html_escape($csrf_name); ?>" value="<?php echo html_escape($csrf_hash); ?>">
        <button type="submit" class="btn portal-btn-outline w-100">Simulate payment (local)</button>
      </form>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php
ob_start();
if (empty($isComplimentary) && !empty($checkout['key_id'])):
?>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
document.getElementById('hospitalSignupPayBtn')?.addEventListener('click', function () {
  const button = this;
  const verifyUrl = <?php echo json_encode(site_url('site/register_checkout_verify/' . $pending['uuid'])); ?>;
  const csrfName = <?php echo json_encode($csrf_name); ?>;
  const csrfHash = <?php echo json_encode($csrf_hash); ?>;
  button.disabled = true;
  new Razorpay({
    key: <?php echo json_encode($checkout['key_id']); ?>,
    currency: <?php echo json_encode($checkout['currency'] ?? 'INR'); ?>,
    name: <?php echo json_encode(product_name()); ?>,
    description: <?php echo json_encode($planName ?? 'Hospital plan'); ?>,
    order_id: <?php echo json_encode($checkout['gateway_order_id']); ?>,
    prefill: {
      email: <?php echo json_encode($pending['email'] ?? ''); ?>,
      name: <?php echo json_encode(trim(($pending['first_name'] ?? '') . ' ' . ($pending['last_name'] ?? ''))); ?>,
      contact: <?php echo json_encode($pending['phone'] ?? ''); ?>
    },
    theme: { color: '#2b7568' },
    handler: async function (response) {
      try {
        const body = Object.assign({}, response);
        body[csrfName] = csrfHash;
        const result = await fetch(verifyUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: JSON.stringify(body)
        });
        const payload = await result.json();
        if (!result.ok || !payload.status) {
          throw new Error(payload.message || 'Checkout verification failed.');
        }
        window.location.href = payload.redirect_url;
      } catch (error) {
        if (window.HospitalAuthToast) {
          window.HospitalAuthToast.error(error.message || 'Checkout verification failed.');
        }
        button.disabled = false;
      }
    },
    modal: { ondismiss: function () { button.disabled = false; } }
  }).open();
});
</script>
<?php
endif;
$register_footer_scripts = ob_get_clean();
$this->load->view('site/partials/register_layout_end', array(
    'title_name' => $title_name,
    'register_footer_scripts' => $register_footer_scripts,
));
?>
