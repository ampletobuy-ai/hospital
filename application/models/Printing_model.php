<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Printing_model extends MY_Model
{
    private $visibility_filtering = true;
    private $hidden_header_placeholder = 'backend/images/print-header-placeholder.svg';

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

        if (isset($record['show_header']) && !(int) $record['show_header']) {
            $record['print_header'] = $this->hidden_header_placeholder;
        }

        if (isset($record['show_footer']) && !(int) $record['show_footer']) {
            $record['print_footer'] = '';
        }

        return $record;
    }

    public function add($data)
    {
        $this->db->trans_start(); # Starting Transaction
        $this->db->trans_strict(false); # See Note 01. If you wish can remove as well
        //=======================Code Start===========================
        if (isset($data["id"])) {
            $this->db->where("id", $data["id"])->update("print_setting", $data);            
            $message = UPDATE_RECORD_CONSTANT . " On Print Setting id " . $data['id'];
            $action = "Update";
            $record_id = $data['id'];
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
        if (!empty($id)) {
            $query = $this->db->where("id", $id)->get("print_setting");
            return $this->applyVisibility($query->row_array());
        } else {
            $query = $this->db->where("setting_for", $setting_for)->get("print_setting");
            return array_map(array($this, 'applyVisibility'), $query->result_array());
        }
    }

    public function getheaderfooter($setting_for)
    {        
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
