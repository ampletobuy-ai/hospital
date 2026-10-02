<?php
$titleresult = $this->customlib->getTitleName();
$title_name  = !empty($titleresult['name']) ? $titleresult['name'] : product_name();
$product     = product_name();
$logoresult  = $this->customlib->getLogoImage();
$favicon     = !empty($logoresult['mini_logo']) ? base_url('uploads/hospital_content/logo/' . $logoresult['mini_logo']) : base_url('backend/images/brand/qubex-track-logo.png');
$brand_logo  = base_url('backend/images/brand/qubex-track-logo.png');
$page_title  = 'Registration complete';
$csrf_name = $this->security->get_csrf_token_name();
$csrf_hash = $this->security->get_csrf_hash();
$this->load->view('site/partials/register_layout_start', compact('page_title', 'title_name', 'product', 'favicon', 'brand_logo', 'csrf_name', 'csrf_hash'));
?>

<?php
$skip_payment = (($pending['plan_code'] ?? '') === 'trial');
$this->load->view('site/partials/register_steps', array(
    'current' => $skip_payment ? 3 : 4,
    'skip_payment' => $skip_payment,
));
?>

<div class="portal-auth-complete">
  <div class="portal-auth-complete__icon <?php echo !empty($ready) ? 'is-ready' : ''; ?>" aria-hidden="true">
    <?php echo !empty($ready) ? '✓' : '…'; ?>
  </div>
  <header class="portal-auth-header text-center">
    <h1 class="portal-auth-title">Registration complete</h1>
  </header>
  <?php if (!empty($ready)): ?>
    <p class="portal-auth-complete__message">
      Your hospital workspace is ready. Sign in with <strong><?php echo html_escape($pending['email'] ?? ''); ?></strong>.
    </p>
    <a href="<?php echo site_url('site/login'); ?>" class="btn portal-btn-primary portal-auth-submit w-100">Open hospital login</a>
  <?php else: ?>
    <p class="portal-auth-complete__message">Provisioning is still in progress. Refresh in a moment.</p>
    <a href="<?php echo site_url('site/register_complete/' . rawurlencode($pending['uuid'] ?? '')); ?>" class="btn portal-btn-outline w-100">Refresh status</a>
  <?php endif; ?>
</div>

<?php
ob_start();
if (!empty($ready)):
?>
<script>
window.setTimeout(function () {
  window.location.href = <?php echo json_encode(site_url('site/login')); ?>;
}, 1500);
</script>
<?php
endif;
$register_footer_scripts = ob_get_clean();
$this->load->view('site/partials/register_layout_end', array(
    'title_name' => $title_name,
    'register_footer_scripts' => $register_footer_scripts,
));
?>
