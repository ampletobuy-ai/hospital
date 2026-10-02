<?php
$titleresult = $this->customlib->getTitleName();
$logoresult  = $this->customlib->getLogoImage();
$title_name  = !empty($titleresult['name']) ? $titleresult['name'] : product_name();
$product     = product_name();
$favicon     = !empty($logoresult['mini_logo']) ? base_url('uploads/hospital_content/logo/' . $logoresult['mini_logo']) : base_url('backend/images/brand/qubex-track-logo.png');
$brand_logo  = base_url('backend/images/brand/qubex-track-logo.png');
$page_title  = isset($page_title) ? $page_title : 'Create account';
$promo_headline = isset($promo_headline) ? $promo_headline : 'Start managing care with <em>Qubex Track Hospital</em>';
$promo_subtext  = isset($promo_subtext) ? $promo_subtext : 'Create your hospital workspace, pick a plan, and go live in minutes.';
$csrf_name = $this->security->get_csrf_token_name();
$csrf_hash = $this->security->get_csrf_hash();
$hospital_plans = isset($hospital_plans) ? $hospital_plans : array();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo html_escape($page_title); ?> — <?php echo html_escape($product); ?></title>
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
        <p class="portal-auth-promo__trust-text">Trusted by <strong>healthcare teams</strong> across India</p>
      </div>
    </div>
  </aside>

  <main class="portal-auth-main">
    <div class="portal-auth-main__scroll">
      <div class="portal-auth-main__inner app-auth-main__inner app-auth-main__inner--wide">
        <a href="<?php echo site_url('site/login'); ?>" class="portal-auth-back mb-3 d-inline-flex align-items-center gap-2 text-decoration-none">
          <span aria-hidden="true">←</span> <span>Back to sign in</span>
        </a>
