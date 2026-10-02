<div id="addonModal" class="modal fade">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= $this->lang->line('register_your_addon') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?php echo site_url('admin/admin/updateaddon') ?>" method="POST" id="addon_verify">
                <div class="modal-body addon_modal-body">
                    <div class="error_message">

                    </div>
                    <input type="hidden" name="addon" class="addon_type" value="">
                    <input type="hidden" name="addon_version" class="addon_version" value="0">
                    <div class="form-group">
                        <label class="ainline"><span>Qubex Purchase Code for Addon</span></label>
                        <input type="text" class="form-control" id="input-app-qubex_purchase_code" name="app-qubex_purchase_code">
                        <div id="error" class="input-error text text-danger"></div>
                    </div>

                    <div class="form-group">
                        <label for="exampleInputEmail1"><?= $this->lang->line('your_email_registered_with_qubex') ?></label>
                        <input type="text" class="form-control" id="input-app-email" name="app-email">
                        <div id="error" class="input-error text text-danger"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-info" data-loading-text="<i class='fa fa-spinner fa-spin '></i> Saving...">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>