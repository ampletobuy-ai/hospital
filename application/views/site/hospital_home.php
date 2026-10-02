<?php
$titleresult = $this->customlib->getTitleName();
$logoresult  = $this->customlib->getLogoImage();
$title_name  = !empty($titleresult['name']) ? $titleresult['name'] : product_name();
$product     = product_name();
$favicon     = !empty($logoresult['mini_logo'])
    ? base_url('uploads/hospital_content/logo/' . $logoresult['mini_logo'])
    : base_url('backend/images/brand/qubex-track-logo.png');
$brand_logo  = base_url('backend/images/brand/qubex-track-logo.png');
$hospital_plans = isset($hospital_plans) ? $hospital_plans : array();
$starter = isset($hospital_plans['hospital_starter']) ? $hospital_plans['hospital_starter'] : array();
$business = isset($hospital_plans['hospital_business']) ? $hospital_plans['hospital_business'] : array();
$enterprise = isset($hospital_plans['hospital_enterprise']) ? $hospital_plans['hospital_enterprise'] : array();
$trial_days = isset($trial_days) ? (int) $trial_days : 14;
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo html_escape($product); ?> — Hospital and clinic management software</title>
<meta name="description" content="Hospital and clinic management software for Indian healthcare teams. Register your workspace, choose a plan, and go live with OPD, IPD, pharmacy, lab, and billing.">
<link rel="shortcut icon" href="<?php echo $favicon; ?>" type="image/x-icon">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="<?php echo base_url('backend/css/qubex-auth.css'); ?>">
</head>
<body class="portal-auth-shell portal-home-shell">

<header class="portal-home-nav">
  <a href="<?php echo site_url('/'); ?>" class="portal-home-nav__brand">
    <img src="<?php echo $brand_logo; ?>" alt="<?php echo html_escape($product); ?>" width="180" height="73" decoding="async">
  </a>
  <nav class="portal-home-nav__links" aria-label="Account">
    <a href="<?php echo site_url('site/login'); ?>">Staff sign in</a>
    <a href="<?php echo site_url('register'); ?>" class="portal-home-nav__cta">Start free trial</a>
  </nav>
</header>

<section class="portal-home-hero">
  <div class="portal-home-hero__copy">
    <h1 class="portal-home-title">Hospital and clinic management software</h1>
    <p class="portal-home-lead">Create your workspace, pick Starter, Business, or Enterprise, and start running OPD, billing, and more in minutes.</p>
    <div class="portal-home-actions">
      <a href="<?php echo site_url('register'); ?>" class="btn portal-btn-primary portal-home-btn">
        Start <?php echo (int) $trial_days; ?>-day free trial
      </a>
      <a href="<?php echo site_url('site/login'); ?>" class="btn portal-home-btn-secondary">Staff sign in</a>
    </div>
    <p class="portal-home-fine">No card required for trial · Prices exclude GST · GST added at checkout</p>
  </div>
</section>

<section class="portal-home-plans" id="plans" aria-labelledby="plansHeading">
  <div class="portal-home-plans__inner">
    <h2 id="plansHeading" class="portal-home-section-title">Plans built for every bed size</h2>
    <p class="portal-home-section-lead">Annual pricing (excl. GST). Upgrade anytime as you add IPD, pharmacy, and branches.</p>

    <div class="portal-home-plan-grid">
      <?php
      $cards = array(
          array('code' => 'hospital_starter', 'label' => 'Starter', 'plan' => $starter, 'badge' => ''),
          array('code' => 'hospital_business', 'label' => 'Business', 'plan' => $business, 'badge' => 'Recommended'),
          array('code' => 'hospital_enterprise', 'label' => 'Enterprise', 'plan' => $enterprise, 'badge' => ''),
      );
      foreach ($cards as $card):
          $plan = $card['plan'];
          $price = $plan_price_inr($plan, 'annual');
          $isBiz = ($card['code'] === 'hospital_business');
      ?>
      <article class="portal-home-plan-card<?php echo $isBiz ? ' portal-home-plan-card--featured' : ''; ?>">
        <?php if ($card['badge'] !== ''): ?>
          <span class="portal-home-plan-badge"><?php echo html_escape($card['badge']); ?></span>
        <?php endif; ?>
        <h3 class="portal-home-plan-name"><?php echo html_escape($card['label']); ?></h3>
        <p class="portal-home-plan-target"><?php echo html_escape($plan['target'] ?? ''); ?></p>
        <p class="portal-home-plan-price">
          <span class="portal-home-plan-amount">₹<?php echo number_format($price); ?></span>
          <span class="portal-home-plan-period">/ year</span>
        </p>
        <ul class="portal-home-plan-points">
          <li><?php echo (int) ($plan['quota']['no_of_staff'] ?? $plan['max_users'] ?? 0); ?> staff seats</li>
          <li><?php echo number_format((int) ($plan['quota']['no_of_patient'] ?? 0)); ?> patients / year</li>
          <?php if (!empty($plan['features']['ipd'])): ?>
            <li>IPD + bed &amp; ward</li>
          <?php else: ?>
            <li>OPD, appointments, billing</li>
          <?php endif; ?>
          <?php if (!empty($plan['features']['multi_branch'])): ?>
            <li>Multi-branch</li>
          <?php elseif (!empty($plan['features']['pharmacy'])): ?>
            <li>Pharmacy + laboratory</li>
          <?php else: ?>
            <li>Basic inventory &amp; reports</li>
          <?php endif; ?>
        </ul>
        <a href="<?php echo site_url('register'); ?>?plan=<?php echo rawurlencode($card['code']); ?>" class="btn <?php echo $isBiz ? 'portal-btn-primary' : 'portal-home-btn-outline'; ?> w-100">
          <?php echo $isBiz ? 'Start with Business trial' : 'Choose ' . html_escape($card['label']); ?>
        </a>
      </article>
      <?php endforeach; ?>
    </div>

    <p class="portal-home-compare-link">
      <button type="button" class="btn btn-link p-0" data-bs-toggle="modal" data-bs-target="#planCompareModal">
        Full feature comparison
      </button>
    </p>
  </div>
</section>

<footer class="portal-home-footer">
  <span>© <?php echo date('Y'); ?> <?php echo html_escape($product); ?></span>
  <span class="portal-auth-footer__dot" aria-hidden="true"></span>
  <a href="<?php echo site_url('site/login'); ?>">Staff sign in</a>
  <span class="portal-auth-footer__dot" aria-hidden="true"></span>
  <a href="mailto:support@qubextrack.com">Support</a>
</footer>

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
        <a href="<?php echo site_url('register'); ?>" class="btn portal-btn-primary">Start free trial</a>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
