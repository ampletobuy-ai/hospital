<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class SaasValidation
{
    public $CI;
    public $sass_enabled;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->library('media_storage');
        $this->CI->load->library('customlib');
        $this->CI->load->config('saas-config');
        $this->sass_enabled = $this->CI->config->item('saas_enabled');

        if ($this->sass_enabled) {
            $this->CI->load->library('ResourceQuota');
        }
    }

    public function applicationQuotas()
    {
        return $this->sass_enabled ? $this->CI->resourcequota : true;
    }

    public function validateCanAddNewResource($input, $resource_name, $no_of_record)
    {
        if (!$this->sass_enabled) {
            return true;
        }

        $limit = $this->CI->resourcequota->getLimit($resource_name);
        if ($limit === null || $limit <= 0) {
            return true;
        }

        $usage = $this->CI->resourcequota->getUsage($resource_name);
        if (($usage + (int) $no_of_record) > $limit) {
            $this->CI->form_validation->set_message('validateCanAddNewResource', 'Plan limit reached for ' . $resource_name . '. Please upgrade your subscription.');

            return false;
        }

        return true;
    }

    public function getResourceLimit($resource)
    {
        if (!$this->sass_enabled) {
            return true;
        }

        return $this->CI->resourcequota->getLimit($resource);
    }

    public function validateCanUploadFile($input, $storage_array = array())
    {
        if (!$this->sass_enabled) {
            return true;
        }

        $limitKb = $this->CI->resourcequota->getLimit('storage');
        if ($limitKb === null || $limitKb <= 0) {
            return true;
        }

        $additionalKb = 0;
        if (!empty($storage_array) && is_array($storage_array)) {
            foreach ($storage_array as $field) {
                $field = trim((string) $field);
                if ($field === '') {
                    continue;
                }
                if (is_array($_FILES[$field]['name'] ?? null)) {
                    $additionalKb += (int) $this->CI->media_storage->getTmpMultipleFileSize($field);
                } else {
                    $additionalKb += (int) $this->CI->media_storage->getTmpFileSize($field);
                }
            }
        }

        $usageKb = $this->CI->resourcequota->getUsage('storage');
        if (($usageKb + $additionalKb) > $limitKb) {
            $this->CI->form_validation->set_message('validateCanUploadFile', 'Storage limit exceeded. Please upgrade your subscription or remove old files.');

            return false;
        }

        return true;
    }

    public function updateStorageLimit($resource, $storage_array)
    {
        return true;
    }

    public function updateResouceQuota($resource, $resource_usage)
    {
        return true;
    }

    public function deleteResouceQuota($resource, $resource_usage)
    {
        return true;
    }
}
