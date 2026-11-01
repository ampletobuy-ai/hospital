<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Printing_model extends MY_Model
{
    private $visibility_filtering = true;
    private static $blank_line_schema_ready = false;

    /**
     * The settings screen must display saved content even when it is hidden
     * from printed documents.
     */
    public function setVisibilityFiltering($enabled)
    {
        $this->visibility_filtering = (bool) $enabled;
        return $this;
    }

    private function applyVisibility($record)
    {
        if (!$this->visibility_filtering || empty($record)) {
            return $record;
        }

        // Clear content so print templates' !empty(print_header/print_footer)
        // checks hide the blocks. (A transparent placeholder still rendered an
        // <img>, so "hide header" appeared not to work.)
        if (isset($record['show_header']) && !(int) $record['show_header']) {
            $record['print_header'] = '';
        }

        if (isset($record['show_footer']) && !(int) $record['show_footer']) {
            $record['print_footer'] = '';
        }

        return $record;
    }

    public function add($data)
    {
        $this->ensureBlankLineColumns();
        $this->db->trans_start(); # Starting Transaction
        $this->db->trans_strict(false); # See Note 01. If you wish can remove as well
        //=======================Code Start===========================
        if (isset($data["id"]) && $data["id"] !== '' && $data["id"] !== null) {
            $record_id = $data['id'];
            unset($data['id']); // do not SET primary key in UPDATE
            $this->db->where("id", $record_id)->update("print_setting", $data);
            $message = UPDATE_RECORD_CONSTANT . " On Print Setting id " . $record_id;
            $action = "Update";
            $this->log($message, $record_id, $action);
        } else {
            $this->db->insert("print_setting", $data);
            $insert_id = $this->db->insert_id();
            $message = INSERT_RECORD_CONSTANT . " On Print Setting id " . $insert_id;
            $action = "Insert";
            $record_id = $insert_id;
            $this->log($message, $record_id, $action);
        }
        //======================Code End==============================

        $this->db->trans_complete(); # Completing transaction
        /* Optional */

        if ($this->db->trans_status() === false) {
            # Something went wrong.
            $this->db->trans_rollback();
            return false;
        } else {
            return $record_id;
        }
    }

    public function get($id = '', $setting_for = '')
    {
        $this->ensureBlankLineColumns();

        if (!empty($id)) {
            $query = $this->db->where("id", $id)->get("print_setting");
            return $this->applyVisibility($query->row_array());
        } else {
            $query = $this->db->where("setting_for", $setting_for)->get("print_setting");
            return array_map(array($this, 'applyVisibility'), $query->result_array());
        }
    }

    /**
     * Production tenant DBs provisioned before migrations 125/126 may lack
     * blank-line / visibility columns. Ensure them on read so OPD bill print
     * can honor Setup → Print Header Footer spacing settings.
     */
    private function ensureBlankLineColumns()
    {
        if (self::$blank_line_schema_ready) {
            return;
        }
        self::$blank_line_schema_ready = true;

        if (!$this->db->table_exists('print_setting')) {
            return;
        }
        if (!$this->db->field_exists('show_header', 'print_setting')) {
            $this->db->query("ALTER TABLE `print_setting` ADD COLUMN `show_header` TINYINT(1) NOT NULL DEFAULT 1 AFTER `print_footer`");
        }
        if (!$this->db->field_exists('show_footer', 'print_setting')) {
            $this->db->query("ALTER TABLE `print_setting` ADD COLUMN `show_footer` TINYINT(1) NOT NULL DEFAULT 1 AFTER `show_header`");
        }
        if (!$this->db->field_exists('blank_lines_before', 'print_setting')) {
            $this->db->query("ALTER TABLE `print_setting` ADD COLUMN `blank_lines_before` TINYINT(3) UNSIGNED NOT NULL DEFAULT 0 AFTER `show_footer`");
        }
        if (!$this->db->field_exists('blank_lines_after_header', 'print_setting')) {
            $this->db->query("ALTER TABLE `print_setting` ADD COLUMN `blank_lines_after_header` TINYINT(3) UNSIGNED NOT NULL DEFAULT 0 AFTER `blank_lines_before`");
        }
    }

    public function getheaderfooter($setting_for)
    {
        $this->ensureBlankLineColumns();
        $query = $this->db->where("setting_for", $setting_for)->get("print_setting");
        return $this->applyVisibility($query->row_array());
    }

    public function delete($id)
    {
        $this->db->trans_start(); # Starting Transaction
        $this->db->trans_strict(false); # See Note 01. If you wish can remove as well
        //=======================Code Start===========================
        $this->db->where("id", $id)->delete('print_setting');
        
        $message = DELETE_RECORD_CONSTANT . " On Print Setting id " . $id;
        $action = "Delete";
        $record_id = $id;
        $this->log($message, $record_id, $action);
        //======================Code End==============================

        $this->db->trans_complete(); # Completing transaction
        /* Optional */

        if ($this->db->trans_status() === false) {
            # Something went wrong.
            $this->db->trans_rollback();
            return false;
        } else {
            return $record_id;
        }
    }
	
}
