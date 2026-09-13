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

$config['skip_template_tables'] = array();

$config['copy_template_data_tables'] = array(
    'roles',
    'permission_category',
    'permission_group',
    'permission',
    'roles_permissions',
    'languages',
    'currencies',
    'notification_setting',
    'system_notification_setting',
    'id_card',
    'staff_id_card',
    'patient_id_card',
    'print_setting',
    'payment_settings',
    'medicine_category',
    'medicine_supplier',
    'lab',
    'charge_categories',
    'charge_type_master',
    'operation_category',
    'organisation',
    'vehicles',
    'leave_types',
    'staff_attendance_type',
    'staff_designation',
    'staff_department',
    'bed_type',
    'bed_group',
    'floor',
    'symptoms',
    'symptoms_classification',
    'finding',
    'finding_category',
    'vital',
    'content_type',
    'custom_field',
    'front_cms_setting',
    'share_content_for',
    'share_content_type',
    'share_category',
    'share_content_for_widget',
);
