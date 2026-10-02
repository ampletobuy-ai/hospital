<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Tenant-scoped upload directories under uploads/tenants/{tenant_id}/...
 */
class Tenant_uploads
{
    /** @var CI_Controller */
    public $CI;

    public function __construct()
    {
        $this->CI =& get_instance();
    }

    /**
     * Current hospital tenant UUID (session / tenancy context).
     */
    public function currentTenantId()
    {
        $tenantId = trim((string) $this->CI->session->userdata('tenant_id'));
        if ($tenantId !== '') {
            return $tenantId;
        }

        if (isset($this->CI->tenant_context) && is_object($this->CI->tenant_context)) {
            $fromContext = trim((string) $this->CI->tenant_context->getTenantId());
            if ($fromContext !== '') {
                return $fromContext;
            }
        }

        $admin = $this->CI->session->userdata('hospitaladmin');
        if (is_array($admin) && !empty($admin['saas_key'])) {
            return trim((string) $admin['saas_key']);
        }

        return '';
    }

    /**
     * Relative web path (no leading slash), e.g. uploads/tenants/{id}/printing
     * Falls back to shared uploads/{subdir} when tenancy is off / no tenant.
     */
    public function relativeDir($subdir = 'printing')
    {
        $subdir = trim((string) $subdir, '/');
        $tenantId = $this->currentTenantId();
        $this->CI->load->config('tenancy-config');

        if ($tenantId !== '' && $this->CI->config->item('tenancy_enabled')) {
            return 'uploads/tenants/' . $tenantId . '/' . $subdir;
        }

        return 'uploads/' . $subdir;
    }

    /**
     * Absolute filesystem directory (trailing slash).
     */
    public function absoluteDir($subdir = 'printing')
    {
        $rel = $this->relativeDir($subdir);

        return rtrim(FCPATH, '/\\') . '/' . $rel . '/';
    }

    /**
     * Ensure directory exists; return relative dir (no trailing slash).
     */
    public function ensureDir($subdir = 'printing')
    {
        $abs = $this->absoluteDir($subdir);
        if (!is_dir($abs)) {
            @mkdir($abs, 0777, true);
        }
        @chmod($abs, 0777);
        // Prevent directory listing
        $index = $abs . 'index.html';
        if (!is_file($index)) {
            @file_put_contents($index, '');
        }

        return $this->relativeDir($subdir);
    }

    /**
     * Upload path argument for Media_storage::fileupload (trailing slash).
     */
    public function mediaUploadPath($subdir = 'printing')
    {
        return $this->ensureDir($subdir) . '/';
    }

    /**
     * Stored DB path for a newly uploaded file.
     */
    public function storedPath($subdir, $fileName)
    {
        return $this->relativeDir($subdir) . '/' . ltrim((string) $fileName, '/');
    }

    /**
     * Delete a stored relative file path if it belongs to the current tenant
     * (or shared uploads/printing when not multi-tenant).
     */
    public function deleteStoredFile($relativePath)
    {
        $relativePath = ltrim(str_replace('\\', '/', (string) $relativePath), './');
        if ($relativePath === '' || strpos($relativePath, '..') !== false) {
            return false;
        }

        $tenantId = $this->currentTenantId();
        $allowedPrefixes = array('uploads/printing/');
        if ($tenantId !== '') {
            $allowedPrefixes[] = 'uploads/tenants/' . $tenantId . '/';
        }

        $ok = false;
        foreach ($allowedPrefixes as $prefix) {
            if (strpos($relativePath, $prefix) === 0) {
                $ok = true;
                break;
            }
        }
        // Never delete another tenant's file.
        if (strpos($relativePath, 'uploads/tenants/') === 0 && $tenantId !== '') {
            if (strpos($relativePath, 'uploads/tenants/' . $tenantId . '/') !== 0) {
                return false;
            }
        }
        if (!$ok && strpos($relativePath, 'uploads/tenants/') === 0) {
            return false;
        }
        if (!$ok && strpos($relativePath, 'uploads/printing/') !== 0) {
            return false;
        }

        $full = rtrim(FCPATH, '/\\') . '/' . $relativePath;
        if (is_file($full)) {
            return @unlink($full);
        }

        return false;
    }

    /**
     * Create standard tenant upload tree during provision.
     */
    public function provisionTenantDirs($tenantId)
    {
        $tenantId = trim((string) $tenantId);
        if ($tenantId === '') {
            return;
        }

        $base = rtrim(FCPATH, '/\\') . '/uploads/tenants/' . $tenantId;
        $subs = array(
            'printing',
            'staff_images',
            'staff_documents',
            'patient_images',
            'patient_timeline',
            'hospital_content/logo',
            'payment_document',
            'pathology_report',
            'radiology_report',
        );
        foreach ($subs as $sub) {
            $dir = $base . '/' . $sub;
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }
            @chmod($dir, 0777);
            $index = $dir . '/index.html';
            if (!is_file($index)) {
                @file_put_contents($index, '');
            }
        }
    }
}
