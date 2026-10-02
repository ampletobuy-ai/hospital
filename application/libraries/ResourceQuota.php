<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class ResourceQuota
{
    /** @var CI_Controller */
    public $CI;

    /** @var array<string, int|null> */
    private $limits = array();

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->library('subscription_resolver');
        $this->CI->load->config('tenancy-config');
        $this->loadLimits();
    }

    private function loadLimits()
    {
        if (!$this->CI->config->item('tenancy_enabled')) {
            return;
        }

        $tenantId = (string) $this->CI->session->userdata('tenant_id');
        if ($tenantId === '') {
            return;
        }

        $this->limits = $this->CI->subscription_resolver->quotaLimits($tenantId);
    }

    public function getLimit($resource)
    {
        $resource = (string) $resource;
        if ($resource === 'storage') {
            if (isset($this->limits['storage'])) {
                return (int) $this->limits['storage'];
            }
            if (isset($this->limits['storage_mb'])) {
                return (int) $this->limits['storage_mb'] * 1024;
            }

            return null;
        }

        if (isset($this->limits[$resource])) {
            return (int) $this->limits[$resource];
        }

        if ($resource === 'no_of_staff' && isset($this->limits['max_users'])) {
            return (int) $this->limits['max_users'];
        }

        return null;
    }

    public function getUsage($resource)
    {
        $resource = (string) $resource;

        if ($resource === 'storage') {
            return $this->storageUsageKb();
        }

        $tableMap = array(
            'no_of_staff' => array('staff', 'is_active = 1'),
            'no_of_patient' => array('patients', '1=1'),
            'no_of_opd' => array('opd_details', '1=1'),
            'no_of_ipd' => array('ipd_details', '1=1'),
            'no_of_appointment' => array('appointment', '1=1'),
            'no_of_pharmacy' => array('pharmacy_bill_basic', '1=1'),
            'no_of_pathology' => array('pathology_report', '1=1'),
            'no_of_radiology' => array('radiology_report', '1=1'),
            'no_of_blood_bank' => array('blood_issue', '1=1'),
            'no_of_ambulance' => array('ambulance_call', '1=1'),
        );

        if (!isset($tableMap[$resource])) {
            return 0;
        }

        list($table, $where) = $tableMap[$resource];
        if (!$this->CI->db->table_exists($table)) {
            return 0;
        }

        $query = $this->CI->db->query('SELECT COUNT(*) AS total FROM `' . $this->CI->db->escape_str($table) . '` WHERE ' . $where);
        $row = $query ? $query->row_array() : null;

        return (int) ($row['total'] ?? 0);
    }

    private function storageUsageKb()
    {
        $this->CI->load->library('media_storage');
        $uploadRoot = rtrim(FCPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads';
        if (!is_dir($uploadRoot)) {
            return 0;
        }

        $bytes = $this->directorySizeBytes($uploadRoot);

        return (int) ceil($bytes / 1024);
    }

    private function directorySizeBytes($path)
    {
        $size = 0;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += (int) $file->getSize();
            }
        }

        return $size;
    }
}
