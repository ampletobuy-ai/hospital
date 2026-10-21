<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Tenant-scoped upload directories under uploads/tenants/{tenant_id}/...
 *
 * When tenancy is off / no tenant session, paths fall back to shared uploads/{subdir}.
 */
class Tenant_uploads
{
    /** @var CI_Controller */
    public $CI;

    /** Shared product assets that must never be remapped to a tenant folder. */
    private $sharedAssetBasenames = array(
        'no_image.png',
        'pregnant.png',
        'ultrasound-machine.png',
        'qubex_track_logo.png',
        'qubex_track_favicon.png',
    );

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
     * Whether tenant-scoped uploads are active for this request.
     */
    public function isScoped()
    {
        $this->CI->load->config('tenancy-config');
        if (!$this->CI->config->item('tenancy_enabled')) {
            return false;
        }

        return $this->currentTenantId() !== '';
    }

    /**
     * Relative web path (no leading slash), e.g. uploads/tenants/{id}/printing
     * Falls back to shared uploads/{subdir} when tenancy is off / no tenant.
     */
    public function relativeDir($subdir = 'printing')
    {
        $subdir = trim((string) $subdir, '/');
        if ($this->isScoped()) {
            return 'uploads/tenants/' . $this->currentTenantId() . '/' . $subdir;
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
     * Normalize a relative path (strip ./ and leading slashes).
     */
    public function normalizeRelative($path)
    {
        $path = ltrim(str_replace('\\', '/', (string) $path), '/');
        if (strpos($path, './') === 0) {
            $path = substr($path, 2);
        }

        return $path;
    }

    /**
     * Map a shared uploads/... relative path to the current tenant equivalent.
     * Returns '' when scoping is off or path is not under uploads/.
     */
    public function tenantMirrorRelative($relative)
    {
        $relative = $this->normalizeRelative($relative);
        if ($relative === '' || !$this->isScoped()) {
            return '';
        }
        if (strpos($relative, 'uploads/tenants/') === 0) {
            return $relative;
        }
        if (strpos($relative, 'uploads/') !== 0) {
            return '';
        }

        $suffix = substr($relative, strlen('uploads/'));
        $base = basename($relative);
        if (in_array($base, $this->sharedAssetBasenames, true)) {
            return '';
        }

        return 'uploads/tenants/' . $this->currentTenantId() . '/' . $suffix;
    }

    /**
     * Resolve which relative path to use for reading/serving a file.
     * Prefers tenant copy when it exists; falls back to shared/legacy path.
     *
     * @param string $relativeOrDir full relative path OR directory (uploads/staff_images)
     * @param string|null $fileName bare filename when $relativeOrDir is a directory
     */
    public function resolveReadableRelative($relativeOrDir, $fileName = null)
    {
        if ($fileName !== null && $fileName !== '') {
            $fileName = $this->normalizeRelative($fileName);
            if (strpos($fileName, 'uploads/') === 0) {
                $relative = $fileName;
            } else {
                $dir = rtrim($this->normalizeRelative($relativeOrDir), '/');
                $relative = $dir . '/' . $fileName;
            }
        } else {
            $relative = $this->normalizeRelative($relativeOrDir);
        }

        if ($relative === '') {
            return '';
        }

        $base = basename($relative);
        if (in_array($base, $this->sharedAssetBasenames, true)) {
            return $relative;
        }

        $tenantRel = $this->tenantMirrorRelative($relative);
        if ($tenantRel !== '' && is_file(rtrim(FCPATH, '/\\') . '/' . $tenantRel)) {
            return $tenantRel;
        }

        return $relative;
    }

    /**
     * Absolute paths to try for size/delete (tenant first, then shared).
     *
     * @return string[]
     */
    public function candidateAbsolutePaths($fileName, $filePath = '')
    {
        $fileName = $this->normalizeRelative($fileName);
        $candidates = array();
        $root = rtrim(FCPATH, '/\\') . '/';

        if ($filePath === '' || $filePath === null) {
            if ($fileName === '') {
                return array();
            }
            $candidates[] = $root . $fileName;
            $tenantRel = $this->tenantMirrorRelative($fileName);
            if ($tenantRel !== '') {
                array_unshift($candidates, $root . $tenantRel);
            }
        } else {
            $dir = rtrim($this->normalizeRelative($filePath), '/');
            $candidates[] = $root . $dir . '/' . $fileName;
            $tenantDir = $this->tenantMirrorRelative($dir);
            if ($tenantDir !== '') {
                array_unshift($candidates, $root . $tenantDir . '/' . $fileName);
            }
            // Bare filename stored with logical shared dir, but file may only be under tenant.
            if ($this->isScoped() && strpos($dir, 'uploads/') === 0) {
                $suffix = substr($dir, strlen('uploads/'));
                array_unshift($candidates, $root . 'uploads/tenants/' . $this->currentTenantId() . '/' . $suffix . '/' . $fileName);
            }
        }

        return array_values(array_unique($candidates));
    }

    /**
     * Delete a stored relative file path if it belongs to the current tenant
     * (or shared uploads/printing when not multi-tenant).
     */
    public function deleteStoredFile($relativePath)
    {
        $relativePath = $this->normalizeRelative($relativePath);
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
            'patient_id_card/barcodes',
            'patient_id_card/qrcode',
            'staff_id_card/barcodes',
            'staff_id_card/qrcode',
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
            $index = rtrim($dir, '/\\') . '/index.html';
            if (!is_file($index)) {
                @file_put_contents($index, '');
            }
        }
    }
}
