<?php

defined('BASEPATH') OR exit('No direct script access allowed');

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
}
