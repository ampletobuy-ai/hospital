<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Aligns tenant permission_group / roles_permissions / modules with the plan feature gate.
 */
class Plan_permission_sync
{
    /** @var CI_Controller */
    public $CI;

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->library('plan_feature_gate');
    }

    /**
     * Sync the current request DB (session tenant) to the active plan.
     *
     * @return array<string, int>
     */
    public function syncCurrentTenant()
    {
        $tenantId = (string) $this->CI->session->userdata('tenant_id');
        if ($tenantId === '') {
            return array('modules_disabled' => 0, 'modules_enabled' => 0, 'permissions_cleared' => 0);
        }

        $this->CI->plan_feature_gate->loadForTenantId($tenantId);

        return $this->syncOnDb($this->CI->db);
    }

    /**
     * Sync a tenant by id (switches to tenant DB when tenancy is on).
     *
     * @param string $tenantId
     * @return array<string, int>
     */
    public function syncTenantById($tenantId)
    {
        $tenantId = trim((string) $tenantId);
        if ($tenantId === '') {
            throw new InvalidArgumentException('tenant_id required');
        }

        $this->CI->load->library(array('tenant_context', 'plan_feature_gate'));
        $this->CI->plan_feature_gate->loadForTenantId($tenantId);

        if (!$this->CI->plan_feature_gate->isEnforcing()) {
            return array('modules_disabled' => 0, 'modules_enabled' => 0, 'permissions_cleared' => 0);
        }

        $tenantDatabase = (string) $this->CI->tenant_context->resolveDatabaseName($tenantId);
        include APPPATH . 'config/database.php';
        $config = $db['default'];
        $config['database'] = $tenantDatabase;
        $config['pconnect'] = false;
        $tenantDb = $this->CI->load->database($config, true);

        return $this->syncOnDb($tenantDb);
    }

    /**
     * @param CI_DB_query_builder $db
     * @param string|null $tenantId optional; when set, reloads gate for that tenant
     * @return array<string, int>
     */
    public function syncOnDb($db, $tenantId = null)
    {
        if ($tenantId !== null && $tenantId !== '') {
            $this->CI->plan_feature_gate->loadForTenantId($tenantId);
        }

        if (!$this->CI->plan_feature_gate->isEnforcing()) {
            return array('modules_disabled' => 0, 'modules_enabled' => 0, 'permissions_cleared' => 0);
        }

        $stats = array(
            'modules_disabled' => 0,
            'modules_enabled' => 0,
            'permissions_cleared' => 0,
        );

        $gated = $this->CI->plan_feature_gate->gatedModuleMap();

        if ($db->table_exists('permission_group')) {
            $groups = $db->select('id, short_code, is_active')->get('permission_group')->result_array();
            foreach ($groups as $group) {
                $short = (string) ($group['short_code'] ?? '');
                if ($short === '' || !isset($gated[$short])) {
                    continue;
                }

                $allowed = $this->CI->plan_feature_gate->moduleAllowed($short);
                if (!$allowed) {
                    if ((string) $group['is_active'] !== '0') {
                        $db->where('id', (int) $group['id'])->update('permission_group', array('is_active' => 0));
                        $stats['modules_disabled']++;
                    }
                } else {
                    if ((string) $group['is_active'] !== '1') {
                        $db->where('id', (int) $group['id'])->update('permission_group', array('is_active' => 1));
                        $stats['modules_enabled']++;
                    }
                }
            }
        }

        // Patient-facing permission mirror table when present.
        if ($db->table_exists('permission_patient')) {
            $rows = $db->select('id, permission_group_short_code, is_active')->get('permission_patient')->result_array();
            foreach ($rows as $row) {
                $short = (string) ($row['permission_group_short_code'] ?? '');
                if ($short === '' || !isset($gated[$short])) {
                    continue;
                }
                $allowed = $this->CI->plan_feature_gate->moduleAllowed($short);
                $db->where('id', (int) $row['id'])->update('permission_patient', array(
                    'is_active' => $allowed ? 1 : 0,
                ));
            }
        }

        if ($db->table_exists('permission_category') && $db->table_exists('roles_permissions')) {
            $categories = $db->select('id, short_code')->get('permission_category')->result_array();
            foreach ($categories as $cat) {
                $code = (string) ($cat['short_code'] ?? '');
                if ($code === '') {
                    continue;
                }
                // Locked if even view is disallowed on the plan.
                if ($this->CI->plan_feature_gate->permissionAllowed($code, 'can_view')) {
                    continue;
                }
                $db->where('perm_cat_id', (int) $cat['id'])->update('roles_permissions', array(
                    'can_view' => 0,
                    'can_add' => 0,
                    'can_edit' => 0,
                    'can_delete' => 0,
                ));
                $stats['permissions_cleared'] += (int) $db->affected_rows();
            }
        }

        return $stats;
    }
}
