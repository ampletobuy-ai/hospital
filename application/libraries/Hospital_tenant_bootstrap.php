<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Hospital_tenant_bootstrap
{
    /**
     * @param CI_DB_query_builder $db
     * @param array<string, mixed> $payload
     */
    public function run($db, $tenantId, array $payload = array())
    {
        $hospitalName = trim((string) ($payload['hospital_name'] ?? $payload['property_name'] ?? $payload['store_name'] ?? 'Qubex Track Hospital'));
        $adminEmail = strtolower(trim((string) ($payload['portal_admin_email'] ?? '')));
        $plainPassword = trim((string) ($payload['password_plain'] ?? ''));

        if ($plainPassword === '' && !empty($payload['password_encrypted'])) {
            $this->CI =& get_instance();
            $this->CI->load->library('portal_signup_crypt');
            $plainPassword = (string) Portal_signup_crypt::decrypt($payload['password_encrypted']);
        }

        $folderPath = rtrim(str_replace('\\', '/', FCPATH), '/') . '/';
        $baseUrl = hospital_env('HOSPITAL_BASE_URL', 'http://localhost/hospital/');

        $db->where('id', 1);
        $db->update('sch_settings', array(
            'name' => $hospitalName,
            'base_url' => $baseUrl,
            'folder_path' => $folderPath,
            'saas_key' => $tenantId,
        ));

        $db->query('DELETE FROM staff_roles');
        $db->query('DELETE FROM staff');

        if ($adminEmail === '') {
            $adminEmail = hospital_env('HOSPITAL_ADMIN_EMAIL', 'admin@example.com');
        }
        if ($plainPassword === '') {
            $plainPassword = hospital_env('HOSPITAL_ADMIN_PASSWORD', 'admin123');
        }

        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
        $staffData = array(
            'employee_id' => '9001',
            'lang_id' => 4,
            'name' => 'Super Admin',
            'email' => $adminEmail,
            'password' => $hash,
            'is_active' => 1,
            'user_id' => 0,
        );
        $db->insert('staff', $staffData);
        $staffId = (int) $db->insert_id();
        if ($staffId > 0) {
            $db->insert('staff_roles', array(
                'role_id' => 7,
                'staff_id' => $staffId,
                'is_active' => 1,
            ));
        }

        $uploadDirs = array(
            FCPATH . 'uploads/staff_id_card/barcodes',
            FCPATH . 'uploads/staff_id_card/qrcode',
            FCPATH . 'uploads/patient_id_card/barcodes',
            FCPATH . 'uploads/patient_id_card/qrcode',
            FCPATH . 'uploads/staff_images',
            FCPATH . 'uploads/staff_documents',
            FCPATH . 'uploads/patient_images',
        );
        foreach ($uploadDirs as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }
            @chmod($dir, 0777);
        }
    }
}
