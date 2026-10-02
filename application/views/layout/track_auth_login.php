<?php
$titleresult = $this->customlib->getTitleName();
$logoresult  = $this->customlib->getLogoImage();
$title_name  = !empty($titleresult['name']) ? $titleresult['name'] : product_name();
$product     = product_name();
$favicon     = !empty($logoresult['mini_logo']) ? base_url('uploads/hospital_content/logo/' . $logoresult['mini_logo']) : base_url('backend/images/brand/qubex-track-logo.png');
$brand_logo  = base_url('backend/images/brand/qubex-track-logo.png');

$auth_portal = isset($auth_portal) ? $auth_portal : 'admin';

if ($auth_portal === 'patient') {
    $form_action       = site_url('site/userlogin');
    $form_title        = $this->lang->line('user_login') ?: 'Patient sign in';
    $form_subtitle     = 'Sign in to your patient portal to continue';
    $promo_headline    = 'Your health journey, <em>digitally connected</em>';
    $promo_subtext     = 'Access appointments, prescriptions, bills, and medical records securely from anywhere.';
    $promo_trust       = 'Trusted by <strong>patients &amp; families</strong> for secure care access';
    $forgot_url        = site_url('site/ufpassword');
    $switch_text       = 'Staff member?';
    $switch_link_text  = $this->lang->line('admin_login') ?: 'Admin sign in';
    $switch_link_url   = site_url('site/login');
    $field_prefix      = 'ul';
} else {
    $form_action       = site_url('site/login');
    $form_title        = 'Welcome back';
    $form_subtitle     = 'Sign in to your account to continue';
    $promo_headline    = 'Welcome to the future of <em>healthcare management</em>';
    $promo_subtext     = 'Manage OPD, IPD, billing, pharmacy, and patient workflows from one unified platform.';
    $promo_trust       = 'Trusted by <strong>healthcare teams</strong> across India';
    $forgot_url        = site_url('site/forgotpassword');
    $switch_text       = 'Patient?';
    $switch_link_text  = $this->lang->line('user_login') ?: 'Patient sign in';
    $switch_link_url   = site_url('site/userlogin');
    $field_prefix      = 'al';
}

try {
    $app_version = $this->customlib->getAppVersion();
} catch (Exception $e) {
    $app_version = '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo html_escape($form_title); ?> — <?php echo html_escape($product); ?></title>
<link rel="shortcut icon" href="<?php echo $favicon; ?>" type="image/x-icon">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="<?php echo base_url('backend/css/qubex-auth.css'); ?>">
</head>
<body class="portal-auth-shell app-auth-shell">

<div class="portal-auth-split">

  <aside class="portal-auth-promo app-auth-promo" aria-hidden="false">
    <div class="portal-auth-promo__inner">
      <div class="portal-auth-promo__content">
        <a href="<?php echo site_url('site/login'); ?>" class="portal-auth-promo__logo-wrap">
          <img src="<?php echo $brand_logo; ?>" alt="<?php echo html_escape($product); ?>" class="track-auth-logo track-auth-logo--promo-lg" width="815" height="331" decoding="async">
        </a>
        <h1 class="portal-auth-promo__headline"><?php echo $promo_headline; ?></h1>
        <p class="portal-auth-promo__subtext"><?php echo html_escape($promo_subtext); ?></p>
      </div>
      <div class="portal-auth-promo__trust">
        <div class="portal-auth-promo__trust-avatars" aria-hidden="true">
          <span></span><span></span><span></span><span></span>
        </div>
        <p class="portal-auth-promo__trust-text"><?php echo $promo_trust; ?></p>
      </div>
    </div>
  </aside>

  <main class="portal-auth-main">
    <div class="portal-auth-main__scroll">
      <div class="portal-auth-main__inner app-auth-main__inner">

        <header class="portal-auth-header">
          <div class="app-auth-brand-desktop mb-3">
            <img src="<?php echo $brand_logo; ?>" alt="<?php echo html_escape($product); ?>" class="track-auth-logo track-auth-logo--lg mx-auto" width="815" height="331" decoding="async">
          </div>
          <h1 class="portal-auth-title"><?php echo html_escape($form_title); ?></h1>
          <p class="portal-auth-subtitle"><?php echo html_escape($form_subtitle); ?></p>
        </header>

        <?php if (isset($error_message) && $error_message !== ''): ?>
          <div class="alert alert-danger portal-auth-alert" role="alert"><?php echo $error_message; ?></div>
        <?php endif; ?>
        <?php if ($this->session->flashdata('message')): ?>
          <div class="alert alert-success portal-auth-alert" role="alert"><?php echo $this->session->flashdata('message'); ?></div>
        <?php endif; ?>

        <form class="portal-auth-form" action="<?php echo $form_action; ?>" method="post" novalidate>
          <?php echo $this->customlib->getCSRF(); ?>

          <div class="row g-3">
            <div class="col-12">
              <label class="form-label" for="<?php echo $field_prefix; ?>-username"><?php echo $this->lang->line('username'); ?></label>
              <input type="text" class="form-control portal-auth-input" id="<?php echo $field_prefix; ?>-username" name="username" placeholder="<?php echo $this->lang->line('username'); ?>" autocomplete="username" value="" required autofocus>
              <span class="qx-field-error"><?php echo form_error('username'); ?></span>
            </div>

            <div class="col-12">
              <label class="form-label" for="<?php echo $field_prefix; ?>-password"><?php echo $this->lang->line('password'); ?></label>
              <div class="app-auth-password-wrap" id="show_hide_password">
                <input type="password" class="form-control portal-auth-input" id="<?php echo $field_prefix; ?>-password" name="password" placeholder="<?php echo $this->lang->line('password'); ?>" autocomplete="current-password" required>
                <button type="button" class="app-auth-password-toggle" aria-label="Show or hide password">
                  <svg class="qx-eye-show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                  <svg class="qx-eye-hide" hidden viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                </button>
              </div>
              <span class="qx-field-error"><?php echo form_error('password'); ?></span>
            </div>
          </div>

          <?php if (!empty($is_captcha)): ?>
          <div>
            <div class="qx-captcha-row">
              <div class="qx-captcha-img" id="captcha_image"><?php echo $captcha_image; ?></div>
              <div>
                <label class="form-label" for="<?php echo $field_prefix; ?>-captcha"><?php echo $this->lang->line('enter_captcha'); ?></label>
                <input type="text" class="form-control portal-auth-input" id="<?php echo $field_prefix; ?>-captcha" name="captcha" placeholder="<?php echo $this->lang->line('enter_captcha'); ?>" autocomplete="off">
              </div>
            </div>
            <button type="button" class="qx-captcha-refresh" onclick="refreshCaptcha()">
              <svg viewBox="0 0 16 16" width="12" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 8A6 6 0 1 1 8 2a6 6 0 0 1 4.24 1.76L14 2v4h-4l1.6-1.6"/></svg>
              <?php echo $this->lang->line('refresh_captcha') ?: 'Refresh captcha'; ?>
            </button>
            <span class="qx-field-error"><?php echo form_error('captcha'); ?></span>
          </div>
          <?php endif; ?>

          <div class="app-auth-form-meta">
            <a href="<?php echo $forgot_url; ?>" class="app-auth-forgot"><?php echo $this->lang->line('forgot_password'); ?>?</a>
          </div>

          <button type="submit" class="btn portal-btn-primary portal-auth-submit w-100">
            <?php echo $this->lang->line('sign_in'); ?>
          </button>
        </form>

        <?php
        $this->config->load('tenancy-config');
        $this->config->load('hospital_portal');
        $saas_register = (bool) $this->config->item('hospital_saas_register')
            || (bool) $this->config->item('saas_register');
        if ($auth_portal === 'admin' && $saas_register):
        ?>
        <p class="portal-auth-switch">
          New hospital?
          <a href="<?php echo site_url('site/register'); ?>">Create account</a>
        </p>
        <?php endif; ?>

        <p class="portal-auth-switch">
          <?php echo html_escape($switch_text); ?>
          <a href="<?php echo $switch_link_url; ?>"><?php echo html_escape($switch_link_text); ?></a>
        </p>

        <?php if (!empty($app_version)): ?>
        <p class="app-auth-version portal-muted-note text-center mb-0">
          Version: <?php echo html_escape($app_version); ?>
        </p>
        <?php endif; ?>
      </div>

      <footer class="portal-auth-footer app-auth-footer">
        <span>© <?php echo date('Y'); ?> <?php echo html_escape($title_name); ?></span>
        <span class="portal-auth-footer__dot" aria-hidden="true"></span>
        <a href="mailto:support@qubextrack.com">Support</a>
      </footer>
    </div>
  </main>

</div>

<script>window.qxLoginBaseUrl = <?php echo json_encode(base_url()); ?>;</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo base_url('backend/js/qubex-login.js'); ?>"></script>
</body>
</html>
