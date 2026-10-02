<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Runtime plan feature gate for SaaS tenants.
 *
 * Effective features come from tenant_subscriptions.metadata.features when present,
 * otherwise from hospital_plans[plan_code].features. Non-tenant / no subscription = allow all.
 */
class Plan_feature_gate
{
    /** @var CI_Controller */
    public $CI;

    /** @var array<string, mixed>|null null = not loaded yet */
    private $features = null;

    /** @var bool */
    private $enforce = false;

    /** Module short_code => plan feature key (or special token). */
    private $moduleFeatureMap = array(
        'opd' => 'opd',
        'patient' => 'patient_registration',
        'appointment' => 'appointment',
        'bill' => 'billing',
        'ipd' => 'ipd',
        'pharmacy' => 'pharmacy',
        'pathology' => 'laboratory',
        'radiology' => 'laboratory',
        'blood_bank' => '__requires_ipd__',
        'ambulance' => '__requires_ipd__',
        'live_consultation' => '__requires_ipd__',
        'inventory' => 'inventory',
        'tpa_management' => 'tpa_insurance',
        'referral' => 'doctor_commission',
        'whatsapp_messaging' => 'whatsapp_sms',
        'communicate' => 'whatsapp_sms',
    );

    /** Exact permission_category.short_code => plan feature key. */
    private $permissionExactMap = array(
        'opd_move_patient_in_ipd' => 'ipd',
        'organisation' => 'tpa_insurance',
        'tpa_charges' => 'tpa_insurance',
        'tpa_report' => 'tpa_insurance',
        'bed' => 'ipd',
        'bed_status' => 'ipd',
        'bed_history' => 'ipd',
        'bed_type' => 'ipd',
        'bed_group' => 'ipd',
        'floor' => 'ipd',
        'discharged_patients' => 'ipd',
        'consultant_register' => 'ipd',
        'nurse_note' => 'ipd',
        'medicine' => 'pharmacy',
        'medicine_category' => 'pharmacy',
        'medicine_bad_stock' => 'pharmacy',
        'import_medicine' => 'pharmacy',
        'medicine_purchase' => 'pharmacy',
        'medicine_supplier' => 'pharmacy',
        'medicine_dosage' => 'pharmacy',
        'dosage_interval' => 'pharmacy',
        'dosage_duration' => 'pharmacy',
        'medicine_unit' => 'pharmacy',
        'medicine_company' => 'pharmacy',
        'medicine_group' => 'pharmacy',
        'expiry_medicine_report' => 'pharmacy',
        'medicine_purchase_report' => 'pharmacy',
        'medicine_purchase_return_report' => 'pharmacy',
        'stock_report' => 'pharmacy',
        'sms_setting' => 'whatsapp_sms',
        'email_sms' => 'whatsapp_sms',
        'email_sms_log' => 'whatsapp_sms',
        'send_credential' => 'whatsapp_sms',
        'api_keys' => 'api_integration',
        'webhooks' => 'api_integration',
    );

    /** Permission short_code prefix => plan feature key. */
    private $permissionPrefixMap = array(
        'ipd_' => 'ipd',
        'ipd' => 'ipd',
        'pharmacy_' => 'pharmacy',
        'pathology_' => 'laboratory',
        'radiology_' => 'laboratory',
        'referral_' => 'doctor_commission',
        'blood_' => '__requires_ipd__',
        'bloodbank_' => '__requires_ipd__',
        'ambulance' => '__requires_ipd__',
        'live_consult' => '__requires_ipd__',
        'live_meeting' => '__requires_ipd__',
        'tpa_' => 'tpa_insurance',
    );

    /** Basic (Starter) report allowlist — PLAN_ROLE_PERMISSION_MATRIX §6. */
    private $basicReportCodes = array(
        'opd_report',
        'opd_balance_report',
        'appointment_report',
        'patient_visit_report',
        'patient_bill_report',
        'daily_transaction_report',
        'income_report',
        'expense_report',
        'staff_attendance_report',
    );

    /** Report-like short_codes that do not end with _report. */
    private $extraReportCodes = array(
        'user_log',
        'email_sms_log',
    );

    /** Inventory permissions that require advanced inventory. */
    private $advancedInventoryCodes = array(
        'store',
        'supplier',
        'inventory_stock_report',
        'add_item_report',
        'issue_inventory_report',
        'stock_report',
    );

    /** Customization short_code => minimum tier (limited|standard|advanced). */
    private $customizationMinTier = array(
        'general_setting' => 'limited',
        'prefix_setting' => 'limited',
        'notification_setting' => 'limited',
        'payment_methods' => 'limited',
        'languages' => 'limited',
        'language_switcher' => 'limited',
        'email_setting' => 'limited',
        'users' => 'limited',
        'captcha_setting' => 'limited',
        'custom_fields' => 'standard',
        'icd10_groups' => 'standard',
        'icd10_codes' => 'standard',
        'front_cms_setting' => 'standard',
        'backup' => 'standard',
        'restore' => 'standard',
        'theme_studio' => 'advanced',
    );

    /** Master data prefixes: view-only on limited customization. */
    private $viewOnlyMasterPrefixes = array(
        'symptoms_',
        'symptoms',
        'finding',
        'vital',
        'operation',
    );

    /** role_id => required plan feature. */
    private $roleFeatureMap = array(
        4 => 'pharmacy',
        5 => 'laboratory',
        6 => 'laboratory',
    );

    /** Tier ranks for canAtLeast. */
    private $tierRanks = array(
        'inventory' => array('basic' => 1, 'advanced' => 2),
        'reports' => array('basic' => 1, 'advanced' => 2),
        'customization' => array('limited' => 1, 'standard' => 2, 'advanced' => 3),
    );

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->config('tenancy-config');
        $this->CI->load->config('hospital_portal');
        $this->CI->load->library('subscription_resolver');
        $this->bootstrap();
    }

    private function bootstrap()
    {
        if (!$this->CI->config->item('tenancy_enabled')) {
            $this->enforce = false;
            $this->features = array();
            return;
        }

        $tenantId = (string) $this->CI->session->userdata('tenant_id');
        if ($tenantId === '') {
            $this->enforce = false;
            $this->features = array();
            return;
        }

        $subscription = $this->CI->subscription_resolver->latestForTenant($tenantId);
        if (!$subscription) {
            // Legacy tenant without subscription row: do not lock features.
            $this->enforce = false;
            $this->features = array();
            return;
        }

        $this->enforce = true;
        $metadata = $subscription['metadata'] ?? array();
        if (!is_array($metadata)) {
            $metadata = array();
        }

        $planCode = (string) ($subscription['plan_code'] ?? '');
        $plans = (array) $this->CI->config->item('hospital_plans');
        $defaults = array();
        if ($planCode !== '' && isset($plans[$planCode]['features']) && is_array($plans[$planCode]['features'])) {
            $defaults = $plans[$planCode]['features'];
        }

        $stored = isset($metadata['features']) && is_array($metadata['features'])
            ? $metadata['features']
            : array();

        $this->features = array_merge($defaults, $stored);
    }

    /**
     * @param string $featureKey
     * @return mixed|null
     */
    public function featureLevel($featureKey)
    {
        $featureKey = trim((string) $featureKey);
        if ($featureKey === '' || !is_array($this->features) || !array_key_exists($featureKey, $this->features)) {
            return null;
        }

        return $this->features[$featureKey];
    }

    /**
     * @param string $featureKey
     * @param string $minLevel
     * @return bool
     */
    public function canAtLeast($featureKey, $minLevel)
    {
        $featureKey = trim((string) $featureKey);
        $minLevel = strtolower(trim((string) $minLevel));

        if ($featureKey === '' || $minLevel === '') {
            return true;
        }

        if (!$this->enforce) {
            return true;
        }

        if (!$this->can($featureKey)) {
            return false;
        }

        if (!isset($this->tierRanks[$featureKey])) {
            return true;
        }

        $ranks = $this->tierRanks[$featureKey];
        if (!isset($ranks[$minLevel])) {
            return true;
        }

        $current = $this->featureLevel($featureKey);
        if ($current === true || $current === 1 || $current === '1') {
            // Boolean true: treat as highest tier for that feature.
            $currentRank = max($ranks);
        } else {
            $currentKey = strtolower(trim((string) $current));
            if (!isset($ranks[$currentKey])) {
                return true;
            }
            $currentRank = $ranks[$currentKey];
        }

        return $currentRank >= $ranks[$minLevel];
    }

    /**
     * @param string $featureKey
     * @return bool
     */
    public function can($featureKey)
    {
        $featureKey = trim((string) $featureKey);
        if ($featureKey === '') {
            return true;
        }

        if ($featureKey === '__requires_ipd__') {
            return $this->can('ipd');
        }

        if (!$this->enforce) {
            return true;
        }

        if (!array_key_exists($featureKey, $this->features)) {
            return true;
        }

        $value = $this->features[$featureKey];
        if ($value === false || $value === null || $value === 0 || $value === '0') {
            return false;
        }

        return true;
    }

    /**
     * @param string|null $moduleShortCode
     * @return bool
     */
    public function moduleAllowed($moduleShortCode)
    {
        $moduleShortCode = trim((string) $moduleShortCode);
        if ($moduleShortCode === '') {
            return true;
        }

        if (!isset($this->moduleFeatureMap[$moduleShortCode])) {
            return true;
        }

        return $this->can($this->moduleFeatureMap[$moduleShortCode]);
    }

    /**
     * Whether a built-in role_id may be assigned on the current plan.
     *
     * @param int|string $roleId
     * @return bool
     */
    public function roleAllowed($roleId)
    {
        $roleId = (int) $roleId;
        if (!$this->enforce) {
            return true;
        }

        if (!isset($this->roleFeatureMap[$roleId])) {
            return true;
        }

        return $this->can($this->roleFeatureMap[$roleId]);
    }

    /**
     * @param string|null $permissionCategoryCode
     * @param string|null $action can_view|can_add|can_edit|can_delete
     * @return bool
     */
    public function permissionAllowed($permissionCategoryCode, $action = null)
    {
        $code = trim((string) $permissionCategoryCode);
        if ($code === '') {
            return true;
        }

        if (isset($this->permissionExactMap[$code])) {
            // stock_report is pharmacy-mapped; also require advanced inventory when that tier applies.
            if ($code === 'stock_report' && !$this->inventoryPermissionAllowed($code)) {
                return false;
            }
            return $this->can($this->permissionExactMap[$code]);
        }

        foreach ($this->permissionPrefixMap as $prefix => $featureKey) {
            if (strpos($code, $prefix) === 0) {
                $allowed = $this->can($featureKey);
                if (!$allowed) {
                    return false;
                }
                // Module-linked reports still subject to report tier when applicable.
                if ($this->isReportPermission($code) && !$this->reportAllowed($code)) {
                    return false;
                }
                return true;
            }
        }

        // Billing permission categories tied to gated modules.
        $billingMap = array(
            'ipd_billing' => 'ipd',
            'ipd_billing_payment' => 'ipd',
            'generate_discharge_card' => 'ipd',
            'pharmacy_billing' => 'pharmacy',
            'pharmacy_billing_payment' => 'pharmacy',
            'pathology_billing' => 'laboratory',
            'pathology_billing_payment' => 'laboratory',
            'radiology_billing' => 'laboratory',
            'radiology_billing_payment' => 'laboratory',
            'blood_bank_billing' => '__requires_ipd__',
            'blood_bank_billing_payment' => '__requires_ipd__',
            'ambulance_billing' => '__requires_ipd__',
            'ambulance_billing_payment' => '__requires_ipd__',
        );
        if (isset($billingMap[$code])) {
            return $this->can($billingMap[$code]);
        }

        // Reports that belong to gated modules.
        $reportMap = array(
            'ipd_report' => 'ipd',
            'ipd_balance_report' => 'ipd',
            'discharge_patient_report' => 'ipd',
            'ot_report' => 'ipd',
            'pharmacy_bill_report' => 'pharmacy',
            'pathology_patient_report' => 'laboratory',
            'pathology_balance_report' => 'laboratory',
            'radiology_patient_report' => 'laboratory',
            'radiology_balance_report' => 'laboratory',
            'blood_donor_report' => '__requires_ipd__',
            'blood_issue_report' => '__requires_ipd__',
            'component_issue_report' => '__requires_ipd__',
            'ambulance_report' => '__requires_ipd__',
            'live_consultation_report' => '__requires_ipd__',
            'live_meeting_report' => '__requires_ipd__',
            'tpa_report' => 'tpa_insurance',
            'referral_report' => 'doctor_commission',
        );
        if (isset($reportMap[$code])) {
            if (!$this->can($reportMap[$code])) {
                return false;
            }
            return $this->reportAllowed($code);
        }

        if (!$this->inventoryPermissionAllowed($code)) {
            return false;
        }

        if ($this->isReportPermission($code) && !$this->reportAllowed($code)) {
            return false;
        }

        if (!$this->customizationPermissionAllowed($code, $action)) {
            return false;
        }

        return true;
    }

    /**
     * @param string $shortCode
     * @return bool
     */
    public function reportAllowed($shortCode)
    {
        $shortCode = trim((string) $shortCode);
        if ($shortCode === '' || !$this->enforce) {
            return true;
        }

        if ($this->canAtLeast('reports', 'advanced')) {
            return true;
        }

        // Basic reports tier: allowlist only.
        return in_array($shortCode, $this->basicReportCodes, true);
    }

    /**
     * @param string $code
     * @return bool
     */
    private function isReportPermission($code)
    {
        if (in_array($code, $this->extraReportCodes, true)) {
            return true;
        }

        if (in_array($code, $this->basicReportCodes, true)) {
            return true;
        }

        return substr($code, -7) === '_report';
    }

    /**
     * @param string $code
     * @return bool
     */
    private function inventoryPermissionAllowed($code)
    {
        if (!in_array($code, $this->advancedInventoryCodes, true)) {
            return true;
        }

        if (!$this->enforce) {
            return true;
        }

        // Module must be on, and tier must be advanced for store/supplier/advanced reports.
        if (!$this->can('inventory')) {
            return false;
        }

        return $this->canAtLeast('inventory', 'advanced');
    }

    /**
     * @param string $code
     * @param string|null $action
     * @return bool
     */
    private function customizationPermissionAllowed($code, $action = null)
    {
        if (!$this->enforce) {
            return true;
        }

        if (isset($this->customizationMinTier[$code])) {
            return $this->canAtLeast('customization', $this->customizationMinTier[$code]);
        }

        if ($this->isViewOnlyMasterPermission($code)) {
            if ($this->canAtLeast('customization', 'standard')) {
                return true;
            }
            // Limited: view only.
            $action = strtolower(trim((string) $action));
            if ($action === '' || $action === 'can_view') {
                return true;
            }
            return false;
        }

        return true;
    }

    /**
     * @param string $code
     * @return bool
     */
    private function isViewOnlyMasterPermission($code)
    {
        foreach ($this->viewOnlyMasterPrefixes as $prefix) {
            if ($code === $prefix || strpos($code, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $featureKey
     * @return void
     */
    public function denyUnless($featureKey)
    {
        if ($this->can($featureKey)) {
            return;
        }

        $message = 'This feature is not available on your current plan. Please upgrade your subscription.';

        $isAjax = $this->CI->input->is_ajax_request()
            || strtolower((string) $this->CI->input->server('HTTP_X_REQUESTED_WITH')) === 'xmlhttprequest';

        if ($isAjax) {
            $this->CI->output
                ->set_status_header(403)
                ->set_content_type('application/json')
                ->set_output(json_encode(array(
                    'status' => 'fail',
                    'message' => $message,
                    'error' => array('plan' => $message),
                )));
            $this->CI->output->_display();
            exit;
        }

        if (function_exists('access_denied')) {
            access_denied();
        }

        show_error($message, 403);
    }

    /**
     * @return array<string, mixed>
     */
    public function features()
    {
        return is_array($this->features) ? $this->features : array();
    }

    /**
     * @return bool
     */
    public function isEnforcing()
    {
        return $this->enforce;
    }
}
