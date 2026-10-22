<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Whatsappconfig_model extends MY_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->ensureTable();
    }

    /**
     * Create whatsapp_config if missing (SaaS tenants provisioned before this feature).
     */
    public function ensureTable()
    {
        if ($this->db->table_exists('whatsapp_config')) {
            return;
        }

        $this->db->query("CREATE TABLE IF NOT EXISTS `whatsapp_config` (
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
    }

    public function get($id = null)
    {
        $this->db->select()->from('whatsapp_config');
        if ($id != null) {
            $this->db->where('id', $id);
        } else {
            $this->db->order_by('id');
        }
        $query = $this->db->get();
        if ($id != null) {
            return $query->row_array();
        }

        return $query->result();
    }

    /**
     * @param string $type
     * @return array<string, mixed>
     */
    public function getByType($type)
    {
        $row = $this->db->where('type', $type)->get('whatsapp_config')->row_array();

        return is_array($row) ? $row : array();
    }

    public function changeStatus($type)
    {
        $data = array('is_active' => 'disabled');
        $this->db->where('type !=', $type);
        $this->db->update('whatsapp_config', $data);
    }

    public function add($data)
    {
        $this->db->trans_start();
        $this->db->trans_strict(false);

        $this->db->where('type', $data['type']);
        $q = $this->db->get('whatsapp_config');
        if ($q->num_rows() > 0) {
            $this->db->where('type', $data['type']);
            $this->db->update('whatsapp_config', $data);
            $message   = UPDATE_RECORD_CONSTANT . ' On WhatsApp Config type ' . $data['type'];
            $action    = 'Update';
            $record_id = $data['type'];
            $this->log($message, $record_id, $action);
        } else {
            $this->db->insert('whatsapp_config', $data);
            $insert_id = $this->db->insert_id();
            $message   = INSERT_RECORD_CONSTANT . ' On WhatsApp Config id ' . $insert_id;
            $action    = 'Insert';
            $record_id = $insert_id;
            $this->log($message, $record_id, $action);
        }

        if (isset($data['is_active']) && $data['is_active'] === 'enabled') {
            $this->changeStatus($data['type']);
        }

        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return false;
        }

        return $record_id;
    }

    /**
     * Active provider row for Whatsappgateway.
     *
     * @return object|null
     */
    public function getActiveWhatsApp()
    {
        $this->db->select()->from('whatsapp_config');
        $this->db->where('is_active', 'enabled');
        $query = $this->db->get();

        return $query->row();
    }
}
