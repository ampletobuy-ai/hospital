<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Smart Hospital - Installation</title>
    <link href="<?php echo base_url(); ?>backend/images/s-favican.png" rel="shortcut icon" type="image/x-icon">
    <link href="<?php echo site_url(); ?>backend/installer_template/assets/css/reset.css" rel="stylesheet">
    <link href="<?php echo site_url(); ?>backend/installer_template/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href='<?php echo site_url(); ?>backend/installer_template/assets/css/font-awesome.min.css' rel='stylesheet' type='text/css'>
    <link href='<?php echo site_url(); ?>backend/installer_template/assets/css/bs-overides.css' rel='stylesheet' type='text/css'>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.1.0/jquery.min.js"></script>
    <style>
        :root {
            --teal:       #00B5AD;
            --teal-dark:  #008C85;
            --teal-light: #E6F7F6;
            --teal-ring:  rgba(0,181,173,0.18);
            --ink:        #1A2B3C;
            --ink-soft:   #52657A;
            --ink-muted:  #9BAAB8;
            --bg:         #F2F5F8;
            --border:     #E0E7EE;
            --white:      #FFFFFF;
            --success:    #10B981;
            --danger:     #EF4444;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--ink);
            min-height: 100vh;
        }

        /* ── Top nav (matches admin panel style) ── */
        .top-nav {
            background: var(--white);
            border-bottom: 1px solid var(--border);
            box-shadow: 0 1px 4px rgba(0,0,0,0.06);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .top-nav-inner {
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 24px;
            height: 62px;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .top-nav img {
            height: 44px;
            width: auto;
            display: block;
        }
        .top-nav-divider {
            width: 1px;
            height: 28px;
            background: var(--border);
        }
        .top-nav-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--ink);
        }
        .top-nav-sub {
            font-size: 11.5px;
            color: var(--ink-muted);
            margin-top: 1px;
        }
        .top-nav-badge {
            margin-left: auto;
            background: var(--teal-light);
            color: var(--teal-dark);
            font-size: 11px;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 20px;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        /* ── Page layout ── */
        .page-wrap {
            max-width: 900px;
            margin: 0 auto;
            padding: 32px 20px 60px;
        }

        /* ── Breadcrumb / page title ── */
        .page-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 6px;
        }
        .page-sub {
            font-size: 13px;
            color: var(--ink-muted);
            margin-bottom: 24px;
        }

        /* ── Step bar ── */
        .step-bar {
            display: flex;
            align-items: flex-start;
            position: relative;
            margin-bottom: 24px;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 20px 24px 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .step-bar::before {
            content: '';
            position: absolute;
            top: 37px;
            left: calc(12.5% + 28px);
            right: calc(12.5% + 28px);
            height: 2px;
            background: var(--border);
            z-index: 0;
        }
        .step-item {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            position: relative;
            z-index: 1;
        }
        .step-circle {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--bg);
            border: 2px solid var(--border);
            color: var(--ink-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            transition: all 0.25s;
        }
        .step-label {
            font-size: 11px;
            font-weight: 600;
            color: var(--ink-muted);
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .step-item.active .step-circle {
            background: var(--teal);
            border-color: var(--teal);
            color: #fff;
            box-shadow: 0 0 0 5px var(--teal-ring);
        }
        .step-item.active .step-label { color: var(--teal-dark); font-weight: 700; }
        .step-item.passed .step-circle {
            background: var(--success);
            border-color: var(--success);
            color: #fff;
        }
        .step-item.passed .step-label { color: var(--success); }

        /* ── Main card (like admin panel cards) ── */
        .inst-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        /* Section header — matches admin "Frontend Modules" style */
        .section-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 20px;
            background: #F8FAFB;
            border-bottom: 1px solid var(--border);
        }
        .section-header-icon {
            width: 32px;
            height: 32px;
            background: var(--teal);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 14px;
            flex-shrink: 0;
        }
        .section-header-text strong {
            display: block;
            font-size: 14px;
            font-weight: 700;
            color: var(--ink);
        }
        .section-header-text span {
            font-size: 12px;
            color: var(--ink-muted);
        }

        .card-body {
            padding: 24px 24px 28px;
        }

        /* ── Alerts ── */
        .alert {
            border-radius: 6px;
            padding: 11px 15px;
            font-size: 13px;
            margin-bottom: 18px;
            border: none;
        }
        .alert-danger  { background: #FEF2F2; color: var(--danger); border-left: 3px solid var(--danger); }
        .alert-success { background: var(--teal-light); color: var(--teal-dark); border-left: 3px solid var(--teal); }

        /* ── Requirements table ── */
        .card-body h3 {
            font-size: 14px;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border);
        }
        .req-table { width: 100%; border-collapse: collapse; }
        .req-table thead th {
            background: #F8FAFB;
            padding: 9px 14px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: var(--ink-soft);
            border-bottom: 1px solid var(--border);
            text-align: left;
        }
        .req-table tbody tr { border-bottom: 1px solid #F1F4F8; }
        .req-table tbody tr:last-child { border-bottom: none; }
        .req-table tbody tr:hover { background: #F8FAFB; }
        .req-table td { padding: 10px 14px; font-size: 13px; color: var(--ink-soft); }
        .req-table td:first-child { font-weight: 500; color: var(--ink); }

        .req-table .label-success {
            background: #D1FAE5 !important; color: #065F46 !important;
            border: none !important; border-radius: 4px; padding: 3px 10px; font-size: 11px; font-weight: 700;
        }
        .req-table .label-danger {
            background: #FEE2E2 !important; color: #991B1B !important;
            border: none !important; border-radius: 4px; padding: 3px 10px; font-size: 11px; font-weight: 700;
        }
        .req-table .label-warning {
            background: #FEF3C7 !important; color: #92400E !important;
            border: none !important; border-radius: 4px; padding: 3px 10px; font-size: 11px; font-weight: 700;
        }

        hr { border: none; border-top: 1px solid var(--border); margin: 16px 0; }

        /* ── Form ── */
        .form-section-title {
            font-size: 13.5px;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 18px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border);
        }
        .form-group { margin-bottom: 16px; }
        .control-label {
            display: block;
            font-size: 12.5px;
            font-weight: 600;
            color: var(--ink-soft);
            margin-bottom: 5px;
        }
        .form-control {
            display: block !important;
            width: 100% !important;
            padding: 9px 12px !important;
            font-size: 13px !important;
            border: 1px solid var(--border) !important;
            border-radius: 6px !important;
            background: var(--white) !important;
            color: var(--ink) !important;
            box-shadow: none !important;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-control:focus {
            border-color: var(--teal) !important;
            box-shadow: 0 0 0 3px var(--teal-ring) !important;
        }

        /* ── Buttons ── */
        .btn-teal {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: var(--teal);
            color: #fff;
            border: none;
            padding: 9px 20px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.18s, transform 0.1s;
            letter-spacing: 0.01em;
        }
        .btn-teal:hover { background: var(--teal-dark); color: #fff; text-decoration: none; }
        .btn-teal:active { transform: scale(0.98); }

        .btn-danger-solid {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: var(--danger);
            color: #fff;
            border: none;
            padding: 9px 20px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: opacity 0.18s;
        }
        .btn-danger-solid:hover { opacity: 0.88; color: #fff; text-decoration: none; }

        .btn-row { display: flex; justify-content: flex-end; margin-top: 20px; }

        /* ── Success screen ── */
        .success-box {
            text-align: center;
            padding: 8px 0 16px;
        }
        .success-icon {
            width: 64px;
            height: 64px;
            background: #D1FAE5;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 26px;
            color: var(--success);
        }
        .success-box h4 {
            font-size: 20px;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 6px;
        }
        .success-box > p {
            font-size: 13px;
            color: var(--ink-soft);
            margin-bottom: 18px;
        }

        .login-panels {
            background: var(--teal-light);
            border: 1px solid #C0E8E6;
            border-radius: 8px;
            padding: 18px 20px;
            margin-top: 24px;
            text-align: left;
        }
        .login-panels h5 {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--teal-dark);
            margin-bottom: 12px;
        }
        .panel-item {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            padding: 11px 0;
            border-bottom: 1px solid #C0E8E6;
        }
        .panel-item:last-child { border-bottom: none; padding-bottom: 0; }
        .panel-badge {
            background: var(--teal);
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            padding: 3px 9px;
            border-radius: 4px;
            white-space: nowrap;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            flex-shrink: 0;
            margin-top: 1px;
        }
        .panel-badge.patient { background: #7C3AED; }
        .panel-item a { color: var(--teal-dark); font-weight: 600; font-size: 13px; word-break: break-all; }
        .panel-item a:hover { color: var(--teal); }
        .panel-item .desc { display: block; color: var(--ink-muted); font-size: 11.5px; margin-top: 2px; }

        /* ── Loading overlay ── */
        #loading {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(26,43,60,0.45);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        #loading.visible { display: flex; }
        .loading-card {
            background: var(--white);
            border-radius: 10px;
            padding: 28px 36px;
            text-align: center;
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
        }
        .loading-card img { width: 56px; height: 56px; display: block; margin: 0 auto 12px; }
        .loading-card p { font-size: 14px; font-weight: 600; color: var(--ink); margin-bottom: 3px; }
        .loading-card small { color: var(--ink-muted); font-size: 12px; }
    </style>
</head>
<body>

<!-- Loading overlay -->
<div id="loading">
    <div class="loading-card">
        <img src="../backend/images/installation.gif" alt="">
        <p>Installing Smart Hospital&hellip;</p>
        <small>Please wait, this may take a moment</small>
    </div>
</div>

<!-- Top nav — matches admin panel style -->
<div class="top-nav">
    <div class="top-nav-inner">
        <a href="<?php echo base_url(); ?>">
            <img src="../backend/images/s_logo.png" alt="Smart Hospital Logo">
        </a>
        <div class="top-nav-divider"></div>
        <div>
            <div class="top-nav-title">Smart Hospital</div>
            <div class="top-nav-sub">Hospital Management System</div>
        </div>
        <div class="top-nav-badge"><i class="fa fa-wrench" style="margin-right:5px"></i>Installer</div>
    </div>
</div>

<div class="page-wrap">

    <div class="page-title">Installation Setup</div>
    <div class="page-sub">Follow the steps below to configure and install Smart Hospital on your server.</div>

    <!-- Step bar -->
    <div class="step-bar">
        <?php
        $steps = [
            1 => ['icon' => 'fa-list-alt',      'label' => 'Requirements'],
            2 => ['icon' => 'fa-database',       'label' => 'Database'],
            3 => ['icon' => 'fa-download',       'label' => 'Install'],
            4 => ['icon' => 'fa-flag-checkered', 'label' => 'Finish'],
        ];
        foreach ($steps as $n => $s):
            $cls = '';
            if ($n < $step || ($n == $step && $step == 4)) $cls = 'passed';
            elseif ($n == $step) $cls = 'active';
        ?>
        <div class="step-item <?php echo $cls; ?>">
            <div class="step-circle">
                <?php if ($cls === 'passed'): ?>
                    <i class="fa fa-check"></i>
                <?php else: ?>
                    <i class="fa <?php echo $s['icon']; ?>"></i>
                <?php endif; ?>
            </div>
            <div class="step-label"><?php echo $s['label']; ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Main card -->
    <div class="inst-card">

        <!-- Section header (like admin panel section headers) -->
        <?php
        $headers = [
            1 => ['fa-list-alt',      'Server Requirements',   'Verify your server meets the minimum requirements'],
            2 => ['fa-database',      'Database Setup',         'Configure your MySQL database connection'],
            3 => ['fa-download',      'Create Admin Account',   'Set up the super administrator login credentials'],
            4 => ['fa-flag-checkered','Installation Complete',  'Smart Hospital has been installed successfully'],
        ];
        [$icon, $title, $sub] = $headers[$step];
        ?>
        <div class="section-header">
            <div class="section-header-icon"><i class="fa <?php echo $icon; ?>"></i></div>
            <div class="section-header-text">
                <strong><?php echo $title; ?></strong>
                <span><?php echo $sub; ?></span>
            </div>
        </div>

        <div class="card-body">

            <?php if ($debug): ?>
                <div class="alert alert-success"><i class="fa fa-check-circle" style="margin-right:6px"></i><?php echo $debug; ?></div>
            <?php endif; ?>

            <?php if (isset($error) && $error != ''): ?>
                <div class="alert alert-danger"><i class="fa fa-exclamation-triangle" style="margin-right:6px"></i><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($step == 1): ?>
                <?php include_once('requirements.php'); ?>

            <?php elseif ($step == 2): ?>
                <form method="post" action="">
                <?php echo form_hidden('step', $step); ?>
                <div class="form-group">
                    <label class="control-label">MySQL Hostname</label>
                    <input type="text" class="form-control" name="hostname" placeholder="e.g. localhost">
                </div>
                <div class="form-group">
                    <label class="control-label">Database Name</label>
                    <input type="text" class="form-control" name="database" placeholder="e.g. smart_hospital">
                </div>
                <div class="form-group">
                    <label class="control-label">Database Username</label>
                    <input type="text" class="form-control" name="username" placeholder="e.g. root">
                </div>
                <div class="form-group">
                    <label class="control-label">Database Password</label>
                    <input type="password" class="form-control" name="password" placeholder="Enter database password">
                </div>
                <div class="btn-row">
                    <button type="submit" class="btn-teal">
                        <i class="fa fa-plug"></i> Check &amp; Connect
                    </button>
                </div>
                </form>

            <?php elseif ($step == 3): ?>
                <form method="post" action="" id="final_submit">
                <?php echo form_hidden('step', $step); ?>
                <div class="form-section-title">Super Admin Details</div>
                <div class="form-group">
                    <label class="control-label">Email Address (Login Username)</label>
                    <input type="email" class="form-control" name="admin_email" id="admin_email" placeholder="admin@hospital.com">
                </div>
                <div class="form-group">
                    <label class="control-label">Password</label>
                    <input type="password" class="form-control" name="admin_password" id="admin_password" placeholder="Choose a strong password">
                </div>
                <div class="form-group">
                    <label class="control-label">Confirm Password</label>
                    <input type="password" class="form-control" name="admin_passwordr" id="admin_passwordr" placeholder="Repeat your password">
                </div>
                <div class="btn-row">
                    <button type="button" class="btn-teal install_btn">
                        <i class="fa fa-rocket"></i> Begin Installation
                    </button>
                </div>
                </form>

            <?php elseif ($step == 4): ?>
                <div class="success-box">
                    <div class="success-icon"><i class="fa fa-check"></i></div>
                    <h4>Installation Successful!</h4>
                    <p>For security, please delete the install directory before logging in.</p>
                    <?php
                        $proto      = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                        $app_root   = preg_replace('#/install/start.*$#', '/', $_SERVER['REQUEST_URI']);
                        $delete_url = $proto . '://' . $_SERVER['HTTP_HOST'] . $app_root . 'temp/delete_install.php';
                    ?>
                    <a href="<?php echo $delete_url; ?>" class="btn-danger-solid">
                        <i class="fa fa-trash"></i> Delete Install Directory &amp; Open Admin Panel
                    </a>
                </div>
                <?php
                    $site_base = $proto . '://' . $_SERVER['HTTP_HOST'];
                    // strip install/start/... from REQUEST_URI to get app root
                    $app_root  = preg_replace('#/install/start.*$#', '/', $_SERVER['REQUEST_URI']);
                    $admin_url   = $site_base . $app_root . 'site/login';
                    $patient_url = $site_base . $app_root . 'site/userlogin';
                ?>
                <div class="login-panels">
                    <h5>Login Panels</h5>
                    <div class="panel-item">
                        <span class="panel-badge">Admin</span>
                        <div>
                            <a href="<?php echo $admin_url; ?>"><?php echo $admin_url; ?></a>
                            <span class="desc">Superadmin, admin, doctor, pharmacist, pathologist, radiologist, accountant, receptionist, nurse</span>
                        </div>
                    </div>
                    <div class="panel-item">
                        <span class="panel-badge patient">Patient</span>
                        <div>
                            <a href="<?php echo $patient_url; ?>"><?php echo $patient_url; ?></a>
                            <span class="desc">Patient self-service portal</span>
                        </div>
                    </div>
                </div>

            <?php endif; ?>

        </div><!-- /.card-body -->
    </div><!-- /.inst-card -->

</div><!-- /.page-wrap -->

<script>
$(document).ready(function () {
    $(document).on('click', '.install_btn', function () {
        $('#loading').addClass('visible');
        $('#final_submit').submit();
    });
});
</script>
</body>
</html>
