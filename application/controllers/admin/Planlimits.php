<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * Tenant-facing Plan & limits page (features + quotas).
 */
class Planlimits extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library(array('plan_feature_gate', 'ResourceQuota', 'subscription_resolver'));
        $this->load->config('hospital_portal');
    }

    public function index()
    {
        if (!$this->rbac->hasPrivilege('superadmin', 'can_view') && !$this->rbac->hasPrivilege('general_setting', 'can_view')) {
            access_denied();
        }

        $this->session->set_userdata('top_menu', 'setup');
        $this->session->set_userdata('sub_menu', 'schsettings/index');
        $this->session->set_userdata('inner_menu', 'admin/planlimits');

        $tenantId = (string) $this->session->userdata('tenant_id');
        $subscription = $tenantId !== '' ? $this->subscription_resolver->latestForTenant($tenantId) : null;
        $plans = (array) $this->config->item('hospital_plans');
        $planCode = $subscription ? (string) ($subscription['plan_code'] ?? '') : '';
        $planMeta = ($planCode !== '' && isset($plans[$planCode])) ? $plans[$planCode] : array();

        $features = $this->plan_feature_gate->features();
        $featureLabels = array(
            'opd' => 'OPD',
            'patient_registration' => 'Patient registration',
            'appointment' => 'Appointments',
            'billing' => 'Billing',
            'ipd' => 'IPD',
            'bed_ward' => 'Bed / Ward',
            'pharmacy' => 'Pharmacy',
            'laboratory' => 'Laboratory (Pathology / Radiology)',
            'inventory' => 'Inventory',
            'tpa_insurance' => 'TPA / Insurance',
            'doctor_commission' => 'Doctor commission / Referral',
            'reports' => 'Reports',
            'whatsapp_sms' => 'WhatsApp / SMS',
            'duty_roster' => 'Duty roster',
            'multi_branch' => 'Multi-branch',
            'api_integration' => 'API integration',
            'customization' => 'Customization',
            'support' => 'Support',
        );

        $quotaLimits = $tenantId !== '' ? $this->subscription_resolver->quotaLimits($tenantId) : array();

        $usage = array(
            'no_of_staff' => $this->resourcequota->getUsage('no_of_staff'),
            'no_of_patient' => $this->resourcequota->getUsage('no_of_patient'),
            'storage' => $this->resourcequota->getUsage('storage'),
        );

        $data = array(
            'module' => 'setup',
            'title' => 'Plan & limits',
            'plan_enforcing' => $this->plan_feature_gate->isEnforcing(),
            'plan_code' => $planCode,
            'plan_name' => isset($planMeta['name']) ? $planMeta['name'] : ($planCode !== '' ? $planCode : 'N/A'),
            'subscription' => $subscription,
            'features' => $features,
            'feature_labels' => $featureLabels,
            'quota_limits' => $quotaLimits,
            'usage' => $usage,
            'upgrade_url' => site_url('site/subscription'),
        );

        $this->load->view('layout/header', $data);
        $this->load->view('admin/planlimits/index', $data);
        $this->load->view('layout/footer', $data);
    }

    /**
     * Super Admin can re-sync modules/permissions after a plan change.
     */
    public function sync()
    {
        if (!$this->rbac->hasPrivilege('superadmin', 'can_view')) {
            access_denied();
        }

        $this->load->library('plan_permission_sync');
        $stats = $this->plan_permission_sync->syncCurrentTenant();
        $this->session->set_flashdata(
            'msg',
            '<div class="alert alert-success">Plan permissions synced. Modules disabled: '
            . (int) $stats['modules_disabled']
            . ', enabled: ' . (int) $stats['modules_enabled']
            . ', permission rows cleared: ' . (int) $stats['permissions_cleared']
            . '.</div>'
        );
        redirect('admin/planlimits');
    }
}
