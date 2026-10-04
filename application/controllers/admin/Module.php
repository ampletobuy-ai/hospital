<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Module extends Admin_Controller
{

    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {

        if (!$this->rbac->hasPrivilege('superadmin', 'can_view')) {
            access_denied();
        }
        $this->session->set_userdata('top_menu', 'setup');
        $this->session->set_userdata('sub_menu', 'schsettings/index');
        $this->session->set_userdata('inner_menu', 'admin/module');
        $this->load->library('plan_feature_gate');
        $permissionlist                = $this->module_model->getPermission();
        $patientPermissionList         = $this->module_model->getPatientPermission();
        foreach ($permissionlist as &$permission) {
            $permission['plan_allowed'] = $this->plan_feature_gate->moduleAllowed($permission['short_code'] ?? '');
        }
        unset($permission);
        foreach ($patientPermissionList as &$permission) {
            $code = $permission['permission_group_short_code'] ?? ($permission['short_code'] ?? '');
            $permission['plan_allowed'] = $this->plan_feature_gate->moduleAllowed($code);
        }
        unset($permission);
        $data["permissionList"]        = $permissionlist;
        $data['patientPermissionList'] = $patientPermissionList;
        $data['plan_enforcing'] = $this->plan_feature_gate->isEnforcing();
        $data['plan_upgrade_url'] = site_url('site/subscription');
        $this->load->view("layout/header");
        $this->load->view("setting/permission", $data);
        $this->load->view("layout/footer");
    }

    public function changeStatus()
    {
        $short_code = $this->input->post("short_code", TRUE);
        $status     = $this->input->post("status", TRUE);

        if (!empty($short_code)) {
            $this->load->library('plan_feature_gate');
            if ((string) $status === '1' && !$this->plan_feature_gate->moduleAllowed($short_code)) {
                echo json_encode(array(
                    'status' => 0,
                    'msg' => 'This module is not available on your current plan. Please upgrade your subscription.',
                ));
                return;
            }

            $data         = array('short_code' => $short_code, 'is_active' => $status);
            $data_patient = array('permission_group_short_code' => $short_code, 'is_active' => $status);
            $result       = $this->module_model->changeStatus($data, $data_patient);

            $response = array('status' => 1, 'msg' => $this->lang->line('status_change_message'));
            echo json_encode($response);
        }
    }

    public function changePatientStatus()
    {

        $id     = $this->input->post("id", TRUE);
        $status = $this->input->post("status", TRUE);

        if (!empty($id)) {

            $data   = array('id' => $id, 'is_active' => $status);
            $result = $this->module_model->changePatientStatus($data);
            $response = array('status' => 1, 'msg' => $this->lang->line('status_change_message'));
            echo json_encode($response);
        }
    }

}
