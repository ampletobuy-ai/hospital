<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Hospital_tenant_login_service
{
    /** @var CI_Controller */
    public $CI;

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->library(array('central_db', 'login_subscription_gate', 'tenant_context'));
        $this->CI->load->config('tenancy-config');
        $this->CI->load->model('staff_model');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function tenantsForCredentials($email, $password)
    {
        $email = strtolower(trim((string) $email));
        $password = trim((string) $password);
        if ($email === '' || $password === '') {
            return array();
        }

        $productCode = (string) $this->CI->config->item('product_code');
        $mappings = $this->CI->central_db->findMappingsByEmail($email, $productCode);
        $candidates = array();

        foreach ($mappings as $mapping) {
            $tenantId = (string) ($mapping['tenant_id'] ?? '');
            if ($tenantId === '') {
                continue;
            }

            $databaseName = $this->CI->tenant_context->resolveDatabaseName($tenantId);
            if (!$this->CI->central_db->tenantDatabaseExists($databaseName)) {
                continue;
            }

            $staff = $this->verifyStaffInTenant($tenantId, $email, $password);
            if (!$staff) {
                continue;
            }

            if (!(int) ($staff->is_active ?? 0)) {
                continue;
            }

            $candidates[] = array(
                'id' => $tenantId,
                'name' => $this->tenantDisplayName($tenantId),
                'staff' => $staff,
                'login_allowed' => $this->CI->login_subscription_gate->isLoginAllowed($tenantId),
            );
        }

        usort($candidates, function ($a, $b) {
            return strcasecmp((string) $a['name'], (string) $b['name']);
        });

        return $candidates;
    }

    private function verifyStaffInTenant($tenantId, $email, $password)
    {
        $this->CI->tenant_context->initialize($tenantId);
        $config = $this->CI->tenant_context->tenantDatabaseConfig();
        $tenantDb = $this->CI->load->database($config, true);

        $query = $tenantDb->get_where('staff', array('email' => $email), 1);
        $staff = $query ? $query->row() : null;
        if (!$staff || empty($staff->password)) {
            return null;
        }

        if (!password_verify($password, (string) $staff->password)) {
            return null;
        }

        return $staff;
    }

    private function tenantDisplayName($tenantId)
    {
        $tenant = $this->CI->central_db->getTenant($tenantId);
        if (is_array($tenant)) {
            $data = is_array($tenant['data'] ?? null) ? $tenant['data'] : array();
            foreach (array('store_name', 'property_name', 'hospital_name', 'name') as $key) {
                $name = trim((string) ($data[$key] ?? ''));
                if ($name !== '') {
                    return $name;
                }
            }
        }

        $this->CI->tenant_context->initialize($tenantId);
        $config = $this->CI->tenant_context->tenantDatabaseConfig();
        $tenantDb = $this->CI->load->database($config, true);
        $settings = $tenantDb->get('sch_settings', 1)->row_array();
        if (!empty($settings['name'])) {
            return (string) $settings['name'];
        }

        return 'Hospital ' . substr($tenantId, 0, 8);
    }

    /**
     * Ensure a tenant staff row can sign in via multi-tenant login
     * (portal_accounts + tenant_user_mappings).
     *
     * @param array<string,mixed> $staff
     */
    public function ensureStaffPortalAccess(array $staff, $plainPassword = null)
    {
        $this->CI->load->config('tenancy-config');
        if (!$this->CI->config->item('tenancy_enabled')) {
            return null;
        }

        $tenantId = trim((string) $this->CI->session->userdata('tenant_id'));
        if ($tenantId === '') {
            $this->CI->load->library('tenant_context');
            $tenantId = trim((string) $this->CI->tenant_context->getTenantId());
        }
        if ($tenantId === '') {
            return null;
        }

        $email = strtolower(trim((string) ($staff['email'] ?? '')));
        if ($email === '') {
            return null;
        }

        $product = (string) $this->CI->config->item('product_code');
        $portal = $this->CI->central_db->findPortalAccountByEmail($email);
        if (!$portal) {
            $portal = $this->CI->central_db->createPortalAccount(array(
                'first_name' => (string) ($staff['name'] ?? 'Staff'),
                'last_name' => (string) ($staff['surname'] ?? ''),
                'email' => $email,
                'phone' => $staff['contact_no'] ?? null,
                'password' => ($plainPassword !== null && $plainPassword !== '')
                    ? (string) $plainPassword
                    : bin2hex(random_bytes(8)),
            ));
        }

        $mapping = array(
            'portal_account_id' => (int) $portal['id'],
            'phone' => $staff['contact_no'] ?? ($portal['phone'] ?? null),
            'is_primary_admin' => 0,
        );
        if ($this->CI->central_db->hasColumn('tenant_user_mappings', 'product_code')) {
            $mapping['product_code'] = $product;
        }

        return $this->CI->central_db->upsertTenantUserMapping($tenantId, $email, $mapping);
    }
}
