<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

require_once APPPATH . 'helpers/env_helper.php';

$config['saas_enabled'] = hospital_env_bool('SAAS_ENABLED', hospital_env_bool('TENANCY_ENABLED', false));
