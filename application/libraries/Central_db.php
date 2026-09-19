<?php

defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'libraries/Hospital_uuid.php';

class Central_db
{
    /** @var CI_Controller */
    public $CI;

    /** @var CI_DB_query_builder|null */
    private $db;

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->connect();
    }

    public function connect()
    {
        if ($this->db !== null) {
            return $this->db;
        }

        $this->db = $this->CI->load->database('central', true);

        return $this->db;
    }

    public function db()
    {
        return $this->connect();
    }

    public function tableExists($table)
    {
        $db = $this->connect();
        $table = $db->escape_str($table);
        $query = $db->query("SHOW TABLES LIKE '{$table}'");

        return $query && $query->num_rows() > 0;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findMappingsByEmail($email, $productCode = null)
    {
        if (!$this->tableExists('tenant_user_mappings')) {
            return array();
        }

        $db = $this->connect();
        $email = strtolower(trim((string) $email));
        if ($email === '') {
            return array();
        }

        $db->from('tenant_user_mappings');
        $db->where('email', $email);

        if ($productCode !== null && $productCode !== '') {
            $db->where('product_code', $productCode);
        }

        $query = $db->get();

        return $query ? $query->result_array() : array();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getSubscription($tenantId, $productCode = null)
    {
        if (!$this->tableExists('tenant_subscriptions')) {
            return null;
        }

        $db = $this->connect();
        $db->from('tenant_subscriptions');
        $db->where('tenant_id', $tenantId);
        if ($productCode !== null && $productCode !== '') {
            $db->where('product_code', $productCode);
        }
        $db->order_by('id', 'DESC');
        $db->limit(1);
        $query = $db->get();
        $row = $query ? $query->row_array() : null;

        if ($row && !empty($row['metadata']) && is_string($row['metadata'])) {
            $decoded = json_decode($row['metadata'], true);
            if (is_array($decoded)) {
                $row['metadata'] = $decoded;
            }
        }

        return $row ?: null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getProvisionJobs($status = 'pending', $productCode = null, $limit = 5)
    {
        if (!$this->tableExists('tenant_provision_jobs')) {
            return array();
        }

        $db = $this->connect();
        $db->from('tenant_provision_jobs');
        $db->where('status', $status);
        if ($productCode !== null && $productCode !== '') {
            $db->where('product_code', $productCode);
        }
        $db->order_by('created_at', 'ASC');
        $db->limit((int) $limit);
        $query = $db->get();
        $rows = $query ? $query->result_array() : array();

        foreach ($rows as &$row) {
            if (!empty($row['payload']) && is_string($row['payload'])) {
                $decoded = json_decode($row['payload'], true);
                if (is_array($decoded)) {
                    $row['payload'] = $decoded;
                }
            }
        }

        return $rows;
    }

    public function updateProvisionJob($tenantId, $productCode, array $data)
    {
        if (!$this->tableExists('tenant_provision_jobs')) {
            return false;
        }

        $db = $this->connect();
        $db->where('tenant_id', $tenantId);
        $db->where('product_code', $productCode);
        $data['updated_at'] = date('Y-m-d H:i:s');

        return $db->update('tenant_provision_jobs', $data);
    }

    public function tenantDatabaseExists($databaseName)
    {
        $db = $this->connect();
        $databaseName = $db->escape_str($databaseName);
        $query = $db->query("SHOW DATABASES LIKE '{$databaseName}'");

        return $query && $query->num_rows() > 0;
    }

    public function getTenant($tenantId)
    {
        if (!$this->tableExists('tenants')) {
            return null;
        }

        $db = $this->connect();
        $query = $db->get_where('tenants', array('id' => $tenantId), 1);
        $row = $query ? $query->row_array() : null;

        if ($row && !empty($row['data']) && is_string($row['data'])) {
            $decoded = json_decode($row['data'], true);
            if (is_array($decoded)) {
                $row['data'] = $decoded;
            }
        }

        return $row ?: null;
    }

    public function markTenantProvisioned($tenantId)
    {
        if (!$this->tableExists('tenants')) {
            return;
        }

        $tenant = $this->getTenant($tenantId);
        $data = is_array($tenant['data'] ?? null) ? $tenant['data'] : array();
        $data['provisioned_at'] = date('c');

        $db = $this->connect();
        $db->where('id', $tenantId);
        $db->update('tenants', array(
            'data' => json_encode($data),
            'updated_at' => date('Y-m-d H:i:s'),
        ));
    }

    public function hasColumn($table, $column)
    {
        if (!$this->tableExists($table)) {
            return false;
        }
        $db = $this->connect();
        $fields = $db->list_fields($table);

        return in_array($column, $fields, true);
    }

    public function findPortalAccountByEmail($email)
    {
        if (!$this->tableExists('portal_accounts')) {
            return null;
        }
        $db = $this->connect();
        $query = $db->get_where('portal_accounts', array('email' => strtolower(trim((string) $email))), 1);
        $row = $query ? $query->row_array() : null;

        return $row ?: null;
    }

    public function createPortalAccount(array $data)
    {
        $db = $this->connect();
        $now = date('Y-m-d H:i:s');
        $row = array(
            'uuid' => $data['uuid'] ?? Hospital_uuid::v4(),
            'first_name' => $data['first_name'] ?? '',
            'last_name' => $data['last_name'] ?? '',
            'email' => strtolower(trim((string) ($data['email'] ?? ''))),
            'phone' => $data['phone'] ?? null,
            'tax_number' => $data['tax_number'] ?? null,
            'password' => password_hash((string) ($data['password'] ?? ''), PASSWORD_DEFAULT),
            'status' => $data['status'] ?? 'active',
            'email_verified_at' => $data['email_verified_at'] ?? $now,
            'created_at' => $now,
            'updated_at' => $now,
        );
        $filtered = array();
        foreach ($row as $col => $val) {
            if ($this->hasColumn('portal_accounts', $col)) {
                $filtered[$col] = $val;
            }
        }
        $db->insert('portal_accounts', $filtered);
        $filtered['id'] = (int) $db->insert_id();

        return $filtered;
    }

    public function updatePortalAccount($id, array $data)
    {
        $db = $this->connect();
        $data['updated_at'] = date('Y-m-d H:i:s');
        $db->where('id', (int) $id);

        return $db->update('portal_accounts', $data);
    }

    public function insertTenant(array $data)
    {
        $db = $this->connect();
        $now = date('Y-m-d H:i:s');
        if (!isset($data['created_at'])) {
            $data['created_at'] = $now;
        }
        if (!isset($data['updated_at'])) {
            $data['updated_at'] = $now;
        }
        $filtered = array();
        foreach ($data as $col => $val) {
            if ($this->hasColumn('tenants', $col)) {
                $filtered[$col] = $val;
            }
        }
        $db->insert('tenants', $filtered);

        return $data;
    }

    public function upsertTenantUserMapping($tenantId, $email, array $data)
    {
        $db = $this->connect();
        $email = strtolower(trim((string) $email));
        $existing = $db->get_where('tenant_user_mappings', array(
            'tenant_id' => $tenantId,
            'email' => $email,
        ), 1)->row_array();

        $data['updated_at'] = date('Y-m-d H:i:s');
        if ($existing) {
            $db->where('id', $existing['id']);
            $db->update('tenant_user_mappings', $data);

            return array_merge($existing, $data);
        }

        $data['tenant_id'] = $tenantId;
        $data['email'] = $email;
        $data['created_at'] = date('Y-m-d H:i:s');
        $db->insert('tenant_user_mappings', $data);
        $data['id'] = (int) $db->insert_id();

        return $data;
    }

    public function insertTenantSubscription(array $data)
    {
        $db = $this->connect();
        $now = date('Y-m-d H:i:s');
        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $data['metadata'] = json_encode($data['metadata']);
        }
        $data['created_at'] = $now;
        $data['updated_at'] = $now;
        $filtered = array();
        foreach ($data as $col => $val) {
            if ($this->hasColumn('tenant_subscriptions', $col)) {
                $filtered[$col] = $val;
            }
        }
        $db->insert('tenant_subscriptions', $filtered);
        $filtered['id'] = (int) $db->insert_id();

        return $filtered;
    }

    public function upsertProvisionJob($tenantId, $productCode, array $data)
    {
        $db = $this->connect();
        $existing = $db->get_where('tenant_provision_jobs', array(
            'tenant_id' => $tenantId,
            'product_code' => $productCode,
        ), 1)->row_array();

        if (isset($data['payload']) && is_array($data['payload'])) {
            $data['payload'] = json_encode($data['payload']);
        }
        $data['updated_at'] = date('Y-m-d H:i:s');

        if ($existing) {
            $db->where('id', $existing['id']);
            $db->update('tenant_provision_jobs', $data);
            $row = array_merge($existing, $data);
        } else {
            $data['tenant_id'] = $tenantId;
            $data['product_code'] = $productCode;
            $data['created_at'] = date('Y-m-d H:i:s');
            $db->insert('tenant_provision_jobs', $data);
            $row = $data;
            $row['id'] = (int) $db->insert_id();
        }

        if (!empty($row['payload']) && is_string($row['payload'])) {
            $decoded = json_decode($row['payload'], true);
            if (is_array($decoded)) {
                $row['payload'] = $decoded;
            }
        }

        return $row;
    }

    public function getProvisionJob($tenantId, $productCode)
    {
        $db = $this->connect();
        $query = $db->get_where('tenant_provision_jobs', array(
            'tenant_id' => $tenantId,
            'product_code' => $productCode,
        ), 1);
        $row = $query ? $query->row_array() : null;
        if ($row && !empty($row['payload']) && is_string($row['payload'])) {
            $decoded = json_decode($row['payload'], true);
            if (is_array($decoded)) {
                $row['payload'] = $decoded;
            }
        }

        return $row ?: null;
    }

    public function findMappingTenantIdByPortalAccount($portalAccountId, $productCode)
    {
        if (!$this->tableExists('tenant_user_mappings')) {
            return null;
        }
        $db = $this->connect();
        $db->select('tenant_id');
        $db->from('tenant_user_mappings');
        $db->where('portal_account_id', (int) $portalAccountId);
        if ($productCode !== '') {
            $db->where('product_code', $productCode);
        }
        $db->limit(1);
        $query = $db->get();
        $row = $query ? $query->row_array() : null;

        return $row ? (string) $row['tenant_id'] : null;
    }

    public function createOtp(array $data)
    {
        $db = $this->connect();
        $now = date('Y-m-d H:i:s');
        $row = array(
            'uuid' => $data['uuid'] ?? Hospital_uuid::v4(),
            'channel' => $data['channel'],
            'identifier' => $data['identifier'],
            'otp_hash' => $data['otp_hash'],
            'expires_at' => $data['expires_at'],
            'ip_address' => $data['ip_address'] ?? null,
            'attempts' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        );
        $db->insert('portal_signup_otps', $row);
        $row['id'] = (int) $db->insert_id();

        return $row;
    }

    public function deleteUnverifiedOtps($channel, $identifier)
    {
        $db = $this->connect();
        $db->where('channel', $channel);
        $db->where('identifier', $identifier);
        $db->where('verified_at IS NULL', null, false);
        $db->delete('portal_signup_otps');
    }

    public function latestUnverifiedOtp($channel, $identifier)
    {
        $db = $this->connect();
        $db->from('portal_signup_otps');
        $db->where('channel', $channel);
        $db->where('identifier', $identifier);
        $db->where('verified_at IS NULL', null, false);
        $db->where('consumed_at IS NULL', null, false);
        $db->order_by('id', 'DESC');
        $db->limit(1);
        $query = $db->get();

        return $query ? $query->row_array() : null;
    }

    public function latestVerifiedOtp($channel, $identifier)
    {
        $db = $this->connect();
        $db->from('portal_signup_otps');
        $db->where('channel', $channel);
        $db->where('identifier', $identifier);
        $db->where('verified_at IS NOT NULL', null, false);
        $db->where('consumed_at IS NULL', null, false);
        $db->order_by('id', 'DESC');
        $db->limit(1);
        $query = $db->get();

        return $query ? $query->row_array() : null;
    }

    public function updateOtp($id, array $data)
    {
        $db = $this->connect();
        $data['updated_at'] = date('Y-m-d H:i:s');
        $db->where('id', (int) $id);

        return $db->update('portal_signup_otps', $data);
    }

    public function insertPendingSignup(array $data)
    {
        $db = $this->connect();
        $now = date('Y-m-d H:i:s');
        $data['created_at'] = $now;
        $data['updated_at'] = $now;
        $filtered = array();
        foreach ($data as $col => $val) {
            if ($this->hasColumn('pending_signups', $col)) {
                $filtered[$col] = $val;
            }
        }
        $db->insert('pending_signups', $filtered);
        $filtered['id'] = (int) $db->insert_id();
        // Preserve uuid from input for redirects even if somehow filtered
        if (!isset($filtered['uuid']) && isset($data['uuid'])) {
            $filtered['uuid'] = $data['uuid'];
        }

        return $filtered;
    }

    public function getPendingSignupByUuid($uuid)
    {
        if (!$this->tableExists('pending_signups')) {
            return null;
        }
        $db = $this->connect();
        $query = $db->get_where('pending_signups', array('uuid' => $uuid), 1);
        $row = $query ? $query->row_array() : null;

        return $row ?: null;
    }

    public function updatePendingSignup($id, array $data)
    {
        $db = $this->connect();
        $data['updated_at'] = date('Y-m-d H:i:s');
        $db->where('id', (int) $id);

        return $db->update('pending_signups', $data);
    }

    public function hasActivePendingSignup($email)
    {
        $db = $this->connect();
        $db->from('pending_signups');
        $db->where('email', strtolower(trim((string) $email)));
        $db->where('status', 'pending');
        $db->group_start();
        $db->where('expires_at IS NULL', null, false);
        $db->or_where('expires_at >', date('Y-m-d H:i:s'));
        $db->group_end();
        $db->limit(1);
        $query = $db->get();

        return $query && $query->num_rows() > 0;
    }

    public function findRegistrationCoupon($code)
    {
        if (!$this->tableExists('registration_coupons')) {
            return null;
        }
        $db = $this->connect();
        $query = $db->get_where('registration_coupons', array('code' => strtoupper(trim((string) $code))), 1);
        $row = $query ? $query->row_array() : null;

        return $row ?: null;
    }

    public function findPartnerByCode($code)
    {
        if (!$this->tableExists('partners')) {
            return null;
        }
        $db = $this->connect();
        $code = strtoupper(trim((string) $code));
        if ($code === '') {
            return null;
        }
        $query = $db->get_where('partners', array('code' => $code), 1);
        $row = $query ? $query->row_array() : null;

        return $row ?: null;
    }

    public function recordPaymentSnapshot(array $data)
    {
        if (!$this->tableExists('payments')) {
            return false;
        }
        $db = $this->connect();
        $now = date('Y-m-d H:i:s');
        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $data['metadata'] = json_encode($data['metadata']);
        }
        $existing = $db->get_where('payments', array(
            'gateway' => $data['gateway'],
            'gateway_payment_id' => $data['gateway_payment_id'],
        ), 1)->row_array();

        $data['updated_at'] = $now;
        if ($existing) {
            $db->where('id', $existing['id']);

            return $db->update('payments', $data);
        }
        $data['created_at'] = $now;

        return $db->insert('payments', $data);
    }
}
