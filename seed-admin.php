<?php
/**
 * Seeds default Super Admin after database.sql import.
 * Called by reset-local-db.sh
 */

$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'hospital';

$adminEmail    = getenv('HOSPITAL_ADMIN_EMAIL') ?: 'ampletobuy@gmail.com';
$adminPassword = getenv('HOSPITAL_ADMIN_PASSWORD') ?: 'admin123';
$adminName     = 'Super Admin';
$employeeId    = '9001';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    fwrite(STDERR, "DB connection failed: {$conn->connect_error}\n");
    exit(1);
}

$hash = password_hash($adminPassword, PASSWORD_DEFAULT);
$hash = $conn->real_escape_string($hash);
$email = $conn->real_escape_string($adminEmail);
$name = $conn->real_escape_string($adminName);

$conn->query('DELETE FROM staff_roles');
$conn->query('DELETE FROM staff');

$sql = "INSERT INTO staff (
    employee_id, lang_id, specialist, qualification, work_exp, specialization,
    name, surname, father_name, mother_name, contact_no, emergency_contact_no,
    email, marital_status, local_address, permanent_address, note, image,
    password, gender, blood_group, account_title, bank_account_no, bank_name,
    ifsc_code, bank_branch, payscale, basic_salary, epf_no, contract_type,
    shift, location, facebook, twitter, linkedin, instagram, resume,
    joining_letter, resignation_letter, other_document_name, other_document_file,
    user_id, is_active, verification_code, zoom_api_key, zoom_api_secret,
    pan_number, identification_number, local_identification_number
) VALUES (
    '$employeeId', 4, '', '', '', '',
    '$name', '', '', '', '', '',
    '$email', '', '', '', '', '',
    '$hash', '', '', '', '', '',
    '', '', '', '', '', '',
    '', '', '', '', '', '', '',
    '', '', '', '',
    0, 1, '', '', '',
    '', '', ''
)";

if (!$conn->query($sql)) {
    fwrite(STDERR, "Failed to insert admin: {$conn->error}\n");
    exit(1);
}

$staffId = (int) $conn->insert_id;
if (!$conn->query("INSERT INTO staff_roles (role_id, staff_id, is_active) VALUES (7, $staffId, 1)")) {
    fwrite(STDERR, "Failed to insert staff role: {$conn->error}\n");
    exit(1);
}

echo "Super Admin created: $adminEmail / $adminPassword (staff id: $staffId)\n";

$productName = getenv('HOSPITAL_PRODUCT_NAME') ?: 'Qubex Track';
$productName = $conn->real_escape_string($productName);
if (!$conn->query("UPDATE sch_settings SET name = '$productName' WHERE id = 1")) {
    fwrite(STDERR, "Failed to update hospital name: {$conn->error}\n");
    exit(1);
}

echo "Hospital name set to: $productName\n";

// Local install paths — required for staff/patient ID card barcode & QR generation
$folderPath = getenv('HOSPITAL_FOLDER_PATH') ?: (rtrim(str_replace('\\', '/', __DIR__), '/') . '/');
$baseUrl    = getenv('HOSPITAL_BASE_URL') ?: 'http://localhost/hospital/';
$startMonth = getenv('HOSPITAL_START_MONTH') ?: '4';
$folderPathEsc = $conn->real_escape_string($folderPath);
$baseUrlEsc    = $conn->real_escape_string($baseUrl);
$startMonthEsc = $conn->real_escape_string($startMonth);
if (!$conn->query("UPDATE sch_settings SET folder_path = '$folderPathEsc', base_url = '$baseUrlEsc', start_month = '$startMonthEsc' WHERE id = 1")) {
    fwrite(STDERR, "Failed to update sch_settings paths: {$conn->error}\n");
    exit(1);
}
echo "sch_settings folder_path/base_url/start_month set for local install\n";

$uploadDirs = [
    __DIR__ . '/uploads/staff_id_card/barcodes',
    __DIR__ . '/uploads/staff_id_card/qrcode',
    __DIR__ . '/uploads/patient_id_card/barcodes',
    __DIR__ . '/uploads/patient_id_card/qrcode',
];
foreach ($uploadDirs as $dir) {
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        fwrite(STDERR, "Warning: could not create $dir\n");
        continue;
    }
    @chmod($dir, 0777);
}

$masterSql = __DIR__ . '/seed-healthcare-masters.sql';
if (is_file($masterSql)) {
    $sql = file_get_contents($masterSql);
    if ($sql === false || $sql === '') {
        fwrite(STDERR, "Warning: could not read healthcare master SQL\n");
    } else {
        if ($conn->multi_query($sql)) {
            do {
                if ($result = $conn->store_result()) {
                    $result->free();
                }
            } while ($conn->more_results() && $conn->next_result());
            if ($conn->errno) {
                fwrite(STDERR, "Warning: healthcare master seed error: {$conn->error}\n");
            } else {
                echo "Healthcare master data seeded from seed-healthcare-masters.sql\n";
            }
        } else {
            fwrite(STDERR, "Warning: healthcare master seed failed: {$conn->error}\n");
        }
    }
} else {
    echo "Note: seed-healthcare-masters.sql not found — masters not seeded\n";
}

$clinicalSql = __DIR__ . '/seed-clinical-setup.sql';
if (is_file($clinicalSql)) {
    $sql = file_get_contents($clinicalSql);
    if ($sql === false || $sql === '') {
        fwrite(STDERR, "Warning: could not read clinical setup SQL\n");
    } else {
        if ($conn->multi_query($sql)) {
            do {
                if ($result = $conn->store_result()) {
                    $result->free();
                }
            } while ($conn->more_results() && $conn->next_result());
            if ($conn->errno) {
                fwrite(STDERR, "Warning: clinical setup seed error: {$conn->error}\n");
            } else {
                echo "Clinical setup masters seeded from seed-clinical-setup.sql\n";
            }
        } else {
            fwrite(STDERR, "Warning: clinical setup seed failed: {$conn->error}\n");
        }
    }
} else {
    echo "Note: seed-clinical-setup.sql not found — clinical masters not seeded\n";
}

$conn->close();
