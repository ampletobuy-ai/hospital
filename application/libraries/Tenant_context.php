<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Tenant_context
{
    /** @var CI_Controller */
    public $CI;

    /** @var string|null */
    private $tenantId;

    /** @var string|null */
    private $databaseName;

    /** @var string|null */
    private $dbGroup;

    /** @var array<string, mixed>|null */
    private $subscription;

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->config('tenancy-config');
    }

    public function isEnabled()
    {
        return (bool) $this->CI->config->item('tenancy_enabled');
    }

    public function getTenantId()
    {
        return $this->tenantId;
    }

    public function getDatabaseName()
    {
        return $this->databaseName;
    }

    public function getDbGroup()
    {
        return $this->dbGroup;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getSubscription()
    {
        return $this->subscription;
    }

    public function resolveDatabaseName($tenantId)
    {
        $prefix = (string) $this->CI->config->item('tenant_database_prefix');
        $tenantId = trim((string) $tenantId);

        if ($tenantId === '') {
            return null;
        }

        return $prefix . $tenantId;
    }

    public function dbGroupForTenant($tenantId)
    {
        return 'tenant_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string) $tenantId);
    }

    public function initializeFromSession()
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $tenantId = $this->CI->session->userdata('tenant_id');
        if (!$tenantId) {
            return false;
        }

        return $this->initialize($tenantId);
    }

    public function initialize($tenantId)
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $tenantId = trim((string) $tenantId);
        if ($tenantId === '') {
            return false;
        }

        $databaseName = $this->resolveDatabaseName($tenantId);
        if ($databaseName === null || $databaseName === '') {
            return false;
        }

        $this->tenantId = $tenantId;
        $this->databaseName = $databaseName;
        $this->dbGroup = $this->dbGroupForTenant($tenantId);
        $this->loadSubscription();

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function tenantDatabaseConfig()
    {
        include APPPATH . 'config/database.php';
        $config = $db['default'];
        $config['database'] = (string) $this->databaseName;
        $config['pconnect'] = false;

        return $config;
    }

    private function loadSubscription()
    {
        if ($this->tenantId === null) {
            return;
        }

        $this->CI->load->library('central_db');
        $productCode = (string) $this->CI->config->item('product_code');
        $this->subscription = $this->CI->central_db->getSubscription($this->tenantId, $productCode);
    }
}
