<div class="row">
    <?php $this->load->view('setting/sidebar'); ?>
    <div class="col-md-10">
        <div class="card">
            <div class="card-header ptbnull d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h5 class="card-title mb-0"><?php echo $this->lang->line('modules'); ?></h5>
                <ul class="nav nav-pills sh-segmented-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="tab_system_btn" data-bs-toggle="tab" data-bs-target="#tab_system" type="button" role="tab"><i class="fa fa-cogs"></i> <?php echo $this->lang->line('system') ?></button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab_patient_btn" data-bs-toggle="tab" data-bs-target="#tab_patient" type="button" role="tab"><i class="fa fa-user"></i> <?php echo $this->lang->line('patient') ?></button>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <?php if (!empty($plan_enforcing)) { ?>
                <div class="alert alert-info">
                    Modules outside your subscription cannot be enabled.
                    <a href="<?php echo html_escape(!empty($plan_upgrade_url) ? $plan_upgrade_url : site_url('site/subscription')); ?>">Upgrade plan</a>
                    · <a href="<?php echo site_url('admin/planlimits'); ?>">View plan &amp; limits</a>
                </div>
                <?php } ?>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="tab_system" role="tabpanel">
                        <div class="table-responsive">
                            <div class="download_label"><?php echo $this->lang->line('modules'); ?></div>
                            <table class="table table-striped table-hover align-middle mb-0" cellspacing="0" width="100%">
                                <thead>
                                    <tr>
                                        <th><?php echo $this->lang->line('name'); ?></th>
                                        <th class="text-end rtl-text-start sh-th-140"><?php echo $this->lang->line('action'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if (!empty($permissionList)) {
                                        foreach ($permissionList as $permission) {
                                            $planAllowed = !isset($permission['plan_allowed']) || $permission['plan_allowed'];
                                            ?>
                                            <tr>
                                                <td>
                                                    <?php echo $permission['name']; ?>
                                                    <?php if (!$planAllowed) { ?>
                                                        <span class="badge text-bg-secondary ms-1">Not on plan</span>
                                                    <?php } ?>
                                                </td>
                                                <td class="text-end noExport">
                                                    <div class="form-check form-switch d-inline-flex m-0">
                                                        <input class="form-check-input chk" type="checkbox" role="switch"
                                                               id="student<?php echo $permission['id'] ?>"
                                                               data-role="student"
                                                               data-rowid="<?php echo $permission['short_code'] ?>"
                                                               <?php echo !$planAllowed ? 'disabled title="Upgrade required"' : ''; ?>
                                                               <?php if ($permission['is_active'] == 1) echo 'checked'; ?> />
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php
                                        }
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <!-- /.tab-pane -->
                    <div class="tab-pane fade" id="tab_patient" role="tabpanel">
                        <div class="table-responsive">
                            <div class="download_label"><?php echo $this->lang->line('modules'); ?></div>
                            <table class="table table-striped table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th><?php echo $this->lang->line('name'); ?></th>
                                        <th class="text-end rtl-text-start sh-th-140"><?php echo $this->lang->line('action'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if (!empty($patientPermissionList)) {
                                        foreach ($patientPermissionList as $permission) {
                                            $planAllowed = !isset($permission['plan_allowed']) || $permission['plan_allowed'];
                                            ?>
                                            <tr>
                                                <td>
                                                    <?php echo $permission['name']; ?>
                                                    <?php if (!$planAllowed) { ?>
                                                        <span class="badge text-bg-secondary ms-1">Not on plan</span>
                                                    <?php } ?>
                                                </td>
                                                <td class="text-end">
                                                    <div class="form-check form-switch d-inline-flex m-0">
                                                        <input class="form-check-input chk_patient" type="checkbox" role="switch"
                                                               id="patient<?php echo $permission['id'] ?>"
                                                               data-role="patient"
                                                               data-rowid="<?php echo $permission['id'] ?>"
                                                               <?php echo !$planAllowed ? 'disabled title="Upgrade required"' : ''; ?>
                                                               <?php if ($permission['is_active'] == 1) echo 'checked'; ?> />
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php
                                        }
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <!-- /.tab-pane -->
                </div>
                <!-- /.tab-content -->
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function () {

        $(document).on('click', '.chk', function () {
            var checked = $(this).is(':checked');
            var rowid = $(this).data('rowid');
            var role = $(this).data('role');
            if (checked) {
                if (!confirm('<?php echo $this->lang->line('are_you_sure_to_active_permission') ?>')) {
                    $(this).removeAttr('checked');
                } else {
                    var status = "1";
                    changeStatus(rowid, status, role);
                }
            } else if (!confirm('<?php echo $this->lang->line('are_you_sure_to_deactivate_permission') ?>')) {
                $(this).prop("checked", true);
            } else {
                var status = "0";
                changeStatus(rowid, status, role);
            }
        });

        $(document).on('click', '.chk_patient', function () {
            var checked = $(this).is(':checked');
            var rowid = $(this).data('rowid');
            var role = $(this).data('role');
            if (checked) {
                if (!confirm('<?php echo $this->lang->line('are_you_sure_to_active_permission') ?>')) {
                    $(this).removeAttr('checked');
                } else {
                    var status = "1";
                    changePatientStatus(rowid, status, role);
                }
            } else if (!confirm('<?php echo $this->lang->line('are_you_sure_to_deactivate_permission') ?>')) {
                $(this).prop("checked", true);
            } else {
                var status = "0";
                changePatientStatus(rowid, status, role);
            }
        });
    });

    function changeStatus(rowid, status, role) {
        var base_url = '<?php echo base_url() ?>';
        $.ajax({
            type: "POST",
            url: base_url + "admin/module/changeStatus",
            data: {'short_code': rowid, 'status': status, 'role': role},
            dataType: "json",
            success: function (data) {
                successMsg(data.msg);
                window.location.reload(true);
            }
        });
    }

    function changePatientStatus(rowid, status, role) {
        var base_url = '<?php echo base_url() ?>';
        $.ajax({
            type: "POST",
            url: base_url + "admin/module/changePatientStatus",
            data: {'id': rowid, 'status': status, 'role': role},
            dataType: "json",
            success: function (data) {
                successMsg(data.msg);
                window.location.reload(true);
            }
        });
    }
</script>