<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Login_subscription_gate
{
    /** @var CI_Controller */
    public $CI;

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->library('subscription_resolver');
    }

    public function isLoginAllowed($tenantId)
    {
        $tenantId = trim((string) $tenantId);
        if ($tenantId === '') {
            return false;
        }

        $subscription = $this->CI->subscription_resolver->latestForTenant($tenantId);
        if (!$subscription) {
            return true;
        }

        $status = (string) ($subscription['status'] ?? '');
        if ($status === 'purged' || !empty($subscription['purged_at'])) {
            return false;
        }

        return true;
    }

    public function isSubscriptionActive($tenantId)
    {
        return $this->CI->subscription_resolver->isAccessAllowed($tenantId);
    }
}
