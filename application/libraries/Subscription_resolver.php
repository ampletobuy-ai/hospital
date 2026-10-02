<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Subscription_resolver
{
    /** @var CI_Controller */
    public $CI;

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->library('central_db');
        $this->CI->load->config('tenancy-config');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function latestForTenant($tenantId)
    {
        $tenantId = trim((string) $tenantId);
        if ($tenantId === '') {
            return null;
        }

        $productCode = (string) $this->CI->config->item('product_code');

        return $this->CI->central_db->getSubscription($tenantId, $productCode);
    }

    public function isAccessAllowed($tenantId)
    {
        $subscription = $this->latestForTenant($tenantId);
        if (!$subscription) {
            return true;
        }

        $status = (string) ($subscription['status'] ?? '');
        if (in_array($status, array('expired', 'cancelled', 'purged'), true)) {
            return false;
        }

        if ($status === 'past_due') {
            $graceEnds = $subscription['grace_ends_at'] ?? null;
            if ($graceEnds && strtotime((string) $graceEnds) >= time()) {
                return true;
            }

            return false;
        }

        $periodEnd = $subscription['current_period_end'] ?? $subscription['trial_ends_at'] ?? null;
        if ($periodEnd && strtotime((string) $periodEnd) < time()) {
            $graceEnds = $subscription['grace_ends_at'] ?? null;
            if ($graceEnds && strtotime((string) $graceEnds) >= time()) {
                return true;
            }

            return false;
        }

        return true;
    }

    /**
     * @return array<string, int|null>
     */
    public function quotaLimits($tenantId)
    {
        $subscription = $this->latestForTenant($tenantId);
        if (!$subscription) {
            return array();
        }

        $metadata = $subscription['metadata'] ?? array();
        if (!is_array($metadata)) {
            $metadata = array();
        }

        $limits = array();
        $nestedQuota = isset($metadata['quota']) && is_array($metadata['quota'])
            ? $metadata['quota']
            : array();

        foreach ($nestedQuota as $key => $value) {
            if (is_numeric($value)) {
                $limits[$key] = (int) $value;
            }
        }

        // Allow flat metadata keys to override nested quota (legacy / overrides).
        foreach (array('no_of_patient', 'no_of_staff', 'storage_mb', 'storage', 'max_users', 'max_warehouses') as $key) {
            if (isset($metadata[$key]) && is_numeric($metadata[$key])) {
                $limits[$key] = (int) $metadata[$key];
            }
        }

        if (isset($subscription['max_users'])) {
            $limits['no_of_staff'] = (int) $subscription['max_users'];
        }

        if (isset($subscription['max_warehouses']) && is_numeric($subscription['max_warehouses'])) {
            $limits['max_warehouses'] = (int) $subscription['max_warehouses'];
        } elseif (!isset($limits['max_warehouses'])) {
            // Fallback to plan config when subscription column / metadata omit it.
            $planCode = (string) ($subscription['plan_code'] ?? '');
            $this->CI->load->config('hospital_portal');
            $plans = (array) $this->CI->config->item('hospital_plans');
            if ($planCode !== '' && isset($plans[$planCode]['max_warehouses'])) {
                $limits['max_warehouses'] = (int) $plans[$planCode]['max_warehouses'];
            }
        }

        if (isset($limits['storage_mb']) && !isset($limits['storage'])) {
            $limits['storage'] = (int) $limits['storage_mb'] * 1024;
        }

        return $limits;
    }
}
