<?php

defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'helpers/env_helper.php';

/*
|--------------------------------------------------------------------------
| Hospital multi-tenant (shared central DB with retail-pos)
|--------------------------------------------------------------------------
*/
$config['tenancy_enabled'] = hospital_env_bool('TENANCY_ENABLED', false);
$config['multi_tenant_login'] = hospital_env_bool('MULTI_TENANT_LOGIN', true);
$config['product_code'] = hospital_env('TENANCY_PRODUCT_CODE', 'hospital');
$config['tenant_database_prefix'] = hospital_env('TENANT_DATABASE_PREFIX', 'hospital_');
$config['template_database'] = hospital_env('TENANT_TEMPLATE_DATABASE', hospital_env('DB_DATABASE', 'hospital'));
$config['portal_register_url'] = hospital_env('PORTAL_REGISTER_URL', 'http://localhost/retail-pos/public/portal/register?product=hospital');
$config['portal_subscription_url'] = hospital_env('PORTAL_SUBSCRIPTION_URL', 'http://localhost/retail-pos/public/portal/subscription');
$config['portal_signup_crypt_key'] = hospital_env('PORTAL_SIGNUP_CRYPT_KEY', '');
$config['hospital_saas_register'] = hospital_env_bool('HOSPITAL_SAAS_REGISTER', false);

/*
| Tables skipped entirely during schema clone (rare).
*/
$config['skip_template_tables'] = array();

/*
| Data copy mode: "blacklist" (recommended) or "whitelist".
| - blacklist: copy every template table's rows except skip_template_data_tables
| - whitelist: copy only copy_template_data_tables (legacy)
*/
$config['template_data_copy_mode'] = hospital_env('TENANT_TEMPLATE_DATA_COPY_MODE', 'blacklist');

/*
| Transactional / PII / secrets — schema is created empty; data is NOT copied.
| Master catalogs (radiology_parameter, pathology_*, charges, medicine_*, etc.)
| are copied automatically when mode=blacklist.
*/
$config['skip_template_data_tables'] = array(
    // Identity / bootstrap recreates these
    'staff',
    'staff_roles',
    'staff_leave_details',
    'users',
    'patients',

    // Visits / clinical / billing
    'ambulance_call',
    'antenatal_examine',
    'appointment',
    'appointment_payment',
    'appointment_queue',
    'bill',
    'birth_report',
    'blood_donor',
    'blood_donor_cycle',
    'blood_issue',
    'case_references',
    'complaint',
    'consultant_register',
    'consult_charges',
    'death_report',
    'discharge_card',
    'dispatch_receive',
    'doctor_absent',
    'doctor_global_shift',
    'doctor_shift_time',
    'general_calls',
    'ipd_details',
    'ipd_doctors',
    'ipd_icd10_codes',
    'ipd_prescription_basic',
    'ipd_prescription_details',
    'ipd_prescription_test',
    'medication_report',
    'nurse_note',
    'nurse_notes_comment',
    'obstetric_history',
    'opd_details',
    'opd_icd10_codes',
    'operation_theatre',
    'organisations_charges',
    'organisations_medicine_charges',
    'pathology_billing',
    'pathology_report',
    'pathology_report_parameterdetails',
    'patient_bed_history',
    'patient_charges',
    'patient_timeline',
    'patients_vitals',
    'pharmacy',
    'pharmacy_bill_basic',
    'pharmacy_bill_detail',
    'pharmacy_return_basic',
    'pharmacy_return_detail',
    'postnatal_examine',
    'primary_examine',
    'radiology_billing',
    'radiology_report',
    'radiology_report_parameterdetails',
    'shift_details',
    'transactions',
    'transactions_processing',
    'visit_details',
    'visitors_book',

    // Inventory / stock movements
    'item',
    'item_issue',
    'item_stock',
    'medicine_bad_stock',
    'medicine_batch_details',
    'purchase_return_basic',
    'purchase_return_detail',
    'supplier_bill_basic',

    // HR / payroll / roster
    'duty_roster_assign',
    'duty_roster_list',
    'duty_roster_shift',
    'payslip_allowance',
    'staff_attendance',
    'staff_attendence_schedules',
    'staff_leave_request',
    'staff_payroll',
    'staff_payslip',
    'staff_timeline',

    // Comms / chat / queues / auth sessions
    'captcha',
    'chat_connections',
    'chat_messages',
    'chat_users',
    'failed_message_queue',
    'logs',
    'message_queue',
    'messages',
    'notification_roles',
    'read_notification',
    'read_systemnotification',
    'send_notification',
    'system_notification',
    'userlog',
    'users_authentication',
    'user_theme_preferences',

    // CMS uploads / instance content
    'contents',
    'content_for',
    'custom_field_values',
    'front_cms_media_gallery',
    'front_cms_page_contents',
    'front_cms_program_photos',
    'front_cms_programs',
    'share_contents',
    'share_upload_contents',
    'upload_contents',

    // Finance / events / referrals / conferences
    'annual_calendar',
    'conferences',
    'conferences_history',
    'conference_staff',
    'events',
    'expenses',
    'gateway_ins',
    'gateway_ins_response',
    'income',
    'referral_commission',
    'referral_payment',
    'referral_person',
    'referral_person_commission',

    // Secrets / host-specific / infra
    'email_config',
    'zoom_settings',
    'sms_config',
    'addon_versions',
    'migrations',
);

/*
| Legacy whitelist (used only when template_data_copy_mode = whitelist).
*/
$config['copy_template_data_tables'] = array(
    'sch_settings',
    'front_cms_settings',
    'roles',
    'permission_category',
    'permission_group',
    'roles_permissions',
    'languages',
    'notification_setting',
    'system_notification_setting',
    'staff_id_card',
    'patient_id_card',
    'print_setting',
    'payment_settings',
    'medicine_category',
    'medicine_dosage',
    'medicine_group',
    'medicine_supplier',
    'pharmacy_company',
    'dose_duration',
    'dose_interval',
    'unit',
    'lab',
    'radio',
    'radiology_parameter',
    'radiology_parameterdetails',
    'pathology',
    'pathology_category',
    'pathology_parameter',
    'pathology_parameterdetails',
    'charge_categories',
    'charge_type_master',
    'charge_type_module',
    'charge_units',
    'charges',
    'tax_category',
    'blood_bank_products',
    'appoint_priority',
    'operation',
    'operation_category',
    'organisation',
    'vehicles',
    'leave_types',
    'staff_attendance_type',
    'staff_designation',
    'department',
    'bed',
    'bed_type',
    'bed_group',
    'floor',
    'symptoms',
    'symptoms_classification',
    'finding',
    'finding_category',
    'vitals',
    'complaint_type',
    'visitors_purpose',
    'source',
    'expense_head',
    'income_head',
    'referral_category',
    'referral_type',
    'specialist',
    'global_shift',
    'permission_patient',
    'prefixes',
    'certificates',
    'front_cms_menus',
    'front_cms_menu_items',
    'front_cms_pages',
    'hospital_theme_settings',
    'addons',
    'share_content_for',
    'content_types',
    'custom_fields',
);
