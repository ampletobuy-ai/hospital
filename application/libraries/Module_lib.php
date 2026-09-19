<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Module_lib {

    private $allModules = array();
    private $allPatientModules = array();
    protected $modules;
    var $perm_category;
    public $CI;
    public $patientModules;

    function __construct() {
        $this->CI = & get_instance();
        $this->modules = array();
        $this->patientModules = array();
        self::loadModule(); 
        self::loadPatientModule(); 
    }

    function loadModule() {
        if (!isset($this->CI->module_model)) {
            $this->CI->load->model('Module_model');
        }
        $this->allModules = $this->CI->module_model->get();

        if (!empty($this->allModules)) {
            foreach ($this->allModules as $mod_key => $mod_value) {

                if ($mod_value->is_active == 1) {
                    $this->modules[$mod_value->short_code] = true;
                } else {

                    $this->modules[$mod_value->short_code] = false;
                }
            }
        }
    }

    function loadPatientModule() {
        if (!isset($this->CI->module_model)) {
            $this->CI->load->model('Module_model');
        }
        $this->allPatientModules = $this->CI->module_model->getPatientModule();

        if (!empty($this->allPatientModules)) {
            foreach ($this->allPatientModules as $mod_key => $mod_value) {

                if ($mod_value->is_active == 1) {
                    $this->patientModules[$mod_value->short_code] = true;
                } else {

                    $this->patientModules[$mod_value->short_code] = false;
                }
            }
        }
    }

    function hasActive($module = null) {
        if (empty($module) || empty($this->modules[$module])) {
            return false;
        }

        return $this->planAllowsModule($module);
    }

    function hasPatientActive($module = null) {
        if (empty($module) || empty($this->patientModules[$module])) {
            return false;
        }

        return $this->planAllowsModule($module);
    }

    /**
     * @param string $module
     * @return bool
     */
    private function planAllowsModule($module)
    {
        if (!isset($this->CI->plan_feature_gate)) {
            $this->CI->load->library('plan_feature_gate');
        }

        return $this->CI->plan_feature_gate->moduleAllowed($module);
    }

	function hasModule($module_shortcode) {

        $count = $this->CI->module_model->hasModule($module_shortcode);

        if ($count > 0) {
            return true;
        } else {
            return false;
        }
    }
    

}
