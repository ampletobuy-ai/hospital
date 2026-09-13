<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Db_manager
{
    public $connections = array();
    public $CI;
    public $db;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->config('tenancy-config');

        if ($this->CI->config->item('tenancy_enabled')) {
            $this->CI->load->library('tenant_context');
            if ($this->CI->tenant_context->getTenantId()) {
                $config = $this->CI->tenant_context->tenantDatabaseConfig();
                $this->CI->db = $this->CI->load->database($config, true);

                return;
            }

            $tenantId = $this->CI->session->userdata('tenant_id');
            if ($tenantId && $this->CI->tenant_context->initialize($tenantId)) {
                $config = $this->CI->tenant_context->tenantDatabaseConfig();
                $this->CI->db = $this->CI->load->database($config, true);

                return;
            }
        }

        if ($this->CI->session->has_userdata('hospitaladmin')) {
            $database_session = $this->CI->session->userdata('hospitaladmin');
            $database_group = $database_session['db_array']['db_group'];
            $this->CI->db = $this->CI->load->database($database_group, true);
        } elseif ($this->CI->session->has_userdata('patient') && $this->CI->session->userdata('tenant_id')) {
            $this->CI->load->library('tenant_context');
            if ($this->CI->tenant_context->initialize($this->CI->session->userdata('tenant_id'))) {
                $config = $this->CI->tenant_context->tenantDatabaseConfig();
                $this->CI->db = $this->CI->load->database($config, true);

                return;
            }
            $this->CI->db = $this->CI->load->database('default', true);
        } else {
            $this->CI->db = $this->CI->load->database('default', true);
        }
    }

    public function get_connection($db_name)
    {
        $this->connections[$db_name] = $this->CI->load->database($db_name, true);

        return $this->connections[$db_name];
    }
}
