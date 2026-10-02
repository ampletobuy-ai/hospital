<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Select Hospital</title>
    <link rel="stylesheet" href="<?php echo base_url(); ?>backend/bootstrap/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h4 mb-3">Choose your hospital</h1>
                    <p class="text-muted">Your account is linked to more than one hospital. Select which one to open.</p>

                    <form method="post" action="<?php echo site_url('site/login'); ?>">
                        <input type="hidden" name="username" value="<?php echo htmlspecialchars($login_email ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="password" value="<?php echo htmlspecialchars($login_password ?? '', ENT_QUOTES, 'UTF-8'); ?>">

                        <div class="list-group mb-3">
                            <?php foreach (($tenant_candidates ?? array()) as $tenant) { ?>
                                <?php if (empty($tenant['login_allowed'])) { continue; } ?>
                                <label class="list-group-item list-group-item-action d-flex gap-2 align-items-center">
                                    <input class="form-check-input flex-shrink-0" type="radio" name="tenant_id" value="<?php echo htmlspecialchars($tenant['id'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                    <span><?php echo htmlspecialchars($tenant['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </label>
                            <?php } ?>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Continue</button>
                        <a href="<?php echo site_url('site/login'); ?>" class="btn btn-link w-100 mt-2">Back to login</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
