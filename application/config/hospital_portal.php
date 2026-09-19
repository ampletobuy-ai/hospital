<?php

defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'helpers/env_helper.php';

/*
|--------------------------------------------------------------------------
| Hospital SaaS portal (in-app register / checkout)
|--------------------------------------------------------------------------
| Plan prices are GST-exclusive. 18% GST is added at checkout.
*/
$config['saas_register'] = hospital_env_bool('HOSPITAL_SAAS_REGISTER', false);

$config['gst_rate'] = (float) hospital_env('PORTAL_BILLING_GST_RATE', '0.18');
$config['billing_currency'] = hospital_env('PORTAL_BILLING_CURRENCY', 'INR');

$config['razorpay'] = array(
    'key_id' => hospital_env('PORTAL_RAZORPAY_KEY_ID', hospital_env('RAZORPAY_KEY_ID', '')),
    'key_secret' => hospital_env('PORTAL_RAZORPAY_KEY_SECRET', hospital_env('RAZORPAY_KEY_SECRET', '')),
    'allow_local_orders' => hospital_env_bool('PORTAL_RAZORPAY_ALLOW_LOCAL_ORDERS', false),
);

$config['otp'] = array(
    'length' => (int) hospital_env('PORTAL_OTP_LENGTH', '6'),
    'ttl_minutes' => (int) hospital_env('PORTAL_OTP_TTL_MINUTES', '10'),
    'max_attempts' => (int) hospital_env('PORTAL_OTP_MAX_ATTEMPTS', '5'),
    'verification_ttl_minutes' => (int) hospital_env('PORTAL_OTP_VERIFICATION_TTL_MINUTES', '30'),
);

$config['trial_days'] = 14;
$config['default_plan_code'] = 'hospital_business';
$config['price_includes_gst'] = false;

$config['hospital_plans'] = array(
    'hospital_starter' => array(
        'name' => 'Starter',
        'product_code' => 'hospital',
        'target' => 'Clinic / Nursing Home',
        'price_includes_gst' => false,
        'annual_price_paise' => 2500000,
        'monthly_price_paise' => 208400,
        'max_users' => 5,
        'max_warehouses' => 1,
        'max_devices' => 0,
        'features' => array(
            'opd' => true,
            'patient_registration' => true,
            'appointment' => true,
            'billing' => true,
            'ipd' => false,
            'bed_ward' => false,
            'pharmacy' => false,
            'laboratory' => false,
            'inventory' => 'basic',
            'tpa_insurance' => false,
            'doctor_commission' => false,
            'reports' => 'basic',
            'whatsapp_sms' => false,
            'multi_branch' => false,
            'api_integration' => false,
            'customization' => 'limited',
            'support' => 'standard',
        ),
        'quota' => array(
            'no_of_patient' => 500,
            'no_of_staff' => 5,
            'storage_mb' => 5120,
        ),
    ),
    'hospital_business' => array(
        'name' => 'Business',
        'product_code' => 'hospital',
        'badge' => 'recommended',
        'target' => '20–100 bed Hospital',
        'price_includes_gst' => false,
        'annual_price_paise' => 7500000,
        'monthly_price_paise' => 625000,
        'max_users' => 15,
        'max_warehouses' => 1,
        'max_devices' => 0,
        'features' => array(
            'opd' => true,
            'patient_registration' => true,
            'appointment' => true,
            'billing' => true,
            'ipd' => true,
            'bed_ward' => true,
            'pharmacy' => true,
            'laboratory' => true,
            'inventory' => 'advanced',
            'tpa_insurance' => true,
            'doctor_commission' => true,
            'reports' => 'advanced',
            'whatsapp_sms' => true,
            'multi_branch' => false,
            'api_integration' => false,
            'customization' => 'standard',
            'support' => 'priority',
        ),
        'quota' => array(
            'no_of_patient' => 5000,
            'no_of_staff' => 15,
            'storage_mb' => 20480,
        ),
    ),
    'hospital_enterprise' => array(
        'name' => 'Enterprise',
        'product_code' => 'hospital',
        'target' => '100+ bed / Multi-branch',
        'price_includes_gst' => false,
        'annual_price_paise' => 15000000,
        'monthly_price_paise' => 1250000,
        'max_users' => 50,
        'max_warehouses' => 5,
        'max_devices' => 0,
        'features' => array(
            'opd' => true,
            'patient_registration' => true,
            'appointment' => true,
            'billing' => true,
            'ipd' => true,
            'bed_ward' => true,
            'pharmacy' => true,
            'laboratory' => true,
            'inventory' => 'advanced',
            'tpa_insurance' => true,
            'doctor_commission' => true,
            'reports' => 'advanced',
            'whatsapp_sms' => true,
            'multi_branch' => true,
            'api_integration' => true,
            'customization' => 'advanced',
            'support' => 'dedicated',
        ),
        'quota' => array(
            'no_of_patient' => 50000,
            'no_of_staff' => 50,
            'storage_mb' => 102400,
        ),
    ),
);
