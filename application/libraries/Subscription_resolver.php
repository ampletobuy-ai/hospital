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

        $limits = $metadata;
        if (isset($subscription['max_users'])) {
            $limits['no_of_staff'] = (int) $subscription['max_users'];
        }

        if (isset($limits['storage_mb'])) {
            $limits['storage'] = (int) $limits['storage_mb'] * 1024;
        }

        return $limits;
    }
}
