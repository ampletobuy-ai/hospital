<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Ensures WhatsApp Messaging module + setting privilege exist in a tenant DB.
 */
class Whatsapp_module_setup
{
    /** @var CI_Controller */
    public $CI;

    public function __construct()
    {
        $this->CI =& get_instance();
    }

    /**
     * @param mysqli|null $mysqli Optional CLI mysqli; when null uses CI db.
     * @return bool
     */
    public function ensure($mysqli = null)
    {
        if ($mysqli instanceof mysqli) {
            return $this->ensureWithMysqli($mysqli);
        }

        if (!isset($this->CI->db) || !$this->CI->db->table_exists('permission_group')) {
            return false;
        }

        $this->CI->load->model('Whatsappconfig_model');
        $this->CI->Whatsappconfig_model->ensureTable();

        $group = $this->CI->db->where('short_code', 'whatsapp_messaging')->get('permission_group')->row_array();
        if (empty($group)) {
            $this->CI->db->insert('permission_group', array(
                'name'       => 'WhatsApp Messaging',
                'short_code' => 'whatsapp_messaging',
                'is_active'  => 1,
                'system'     => 0,
                'sort_order' => 17.10,
            ));
            $groupId = (int) $this->CI->db->insert_id();
        } else {
            $groupId = (int) $group['id'];
            if ((int) $group['is_active'] !== 1) {
                $this->CI->db->where('id', $groupId)->update('permission_group', array('is_active' => 1));
            }
        }

        if (!$this->CI->db->table_exists('permission_category')) {
            return $groupId > 0;
        }

        $cat = $this->CI->db->where('short_code', 'whatsapp_setting')->get('permission_category')->row_array();
        if (empty($cat)) {
            $sys = $this->CI->db->where('short_code', 'system_settings')->get('permission_group')->row_array();
            $permGroupId = !empty($sys['id']) ? (int) $sys['id'] : $groupId;
            $this->CI->db->insert('permission_category', array(
                'perm_group_id' => $permGroupId,
                'name'          => 'WhatsApp Setting',
                'short_code'    => 'whatsapp_setting',
                'enable_view'   => 1,
                'enable_add'    => 0,
                'enable_edit'   => 1,
                'enable_delete' => 0,
            ));
            $catId = (int) $this->CI->db->insert_id();
        } else {
            $catId = (int) $cat['id'];
        }

        if ($catId > 0 && $this->CI->db->table_exists('roles_permissions')) {
            foreach (array(7, 1) as $roleId) {
                $role = $this->CI->db->where('id', $roleId)->get('roles')->row_array();
                if (empty($role)) {
                    continue;
                }
                $exists = $this->CI->db
                    ->where('role_id', $roleId)
                    ->where('perm_cat_id', $catId)
                    ->get('roles_permissions')
                    ->row_array();
                if (empty($exists)) {
                    $this->CI->db->insert('roles_permissions', array(
                        'role_id'     => $roleId,
                        'perm_cat_id' => $catId,
                        'can_view'    => 1,
                        'can_add'     => 0,
                        'can_edit'    => 1,
                        'can_delete'  => 0,
                    ));
                } else {
                    $this->CI->db->where('id', (int) $exists['id'])->update('roles_permissions', array(
                        'can_view' => 1,
                        'can_edit' => 1,
                    ));
                }
            }
        }

        return true;
    }

    private function ensureWithMysqli(mysqli $mysqli)
    {
        $mysqli->query("CREATE TABLE IF NOT EXISTS `whatsapp_config` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `type` varchar(50) NOT NULL,
            `name` varchar(100) DEFAULT NULL,
            `username` varchar(255) DEFAULT NULL,
            `password` varchar(255) DEFAULT NULL,
            `authkey` varchar(512) DEFAULT NULL,
            `contact` text,
            `language` varchar(20) DEFAULT 'en',
            `is_active` varchar(10) DEFAULT 'disabled',
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `type` (`type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3");

        $res = $mysqli->query("SELECT id, is_active FROM permission_group WHERE short_code = 'whatsapp_messaging' LIMIT 1");
        $row = $res ? $res->fetch_assoc() : null;
        if ($res) {
            $res->free();
        }
        if (!$row) {
            $mysqli->query("INSERT INTO permission_group (name, short_code, is_active, system, sort_order) VALUES ('WhatsApp Messaging', 'whatsapp_messaging', 1, 0, 17.10)");
            $groupId = (int) $mysqli->insert_id;
        } else {
            $groupId = (int) $row['id'];
            if ((int) $row['is_active'] !== 1) {
                $mysqli->query("UPDATE permission_group SET is_active = 1 WHERE id = {$groupId}");
            }
        }

        $res = $mysqli->query("SELECT id FROM permission_category WHERE short_code = 'whatsapp_setting' LIMIT 1");
        $cat = $res ? $res->fetch_assoc() : null;
        if ($res) {
            $res->free();
        }
        if (!$cat) {
            $sysRes = $mysqli->query("SELECT id FROM permission_group WHERE short_code = 'system_settings' LIMIT 1");
            $sys    = $sysRes ? $sysRes->fetch_assoc() : null;
            if ($sysRes) {
                $sysRes->free();
            }
            $permGroupId = !empty($sys['id']) ? (int) $sys['id'] : $groupId;
            $mysqli->query("INSERT INTO permission_category (perm_group_id, name, short_code, enable_view, enable_add, enable_edit, enable_delete) VALUES ({$permGroupId}, 'WhatsApp Setting', 'whatsapp_setting', 1, 0, 1, 0)");
            $catId = (int) $mysqli->insert_id;
        } else {
            $catId = (int) $cat['id'];
        }

        if ($catId > 0) {
            foreach (array(7, 1) as $roleId) {
                $r = $mysqli->query("SELECT id FROM roles WHERE id = {$roleId} LIMIT 1");
                $ok = $r && $r->num_rows > 0;
                if ($r) {
                    $r->free();
                }
                if (!$ok) {
                    continue;
                }
                $e = $mysqli->query("SELECT id FROM roles_permissions WHERE role_id = {$roleId} AND perm_cat_id = {$catId} LIMIT 1");
                $ex = $e ? $e->fetch_assoc() : null;
                if ($e) {
                    $e->free();
                }
                if (!$ex) {
                    $mysqli->query("INSERT INTO roles_permissions (role_id, perm_cat_id, can_view, can_add, can_edit, can_delete) VALUES ({$roleId}, {$catId}, 1, 0, 1, 0)");
                } else {
                    $mysqli->query("UPDATE roles_permissions SET can_view = 1, can_edit = 1 WHERE id = " . (int) $ex['id']);
                }
            }
        }

        return true;
    }
}
