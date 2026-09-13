<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Start extends CI_Controller {

    private $error = '';

    function __construct() {
        parent::__construct();
        $this->load->library('Enc_lib');
    }

    public function index() {
        // config.php's base_url is still blank at this point (it only gets written
        // once install finishes, see update_config_installed()). Until then, CI falls
        // back to auto-detecting the host from $_SERVER['SERVER_ADDR'], which on
        // dual-stack (IPv4/IPv6) servers can resolve to a different address than the
        // one the browser actually used (e.g. '::1' vs 'localhost'). That mismatch
        // makes the browser treat installer assets as cross-origin, and @font-face
        // fonts get blocked by CORS, so icons fail to render. Override it for this
        // request using the Host header the browser actually sent.
        $proto    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $app_root = preg_replace('#/install/start.*$#', '/', $_SERVER['REQUEST_URI']);
        $this->config->set_item('base_url', $proto . '://' . $_SERVER['HTTP_HOST'] . $app_root);

        $config_path = APPPATH . 'config/config.php';
        $debug = '';
        $step = 1;
        $passed_steps = array(
            1 => false,
            2 => false,
            3 => false,
        );
        if ($this->input->post()) {
            if ($this->input->post('step') && $this->input->post('step') == 2) {
                if ($this->input->post('hostname') == '') {
                    $this->error = 'Hostname is required';
                } else if ($this->input->post('database') == '') {
                    $this->error = 'Enter database name';
                } else if ($this->input->post('password') == '' && strpos(site_url(), 'localhost') === false && strpos(site_url(), '[::1]') === false) {
                    $this->error = 'Enter database password';
                } else if ($this->input->post('username') == '') {
                    $this->error = 'Enter database username';
                }
                $step = 2;
                $passed_steps[1] = true;
                if ($this->error === '') {
                    $passed_steps[2] = true;
                    $link = @mysqli_connect($this->input->post('hostname'), $this->input->post('username'), $this->input->post('password'), $this->input->post('database'));
                    if (!$link) {
                        $this->error .= "Error: Unable to connect to MySQL Database." . PHP_EOL;
                    } else {
                        $debug .= "Success: Connection to " . $this->input->post('database') . " database is done successfully.";
                        if ($this->write_db_config()) {
                            $step = 3;
                        }
                        mysqli_close($link);
                    }
                }
            } else if ($this->input->post('step') && $this->input->post('step') == 3) {
                if ($this->input->post('admin_email') == '') {
                    $this->error = 'Enter admin email address';
                } else if (filter_var($this->input->post('admin_email'), FILTER_VALIDATE_EMAIL) === false) {
                    $this->error = 'Enter valid email address';
                } else if ($this->input->post('admin_password') == '') {
                    $this->error = 'Enter admin password';
                } else if ($this->input->post('admin_password') != $this->input->post('admin_passwordr')) {
                    $this->error = 'Your confirm password not match';
                }
                $passed_steps[1] = true;
                $passed_steps[2] = true;
                $step = 3;
            } else if ($this->input->post('requirements_success')) {
                $step = 2;
                $passed_steps[1] = true;
            }
            if ($this->error === '' && $this->input->post('step') && $this->input->post('step') == 3) {
                $file_path = APPPATH . 'controllers/install/database.sql';
                $this->load->database();

                $this->db->query('SET FOREIGN_KEY_CHECKS=0');
                $this->db->trans_start();


                try {
                    $lines = file($file_path);
                    $query = '';
                    
                    foreach ($lines as $line) {
                        // Skip comments and empty lines
                        if (substr(trim($line), 0, 2) == '--' || trim($line) == '') {
                            continue;
                        }
                        
                        $query .= $line;
                        
                        // If it has a semicolon at the end, it's a complete query
                        if (substr(trim($line), -1, 1) == ';') {
                            // Execute the query
                            if (!$this->db->query($query)) {
                                throw new Exception('Query failed: '.$this->db->error()['message']);
                            }
                            $query = '';
                        }
                    }

                    $this->clean_up_db_query();
                    $data = array(
												
                        'name' => 'Super Admin',
                        'employee_id' => 9001,
										   
                        'email' => $this->input->post('admin_email'),
                        'password' => $this->enc_lib->passHashEnc($this->input->post('admin_password')),
                        'is_active' => 1
                    );

                    $this->db->insert('staff', $data);
                    $insert_id = $this->db->insert_id();

                    $role_data = array(
                        'staff_id' => $insert_id,
                        'role_id' => 7
                    );

                    if ($this->db->insert('staff_roles', $role_data)) {

                        if (!is_really_writable($config_path)) {
                            show_error($config_path . ' should be writable. Database imported successfully. And admin user added successfully. You can set manually in application/config at bottom $config["installed"]  = "true"');
                        }
                        update_config_installed();
                        update_autoload_installed();
                        $passed_steps[1] = true;
                        $passed_steps[2] = true;
                        $passed_steps[3] = true;
                        $step = 4;
                    }
                    $this->db->trans_complete();
                    $this->db->query('SET FOREIGN_KEY_CHECKS=1');
                    
            
                } catch (Exception $e) {
                    $this->db->trans_rollback();
                    $this->db->query('SET FOREIGN_KEY_CHECKS=1');
                    log_message('error', $e->getMessage());
                    $this->error = 'Installation failed: ' . $e->getMessage() . '. Please fix the issue and try again.';
                    $error = $this->error;
                    $step = 3;
                }

            } else {
                $error = $this->error;
            }
        }
        include_once(APPPATH . 'controllers/install/html.php');
    }

    public function delete_install_dir() {
        $install_dir = APPPATH . 'controllers/install';
        if (is_dir($install_dir)) {
            $this->_delete_dir_recursive($install_dir);
        }
        $proto    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $app_root = preg_replace('#/install/start.*$#', '/', $_SERVER['REQUEST_URI']);
        header('Location: ' . $proto . '://' . $_SERVER['HTTP_HOST'] . $app_root . 'site/login');
        exit();
    }

    private function _delete_dir_recursive($dir) {
        $items = glob($dir . '/*');
        if ($items) {
            foreach ($items as $item) {
                if (is_dir($item)) {
                    $this->_delete_dir_recursive($item);
                } else {
                    @unlink($item);
                }
            }
        }
        @rmdir($dir);
    }

    private function clean_up_db_query() {
        $CI = &get_instance();
        while (mysqli_more_results($CI->db->conn_id) && mysqli_next_result($CI->db->conn_id)) {
            $dummyResult = mysqli_use_result($CI->db->conn_id);
            if ($dummyResult instanceof mysqli_result) {
                mysqli_free_result($CI->db->conn_id);
            }
        }
    }

	private function write_db_config() {
        $hostname = $this->input->post('hostname');
        $database = $this->input->post('database');
        $username = $this->input->post('username');
        $password = $this->input->post('password');
        $new_database_file = '<?php defined(\'BASEPATH\') or exit(\'No direct script access allowed\');																	   
	 
$query_builder = true;				  

$db[\'default\'] = array(
    \'dsn\'          => \'\',
    \'hostname\' => \'' . $hostname . '\',
    \'username\' => \'' . $username . '\',
    \'password\' => \'' . $password . '\',
    \'database\' => \'' . $database . '\',
    \'dbdriver\'     => \'mysqli\',
    \'dbprefix\'     => \'\',
    \'pconnect\'     => false,
    \'db_debug\'     => (ENVIRONMENT !== \'production\'),
    \'cache_on\'     => false,
    \'cachedir\'     => \'\',
    \'char_set\'     => \'utf8\',
    \'dbcollat\'     => \'utf8_general_ci\',
    \'swap_pre\'     => \'\',
    \'encrypt\'      => false,
    \'compress\'     => false,
    \'stricton\'     => false,
    \'failover\'     => array(),
    \'save_queries\' => true,
    \'multi_branch\' => false,
);

$active_group = \'default\';

$mydb   = $db[\'default\'];
$mysqli = new mysqli($mydb[\'hostname\'], $mydb["username"], $mydb["password"], $mydb["database"]);

if ($mysqli->connect_errno) {
    printf("connection failed: %s\n", $mysqli->connect_error());
    exit();
}

if ($results = $mysqli->query("SHOW TABLES LIKE \'multi_branch\'")) {
    if ($results->num_rows == 1) {

        if ($result = $mysqli->query("SELECT * FROM multi_branch where is_verified =1")) {
            while ($row = $result->fetch_assoc()) {
                $short_name                      = "branch_" . $row[\'id\'];
                $db[$short_name][\'hostname\']     = $row[\'hostname\'];
                $db[$short_name][\'username\']     = $row[\'username\'];
                $db[$short_name][\'password\']     = $row[\'password\'];
                $db[$short_name][\'database\']     = $row[\'database_name\'];
                $db[$short_name][\'dbdriver\']     = \'mysqli\';
                $db[$short_name][\'dbprefix\']     = \'\';
                $db[$short_name][\'pconnect\']     = false;
                $db[$short_name][\'db_debug\']     = false;
                $db[$short_name][\'cache_on\']     = false;
                $db[$short_name][\'cachedir\']     = \'\';
                $db[$short_name][\'char_set\']     = \'utf8\';
                $db[$short_name][\'dbcollat\']     = \'utf8_general_ci\';
                $db[$short_name][\'swap_pre\']     = \'\';
                $db[$short_name][\'autoinit\']     = false;
                $db[$short_name][\'stricton\']     = false;
                $db[$short_name][\'multi_branch\'] = true;

            }
        }
    }
}

$mysqli->close();';

        $fp = fopen(APPPATH . 'config/database.php', 'w+');
        if ($fp) {
            if (fwrite($fp, $new_database_file)) {
                return true;
            }
            fclose($fp);
        }
        return false;
    }

}
