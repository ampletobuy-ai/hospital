<?php
$status = !empty($subscription['status']) ? (string) $subscription['status'] : 'n/a';
$periodEnd = !empty($subscription['current_period_end'])
    ? $subscription['current_period_end']
    : (!empty($subscription['trial_ends_at']) ? $subscription['trial_ends_at'] : '');
?>
<div class="pb-3">
    <?php if ($this->session->flashdata('msg')) { ?>
        <div><?php echo $this->session->flashdata('msg'); ?></div>
        <?php $this->session->unset_userdata('msg'); ?>
    <?php } ?>

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <h3 class="mb-0">Plan &amp; limits</h3>
        <a class="btn btn-sm btn-primary ms-auto" href="<?php echo html_escape($upgrade_url); ?>">Upgrade / manage subscription</a>
        <?php if ($this->rbac->hasPrivilege('superadmin', 'can_view')) { ?>
            <a class="btn btn-sm btn-outline-secondary" href="<?php echo site_url('admin/planlimits/sync'); ?>">Re-sync permissions</a>
        <?php } ?>
    </div>

    <?php if (empty($plan_enforcing)) { ?>
        <div class="alert alert-warning">Plan enforcement is not active for this hospital (legacy / no subscription). All features are currently unlocked.</div>
    <?php } ?>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Current plan</div>
                    <div class="fs-4 fw-semibold"><?php echo html_escape($plan_name); ?></div>
                    <div class="small mt-1">Code: <?php echo html_escape($plan_code !== '' ? $plan_code : '—'); ?></div>
                    <div class="small">Status: <?php echo html_escape($status); ?></div>
                    <?php if ($periodEnd !== '') { ?>
                        <div class="small">Period / trial end: <?php echo html_escape($periodEnd); ?></div>
                    <?php } ?>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small mb-2">Resource usage</div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Resource</th>
                                    <th class="text-end">Used</th>
                                    <th class="text-end">Limit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $staffLimit = isset($quota_limits['no_of_staff']) ? (int) $quota_limits['no_of_staff'] : null;
                                $patientLimit = isset($quota_limits['no_of_patient']) ? (int) $quota_limits['no_of_patient'] : null;
                                $storageLimitKb = isset($quota_limits['storage']) ? (int) $quota_limits['storage'] : null;
                                ?>
                                <tr>
                                    <td>Staff seats (non-Doctor)</td>
                                    <td class="text-end"><?php echo (int) $usage['no_of_staff']; ?></td>
                                    <td class="text-end"><?php echo $staffLimit !== null ? $staffLimit : '∞'; ?></td>
                                </tr>
                                <tr>
                                    <td>Patients</td>
                                    <td class="text-end"><?php echo (int) $usage['no_of_patient']; ?></td>
                                    <td class="text-end"><?php echo $patientLimit !== null ? $patientLimit : '∞'; ?></td>
                                </tr>
                                <tr>
                                    <td>Storage</td>
                                    <td class="text-end"><?php echo number_format(((int) $usage['storage']) / 1024, 1); ?> MB</td>
                                    <td class="text-end"><?php echo $storageLimitKb !== null ? number_format($storageLimitKb / 1024, 1) . ' MB' : '∞'; ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Features included in your plan</h5>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead>
                    <tr>
                        <th>Feature</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($feature_labels as $key => $label) {
                        $value = array_key_exists($key, $features) ? $features[$key] : null;
                        $on = !($value === false || $value === null || $value === 0 || $value === '0');
                        $detail = '';
                        if (is_string($value) && !in_array($value, array('0', '1'), true)) {
                            $detail = ' (' . html_escape($value) . ')';
                        }
                        ?>
                        <tr>
                            <td><?php echo html_escape($label); ?></td>
                            <td>
                                <?php if ($on) { ?>
                                    <span class="badge text-bg-success">Included<?php echo $detail; ?></span>
                                <?php } else { ?>
                                    <span class="badge text-bg-secondary">Not on plan</span>
                                    <a class="small ms-2" href="<?php echo html_escape($upgrade_url); ?>">Upgrade</a>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
