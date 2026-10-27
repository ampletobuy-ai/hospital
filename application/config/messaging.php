<?php

defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'helpers/env_helper.php';
if (function_exists('hospital_env_boot')) {
    hospital_env_boot(dirname(APPPATH));
}

/*
|--------------------------------------------------------------------------
| Messaging account
|--------------------------------------------------------------------------
|
| central: SMS/WhatsApp use platform MSG91, central message_templates, usage meter.
| tenant: each hospital keeps Twilio / Meta / MSG91 rows in whatsapp_config / sms_config.
|
*/
$config['messaging_account'] = hospital_env('MESSAGING_ACCOUNT', 'central');
$config['messaging_product'] = hospital_env('MESSAGING_PRODUCT', 'hospital');
$config['messaging_fallback_tenant_id'] = hospital_env('MESSAGING_FALLBACK_TENANT_ID', 'platform');

$config['messaging_msg91'] = array(
    'auth_key' => hospital_env('MESSAGING_MSG91_AUTH_KEY', ''),
    'whatsapp_auth_key' => hospital_env('MESSAGING_MSG91_WHATSAPP_AUTH_KEY', ''),
    'sender' => hospital_env('MESSAGING_MSG91_SENDER', 'TRACKP'),
    'route' => hospital_env('MESSAGING_MSG91_ROUTE', '4'),
    'country' => hospital_env('MESSAGING_MSG91_COUNTRY', '91'),
    'whatsapp_number' => hospital_env('MESSAGING_MSG91_WHATSAPP_NUMBER', ''),
    'language' => hospital_env('MESSAGING_MSG91_LANGUAGE', 'en'),
);

$config['messaging_quota'] = array(
    'sms' => (int) hospital_env('MESSAGING_SMS_MONTHLY_QUOTA', 500),
    'whatsapp' => (int) hospital_env('MESSAGING_WHATSAPP_MONTHLY_QUOTA', 50),
);

$config['messaging_whatsapp_pack_code'] = 'whatsapp_message_pack';
$config['messaging_whatsapp_messages_per_pack'] = (int) hospital_env('MESSAGING_WHATSAPP_MESSAGES_PER_PACK', 500);

/*
|--------------------------------------------------------------------------
| WhatsApp IMAGE header URLs (MSG91 header_1)
|--------------------------------------------------------------------------
|
| Only templates whose MSG91 copy has an IMAGE header should be listed.
| Absolute https URLs win; relative paths resolve against HOSPITAL_BASE_URL /
| site base_url (web-accessible files under the hospital app).
|
*/
$config['messaging_whatsapp_header_images'] = array(
    'opd_patient_registration' => hospital_env(
        'MESSAGING_WHATSAPP_OPD_REGISTRATION_HEADER_URL',
        'backend/images/whatsapp-headers/OpdRegistration.png'
    ),
);
