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

        $settingsPayload = array(
            'name' => $hospitalName,
            'base_url' => $baseUrl,
            'folder_path' => $folderPath,
            'saas_key' => $tenantId,
            'image' => 'qubex_track_logo.png',
            'mini_logo' => 'qubex_track_app.png',
            'app_logo' => 'qubex_track_app.png',
        );

        $existingSettings = $db->get_where('sch_settings', array('id' => 1), 1)->row_array();
        if ($existingSettings) {
            $db->where('id', 1);
            $db->update('sch_settings', $settingsPayload);
        } else {
            $seed = $this->seedSettingsFromTemplate($settingsPayload);
            $db->insert('sch_settings', $seed);
        }

        // Product branding defaults for new tenants (Qubex Track).
        if ($db->table_exists('front_cms_settings')) {
            $front = $db->get_where('front_cms_settings', array('id' => 1), 1)->row_array();
            $frontPayload = array(
                'logo' => './uploads/hospital_content/logo/qubex_track_logo.png',
                'fav_icon' => './uploads/hospital_content/logo/qubex_track_favicon.png',
            );
            if ($front) {
                $db->where('id', 1);
                $db->update('front_cms_settings', $frontPayload);
            }
        }

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

        // Tenant-isolated upload tree (print headers, logos, documents, …).
        $this->CI =& get_instance();
        $this->CI->load->library('tenant_uploads');
        $this->CI->tenant_uploads->provisionTenantDirs($tenantId);

        // Clear demo print headers copied from template so tenants start clean.
        if ($db->table_exists('print_setting')) {
            $db->update('print_setting', array('print_header' => ''));
        }
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function seedSettingsFromTemplate(array $overrides)
    {
        $seed = array(
            'id' => 1,
            'name' => 'Qubex Track Hospital',
            'start_month' => 'April',
            'lang_id' => 4,
            'languages' => '["4"]',
            'date_format' => 'd/m/Y',
            'time_format' => '24-hour',
            'currency' => 'INR',
            'currency_symbol' => '₹',
            'is_rtl' => 'disabled',
            'timezone' => 'Asia/Kolkata',
            'theme' => 'default.jpg',
            'cron_secret_key' => md5(uniqid((string) mt_rand(), true)),
            'doctor_restriction' => 'disabled',
            'superadmin_restriction' => 'disabled',
            'patient_panel' => 'enabled',
            'patient_delete_account' => 'disabled',
            'scan_code_type' => 'barcode',
            'message_mode' => 0,
            'message_queue_attempts' => 3,
            'message_queue_batch_size' => 50,
            'mobile_api_url' => '',
            'app_primary_color_code' => '#2b7568',
            'app_secondary_color_code' => '#d49176',
            'app_logo' => 'qubex_track_app.png',
            'mini_logo' => 'qubex_track_app.png',
            'image' => 'qubex_track_logo.png',
            'notification_poll_interval' => 60,
            'zoom_api_key' => '',
            'zoom_api_secret' => '',
            'biometric' => 0,
        );

        try {
            $this->CI =& get_instance();
            $template = hospital_env('TENANT_TEMPLATE_DATABASE', hospital_env('DB_DATABASE', 'hospital'));
            $mysqli = @new mysqli(
                hospital_env('DB_HOST', 'localhost'),
                hospital_env('DB_USERNAME', 'root'),
                hospital_env('DB_PASSWORD', ''),
                $template,
                (int) hospital_env('DB_PORT', '3306')
            );
            if (!$mysqli->connect_errno) {
                $result = $mysqli->query('SELECT * FROM sch_settings WHERE id = 1 LIMIT 1');
                if ($result && ($row = $result->fetch_assoc())) {
                    unset($row['created_at'], $row['updated_at']);
                    $seed = array_merge($seed, $row);
                }
                $mysqli->close();
            }
        } catch (Throwable $e) {
            // Keep hardcoded defaults.
        }

        return array_merge($seed, $overrides);
    }
}
