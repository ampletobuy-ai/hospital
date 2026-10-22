<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * WhatsApp Messaging module: config table + permission rows.
 */
class Migration_Add_whatsapp_messaging extends CI_Migration
{
    public function up()
    {
        if (!$this->db->table_exists('whatsapp_config')) {
            $this->dbforge->add_field(array(
                'id' => array(
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ),
                'type' => array(
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'null'       => false,
                ),
                'name' => array(
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => true,
                ),
                'username' => array(
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                ),
                'password' => array(
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                ),
                'authkey' => array(
                    'type'       => 'VARCHAR',
                    'constraint' => 512,
                    'null'       => true,
                ),
                'contact' => array(
                    'type' => 'TEXT',
                    'null' => true,
                ),
                'language' => array(
                    'type'       => 'VARCHAR',
                    'constraint' => 20,
                    'null'       => true,
                    'default'    => 'en',
                ),
                'is_active' => array(
                    'type'       => 'VARCHAR',
                    'constraint' => 10,
                    'null'       => true,
                    'default'    => 'disabled',
                ),
                'created_at' => array(
                    'type' => 'TIMESTAMP',
                    'null' => true,
                ),
                'updated_at' => array(
                    'type' => 'TIMESTAMP',
                    'null' => true,
                ),
            ));
            $this->dbforge->add_key('id', true);
            $this->dbforge->add_key('type');
            $this->dbforge->create_table('whatsapp_config', true);
        }

        $this->ensureModuleAndPermissions();
    }

    public function down()
    {
        // Keep table/permissions — downgrade would break configured tenants.
    }

    private function ensureModuleAndPermissions()
    {
        $group = $this->db->where('short_code', 'whatsapp_messaging')->get('permission_group')->row_array();
        if (empty($group)) {
            $this->db->insert('permission_group', array(
                'name'       => 'WhatsApp Messaging',
                'short_code' => 'whatsapp_messaging',
                'is_active'  => 1,
                'system'     => 0,
                'sort_order' => 17.10,
            ));
            $groupId = (int) $this->db->insert_id();
        } else {
            $groupId = (int) $group['id'];
            $this->db->where('id', $groupId)->update('permission_group', array('is_active' => 1));
        }

        if ($groupId <= 0) {
            return;
        }

        $cat = $this->db->where('short_code', 'whatsapp_setting')->get('permission_category')->row_array();
        if (empty($cat)) {
            // Prefer system_settings group for Settings sidebar consistency.
            $sys = $this->db->where('short_code', 'system_settings')->get('permission_group')->row_array();
            $permGroupId = !empty($sys['id']) ? (int) $sys['id'] : $groupId;
            $this->db->insert('permission_category', array(
                'perm_group_id' => $permGroupId,
                'name'          => 'WhatsApp Setting',
                'short_code'    => 'whatsapp_setting',
                'enable_view'   => 1,
                'enable_add'    => 0,
                'enable_edit'   => 1,
                'enable_delete' => 0,
            ));
            $catId = (int) $this->db->insert_id();
        } else {
            $catId = (int) $cat['id'];
        }

        if ($catId <= 0 || !$this->db->table_exists('roles_permissions')) {
            return;
        }

        // Grant Super Admin (role_id 7) and Administrator (1) when present.
        foreach (array(7, 1) as $roleId) {
            $role = $this->db->where('id', $roleId)->get('roles')->row_array();
            if (empty($role)) {
                continue;
            }
            $existing = $this->db
                ->where('role_id', $roleId)
                ->where('perm_cat_id', $catId)
                ->get('roles_permissions')
                ->row_array();
            if (empty($existing)) {
                $this->db->insert('roles_permissions', array(
                    'role_id'     => $roleId,
                    'perm_cat_id' => $catId,
                    'can_view'    => 1,
                    'can_add'     => 0,
                    'can_edit'    => 1,
                    'can_delete'  => 0,
                ));
            } else {
                $this->db->where('id', $existing['id'])->update('roles_permissions', array(
                    'can_view' => 1,
                    'can_edit' => 1,
                ));
            }
        }
    }
}
