<?php

defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'libraries/Hospital_uuid.php';

class Hospital_tenant_provisioning
{
    /** @var CI_Controller */
    public $CI;

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->library('central_db');
        $this->CI->load->config('tenancy-config');
        $this->CI->load->config('hospital_portal');
    }

    /**
     * @param array<string,mixed> $portalAccount
     * @param array<string,mixed> $payload
     * @return array<string,mixed> tenant row
     */
    public function provisionFromPortalAccount(array $portalAccount, array $payload = array())
    {
        $product = (string) $this->CI->config->item('product_code');
        $tenantId = Hospital_uuid::v4();
        $hospitalName = trim((string) ($payload['hospital_name'] ?? $payload['property_name'] ?? $payload['store_name'] ?? 'Hospital'));
        if ($hospitalName === '') {
            $hospitalName = 'Hospital';
        }

        $plainPassword = trim((string) ($payload['password_plain'] ?? ''));
        $signupProfile = isset($payload['signup_profile']) && is_array($payload['signup_profile'])
            ? $payload['signup_profile']
            : array();
        $now = date('Y-m-d H:i:s');

        $tenantData = array(
            'store_name' => $hospitalName,
            'hospital_name' => $hospitalName,
            'portal_account_id' => $portalAccount['id'],
            'portal_admin_email' => $portalAccount['email'],
        );
        if ($signupProfile) {
            $tenantData['signup_profile'] = $signupProfile;
        }

        $tenantAttrs = array(
            'id' => $tenantId,
            'product_code' => $product,
            'data' => json_encode($tenantData),
            'created_at' => $now,
            'updated_at' => $now,
        );

        // Optional dedicated columns when present on central.tenants
        foreach (array(
            'store_name' => $hospitalName,
            'property_name' => $hospitalName,
            'portal_account_id' => $portalAccount['id'],
            'portal_admin_email' => $portalAccount['email'],
            'portal_admin_first_name' => $portalAccount['first_name'] ?? '',
            'portal_admin_last_name' => $portalAccount['last_name'] ?? '',
            'provisioned_at' => null,
        ) as $col => $val) {
            if ($this->CI->central_db->hasColumn('tenants', $col)) {
                $tenantAttrs[$col] = $val;
            }
        }

        $this->CI->central_db->insertTenant($tenantAttrs);

        $mapping = array(
            'portal_account_id' => $portalAccount['id'],
            'phone' => $portalAccount['phone'] ?? null,
            'is_primary_admin' => 1,
        );
        if ($this->CI->central_db->hasColumn('tenant_user_mappings', 'product_code')) {
            $mapping['product_code'] = $product;
        }
        $this->CI->central_db->upsertTenantUserMapping($tenantId, $portalAccount['email'], $mapping);

        $signupPlanCode = (string) ($payload['plan_code'] ?? 'trial');
        $billingCycle = (string) ($payload['billing_cycle'] ?? 'annual');
        $isTrial = ($signupPlanCode === 'trial');
        $subscriptionPlanCode = $isTrial
            ? (string) $this->CI->config->item('default_plan_code')
            : $signupPlanCode;

        $plans = (array) $this->CI->config->item('hospital_plans');
        $planConfig = isset($plans[$subscriptionPlanCode]) ? $plans[$subscriptionPlanCode] : array();
        $trialDays = (int) $this->CI->config->item('trial_days');
        $periodEnd = $isTrial
            ? date('Y-m-d H:i:s', strtotime('+' . $trialDays . ' days'))
            : ($billingCycle === 'monthly'
                ? date('Y-m-d H:i:s', strtotime('+1 month'))
                : date('Y-m-d H:i:s', strtotime('+1 year')));

        $subscription = array(
            'tenant_id' => $tenantId,
            'portal_account_id' => $portalAccount['id'],
            'plan_code' => $subscriptionPlanCode,
            'billing_cycle' => $billingCycle,
            'status' => $isTrial ? 'trialing' : 'active',
            'trial_ends_at' => $isTrial ? $periodEnd : null,
            'current_period_start' => $now,
            'current_period_end' => $periodEnd,
            'max_users' => (int) ($payload['max_users'] ?? $planConfig['max_users'] ?? 5),
            'max_warehouses' => (int) ($payload['max_warehouses'] ?? $planConfig['max_warehouses'] ?? 1),
            'metadata' => array(
                'signup_source' => 'hospital',
                'signup_plan_code' => $signupPlanCode,
                'product_code' => $product,
                'store_name' => $hospitalName,
                'hospital_name' => $hospitalName,
                'quota' => $planConfig['quota'] ?? array(),
                'features' => $planConfig['features'] ?? array(),
                'signup_profile' => $signupProfile,
            ),
        );

        if ($this->CI->central_db->hasColumn('tenant_subscriptions', 'product_code')) {
            $subscription['product_code'] = $product;
        }
        if ($this->CI->central_db->hasColumn('tenant_subscriptions', 'max_devices')) {
            $subscription['max_devices'] = (int) ($payload['max_devices'] ?? $planConfig['max_devices'] ?? 0);
        }

        $partnerId = (int) ($payload['partner_id'] ?? 0);
        $partnerCode = trim((string) ($payload['partner_code'] ?? ''));
        if ($partnerId > 0) {
            $subscription['metadata']['partner_id'] = $partnerId;
            $subscription['metadata']['partner_code'] = $partnerCode !== '' ? $partnerCode : null;
            if ($this->CI->central_db->hasColumn('tenant_subscriptions', 'partner_id')) {
                $subscription['partner_id'] = $partnerId;
                $subscription['partner_code'] = $partnerCode !== '' ? $partnerCode : null;
            }
        }

        $this->CI->central_db->insertTenantSubscription($subscription);

        $jobPayload = array(
            'hospital_name' => $hospitalName,
            'store_name' => $hospitalName,
            'portal_account_id' => $portalAccount['id'],
            'portal_admin_email' => $portalAccount['email'],
        );
        if ($plainPassword !== '') {
            $jobPayload['password_plain'] = $plainPassword;
        }

        $this->CI->central_db->upsertProvisionJob($tenantId, $product, array(
            'status' => 'pending',
            'attempts' => 0,
            'last_error' => null,
            'payload' => $jobPayload,
            'started_at' => null,
            'finished_at' => null,
        ));

        return $this->CI->central_db->getTenant($tenantId);
    }
}
