<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Tenant_hook
{
    public function initialize_tenant()
    {
        $CI =& get_instance();
        if (!isset($CI->session)) {
            return;
        }

        $CI->load->config('tenancy-config');
        if (!$CI->config->item('tenancy_enabled')) {
            return;
        }

        $CI->load->library('tenant_context');
        $CI->tenant_context->initializeFromSession();
    }
}
