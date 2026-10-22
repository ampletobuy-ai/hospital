<?php
if (!function_exists('check_whatsapp_in_array')) {
    function check_whatsapp_in_array($find, $array)
    {
        $empty = (object) array(
            'type'      => $find,
            'username'  => '',
            'password'  => '',
            'authkey'   => '',
            'contact'   => '',
            'language'  => 'en',
            'is_active' => 'disabled',
        );
        if (empty($array)) {
            return $empty;
        }
        foreach ($array as $value) {
            if (isset($value->type) && $value->type === $find) {
                return $value;
            }
        }
        return $empty;
    }
}
$twilio_result = check_whatsapp_in_array('twilio', $walist);
$meta_result   = check_whatsapp_in_array('meta', $walist);
$msg91_result  = check_whatsapp_in_array('msg91', $walist);
$messaging_is_central = !empty($messaging_is_central);
$central_messaging = isset($central_messaging) && is_array($central_messaging) ? $central_messaging : array();
?>
<div class="row">
    <?php $this->load->view('setting/sidebar'); ?>
    <div class="col-md-10">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title titlefix"><?php echo $this->lang->line('whatsapp_setting'); ?></h3>
            </div>
            <?php if ($messaging_is_central) { ?>
            <div class="p-3">
                <div class="alert alert-info mb-3">
                    <p class="mb-2"><strong><?php echo $this->lang->line('central_messaging_title'); ?></strong></p>
                    <p class="mb-2"><?php echo $this->lang->line('central_messaging_hint'); ?></p>
                    <p class="mb-0">
                        <?php echo $this->lang->line('sms'); ?>:
                        <?php echo (int) ($central_messaging['sms_used'] ?? 0); ?> / <?php echo (int) ($central_messaging['sms_quota'] ?? 0); ?>
                        &nbsp;·&nbsp;
                        WhatsApp:
                        <?php echo (int) ($central_messaging['whatsapp_used'] ?? 0); ?> / <?php echo (int) ($central_messaging['whatsapp_quota'] ?? 0); ?>
                    </p>
                    <?php if (!empty($central_messaging['whatsapp_needs_pack'])) { ?>
                        <p class="mb-0 mt-2 text-muted"><?php echo $this->lang->line('central_whatsapp_pack_required'); ?></p>
                    <?php } ?>
                </div>
            </div>
            <?php } else { ?>
            <ul class="nav nav-tabs navlistscroll">
                <li class="nav-item"><a class="nav-link active" href="#tab_twilio" data-bs-toggle="tab"><?php echo $this->lang->line('twilio_whatsapp'); ?></a></li>
                <li class="nav-item"><a class="nav-link" href="#tab_meta" data-bs-toggle="tab"><?php echo $this->lang->line('meta_whatsapp'); ?></a></li>
                <li class="nav-item"><a class="nav-link" href="#tab_msg91" data-bs-toggle="tab"><?php echo $this->lang->line('msg91_whatsapp'); ?></a></li>
            </ul>
            <div class="tab-content">
                <div class="tab-pane active" id="tab_twilio">
                    <form role="form" id="twilio_whatsapp_form" action="<?php echo site_url('whatsappconfig/twilio'); ?>" method="post">
                        <div class="p-3">
                            <div class="gw-tab-card">
                                <div class="cred-header">
                                    <div class="gw-logo-box">
                                        <?php if (is_file(FCPATH . 'backend/images/twilio.png')) { ?>
                                            <img src="<?php echo $this->media_storage->getImageURL('backend/images/twilio.png'); ?>" alt="Twilio">
                                        <?php } else { ?>
                                            <strong>twilio</strong>
                                        <?php } ?>
                                    </div>
                                    <div class="gw-brand-info">
                                        <div class="gw-brand-text">
                                            <p class="gw-name"><?php echo $this->lang->line('twilio_whatsapp'); ?></p>
                                            <a class="gw-desc" href="https://www.twilio.com/whatsapp" target="_blank" rel="noopener">https://www.twilio.com/whatsapp</a>
                                        </div>
                                        <?php if (isset($twilio_result->is_active) && $twilio_result->is_active == 'enabled') { ?>
                                            <span class="gw-status"><?php echo $this->lang->line('active'); ?></span>
                                        <?php } ?>
                                    </div>
                                </div>
                                <div class="cred-body">
                                    <div class="cred-field cred-field-top">
                                        <label><?php echo $this->lang->line('twilio_account_sid'); ?> <span class="req">*</span></label>
                                        <div class="inp-wrap">
                                            <input type="text" class="inp" name="twilio_account_sid" value="<?php echo html_escape($twilio_result->username); ?>">
                                            <span class="text text-danger twilio_account_sid_error"></span>
                                        </div>
                                    </div>
                                    <div class="cred-field cred-field-top">
                                        <label><?php echo $this->lang->line('authentication_token'); ?> <span class="req">*</span></label>
                                        <div class="inp-wrap">
                                            <input type="password" class="inp" name="twilio_auth_token" value="">
                                            <?php if (!empty($twilio_result->password)) { ?>
                                                <span class="saved-hint"><i class="fa fa-lock"></i> <?php echo mask_sensitive($twilio_result->password); ?></span>
                                            <?php } ?>
                                            <span class="text text-danger twilio_auth_token_error"></span>
                                        </div>
                                    </div>
                                    <div class="cred-field cred-field-top">
                                        <label><?php echo $this->lang->line('registered_phone_number'); ?> <span class="req">*</span></label>
                                        <div class="inp-wrap">
                                            <input type="text" class="inp" name="twilio_sender_phone_number" value="<?php echo html_escape($twilio_result->contact); ?>" placeholder="+14155238886">
                                            <span class="text text-danger twilio_sender_phone_number_error"></span>
                                        </div>
                                    </div>
                                    <div class="cred-field">
                                        <label><?php echo $this->lang->line('status'); ?></label>
                                        <div class="inp-wrap">
                                            <select class="inp" name="twilio_status">
                                                <?php foreach ($statuslist as $s_key => $s_value) { ?>
                                                    <option value="<?php echo $s_key; ?>" <?php if ($twilio_result->is_active == $s_key) { echo 'selected=selected'; } ?>><?php echo $s_value; ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <?php if ($this->rbac->hasPrivilege('whatsapp_setting', 'can_edit')) { ?>
                                <div class="cred-footer">
                                    <span class="twilio_whatsapp_loader"></span>
                                    <button type="submit" class="btn btn-info btn-sm"><i class="fa fa-check-circle"></i> <?php echo $this->lang->line('save'); ?></button>
                                </div>
                                <?php } ?>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="tab-pane" id="tab_meta">
                    <form role="form" id="meta_whatsapp_form" action="<?php echo site_url('whatsappconfig/meta'); ?>" method="post">
                        <div class="p-3">
                            <div class="gw-tab-card">
                                <div class="cred-header">
                                    <div class="gw-logo-box"><strong>Meta</strong></div>
                                    <div class="gw-brand-info">
                                        <div class="gw-brand-text">
                                            <p class="gw-name"><?php echo $this->lang->line('meta_whatsapp'); ?></p>
                                            <a class="gw-desc" href="https://developers.facebook.com/docs/whatsapp" target="_blank" rel="noopener">https://developers.facebook.com/docs/whatsapp</a>
                                        </div>
                                        <?php if (isset($meta_result->is_active) && $meta_result->is_active == 'enabled') { ?>
                                            <span class="gw-status"><?php echo $this->lang->line('active'); ?></span>
                                        <?php } ?>
                                    </div>
                                </div>
                                <div class="cred-body">
                                    <div class="cred-field cred-field-top">
                                        <label><?php echo $this->lang->line('access_token'); ?> <span class="req">*</span></label>
                                        <div class="inp-wrap">
                                            <input type="password" class="inp" name="meta_access_token" value="">
                                            <?php if (!empty($meta_result->authkey)) { ?>
                                                <span class="saved-hint"><i class="fa fa-lock"></i> <?php echo mask_sensitive($meta_result->authkey); ?></span>
                                            <?php } ?>
                                            <span class="text text-danger meta_access_token_error"></span>
                                        </div>
                                    </div>
                                    <div class="cred-field cred-field-top">
                                        <label><?php echo $this->lang->line('phone_number_id'); ?> <span class="req">*</span></label>
                                        <div class="inp-wrap">
                                            <input type="text" class="inp" name="meta_phone_number_id" value="<?php echo html_escape($meta_result->contact); ?>">
                                            <span class="text text-danger meta_phone_number_id_error"></span>
                                        </div>
                                    </div>
                                    <div class="cred-field cred-field-top">
                                        <label><?php echo $this->lang->line('language'); ?></label>
                                        <div class="inp-wrap">
                                            <input type="text" class="inp" name="meta_language" value="<?php echo html_escape($meta_result->language ? $meta_result->language : 'en'); ?>" placeholder="en">
                                            <span class="text text-danger meta_language_error"></span>
                                        </div>
                                    </div>
                                    <div class="cred-field">
                                        <label><?php echo $this->lang->line('status'); ?></label>
                                        <div class="inp-wrap">
                                            <select class="inp" name="meta_status">
                                                <?php foreach ($statuslist as $s_key => $s_value) { ?>
                                                    <option value="<?php echo $s_key; ?>" <?php if ($meta_result->is_active == $s_key) { echo 'selected=selected'; } ?>><?php echo $s_value; ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <?php if ($this->rbac->hasPrivilege('whatsapp_setting', 'can_edit')) { ?>
                                <div class="cred-footer">
                                    <span class="meta_whatsapp_loader"></span>
                                    <button type="submit" class="btn btn-info btn-sm"><i class="fa fa-check-circle"></i> <?php echo $this->lang->line('save'); ?></button>
                                </div>
                                <?php } ?>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="tab-pane" id="tab_msg91">
                    <form role="form" id="msg91_whatsapp_form" action="<?php echo site_url('whatsappconfig/msg91'); ?>" method="post">
                        <div class="p-3">
                            <div class="gw-tab-card">
                                <div class="cred-header">
                                    <div class="gw-logo-box">
                                        <?php if (is_file(FCPATH . 'backend/images/msg91.png')) { ?>
                                            <img src="<?php echo $this->media_storage->getImageURL('backend/images/msg91.png'); ?>" alt="MSG91">
                                        <?php } else { ?>
                                            <strong>MSG91</strong>
                                        <?php } ?>
                                    </div>
                                    <div class="gw-brand-info">
                                        <div class="gw-brand-text">
                                            <p class="gw-name"><?php echo $this->lang->line('msg91_whatsapp'); ?></p>
                                            <a class="gw-desc" href="https://msg91.com/help/whatsapp/send-whatsapp" target="_blank" rel="noopener">https://msg91.com</a>
                                        </div>
                                        <?php if (isset($msg91_result->is_active) && $msg91_result->is_active == 'enabled') { ?>
                                            <span class="gw-status"><?php echo $this->lang->line('active'); ?></span>
                                        <?php } ?>
                                    </div>
                                </div>
                                <div class="cred-body">
                                    <div class="cred-field cred-field-top">
                                        <label><?php echo $this->lang->line('auth_key'); ?> <span class="req">*</span></label>
                                        <div class="inp-wrap">
                                            <input type="password" class="inp" name="msg91_authkey" value="">
                                            <?php if (!empty($msg91_result->authkey)) { ?>
                                                <span class="saved-hint"><i class="fa fa-lock"></i> <?php echo mask_sensitive($msg91_result->authkey); ?></span>
                                            <?php } ?>
                                            <span class="text text-danger msg91_authkey_error"></span>
                                        </div>
                                    </div>
                                    <div class="cred-field cred-field-top">
                                        <label><?php echo $this->lang->line('integrated_number'); ?> <span class="req">*</span></label>
                                        <div class="inp-wrap">
                                            <input type="text" class="inp" name="msg91_integrated_number" value="<?php echo html_escape($msg91_result->contact); ?>" placeholder="9198XXXXXXXX">
                                            <span class="text text-danger msg91_integrated_number_error"></span>
                                        </div>
                                    </div>
                                    <div class="cred-field cred-field-top">
                                        <label><?php echo $this->lang->line('whatsapp_namespace'); ?></label>
                                        <div class="inp-wrap">
                                            <input type="text" class="inp" name="msg91_namespace" value="<?php echo html_escape($msg91_result->username); ?>">
                                            <span class="text text-danger msg91_namespace_error"></span>
                                        </div>
                                    </div>
                                    <div class="cred-field cred-field-top">
                                        <label><?php echo $this->lang->line('language'); ?></label>
                                        <div class="inp-wrap">
                                            <input type="text" class="inp" name="msg91_language" value="<?php echo html_escape($msg91_result->language ? $msg91_result->language : 'en'); ?>" placeholder="en">
                                            <span class="text text-danger msg91_language_error"></span>
                                        </div>
                                    </div>
                                    <div class="cred-field">
                                        <label><?php echo $this->lang->line('status'); ?></label>
                                        <div class="inp-wrap">
                                            <select class="inp" name="msg91_status">
                                                <?php foreach ($statuslist as $s_key => $s_value) { ?>
                                                    <option value="<?php echo $s_key; ?>" <?php if ($msg91_result->is_active == $s_key) { echo 'selected=selected'; } ?>><?php echo $s_value; ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <?php if ($this->rbac->hasPrivilege('whatsapp_setting', 'can_edit')) { ?>
                                <div class="cred-footer">
                                    <span class="msg91_whatsapp_loader"></span>
                                    <button type="submit" class="btn btn-info btn-sm"><i class="fa fa-check-circle"></i> <?php echo $this->lang->line('save'); ?></button>
                                </div>
                                <?php } ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>
</div>
<?php if (empty($messaging_is_central)) { ?>
<script type="text/javascript">
(function ($) {
    var img_path = '<?php echo base_url('backend/images/loading.gif'); ?>';

    function bindWhatsappForm(formId, loaderClass) {
        $(formId).on('submit', function (e) {
            e.preventDefault();
            $("[class$='_error']").html('');
            $('.' + loaderClass).html('<img src="' + img_path + '">');
            var $form = $(this);
            $.ajax({
                type: 'POST',
                dataType: 'JSON',
                url: $form.attr('action'),
                data: $form.serialize(),
                success: function (data) {
                    if (data.st === 1) {
                        $.each(data.msg, function (key, value) {
                            $('.' + key + '_error').html(value);
                        });
                    } else {
                        successMsg(data.msg);
                        setTimeout(function () { window.location.reload(); }, 800);
                    }
                    $('.' + loaderClass).html('');
                },
                error: function () {
                    $('.' + loaderClass).html('');
                }
            });
        });
    }

    bindWhatsappForm('#twilio_whatsapp_form', 'twilio_whatsapp_loader');
    bindWhatsappForm('#meta_whatsapp_form', 'meta_whatsapp_loader');
    bindWhatsappForm('#msg91_whatsapp_form', 'msg91_whatsapp_loader');
}(jQuery));
</script>
<?php } ?>
