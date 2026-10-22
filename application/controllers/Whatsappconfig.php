<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Whatsappconfig extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('messaging_account');
        // WhatsApp Setting is for tenant providers only; central mode uses Plan & limits.
        if ($this->messaging_account->isCentral()) {
            redirect('admin/planlimits');
        }
        $this->load->library('plan_feature_gate');
        $this->plan_feature_gate->denyUnless('whatsapp_sms');
        $this->load->model('Whatsappconfig_model');
        $this->load->helper('mask');
        $this->load->library('whatsapp_module_setup');
        $this->whatsapp_module_setup->ensure();
        if (isset($this->module_lib)) {
            $this->module_lib->loadModule();
        }
    }

    public function index()
    {
        if (!$this->rbac->hasPrivilege('whatsapp_setting', 'can_view')) {
            access_denied();
        }

        $this->session->set_userdata('top_menu', 'setup');
        $this->session->set_userdata('sub_menu', 'schsettings/index');
        $this->session->set_userdata('inner_menu', 'whatsappconfig/index');

        $data['title']      = $this->lang->line('whatsapp_setting');
        $data['statuslist'] = $this->customlib->getStatus();
        $data['walist']     = $this->Whatsappconfig_model->get();
        $data['messaging_is_central'] = false;
        $data['central_messaging'] = array();

        $this->load->view('layout/header', $data);
        $this->load->view('whatsappconfig/whatsappList', $data);
        $this->load->view('layout/footer', $data);
    }

    public function twilio()
    {
        if (!$this->rbac->hasPrivilege('whatsapp_setting', 'can_edit')) {
            echo json_encode(array('st' => 1, 'msg' => array('error' => $this->lang->line('access_denied'))));
            return;
        }

        $existing = $this->Whatsappconfig_model->getByType('twilio');
        $authToken = trim((string) $this->input->post('twilio_auth_token', true));
        $tokenRule = ($authToken === '' && !empty($existing['password'])) ? 'xss_clean' : 'required|xss_clean';

        $this->form_validation->set_error_delimiters('', '');
        $this->form_validation->set_rules('twilio_account_sid', $this->lang->line('twilio_account_sid'), 'required|xss_clean');
        $this->form_validation->set_rules('twilio_auth_token', $this->lang->line('authentication_token'), $tokenRule);
        $this->form_validation->set_rules('twilio_sender_phone_number', $this->lang->line('registered_phone_number'), 'required|xss_clean');

        if ($this->form_validation->run()) {
            if ($authToken === '' && !empty($existing['password'])) {
                $authToken = $existing['password'];
            }
            $data = array(
                'type'      => 'twilio',
                'name'      => 'Twilio WhatsApp',
                'username'  => $this->input->post('twilio_account_sid', true),
                'password'  => $authToken,
                'contact'   => $this->input->post('twilio_sender_phone_number', true),
                'language'  => 'en',
                'is_active' => $this->input->post('twilio_status', true),
            );
            $this->Whatsappconfig_model->add($data);
            echo json_encode(array('st' => 0, 'msg' => $this->lang->line('update_message')));
            return;
        }

        echo json_encode(array(
            'st'  => 1,
            'msg' => array(
                'twilio_account_sid'         => form_error('twilio_account_sid'),
                'twilio_auth_token'          => form_error('twilio_auth_token'),
                'twilio_sender_phone_number' => form_error('twilio_sender_phone_number'),
            ),
        ));
    }

    public function meta()
    {
        if (!$this->rbac->hasPrivilege('whatsapp_setting', 'can_edit')) {
            echo json_encode(array('st' => 1, 'msg' => array('error' => $this->lang->line('access_denied'))));
            return;
        }

        $existing = $this->Whatsappconfig_model->getByType('meta');
        $accessToken = trim((string) $this->input->post('meta_access_token', true));
        $tokenRule = ($accessToken === '' && !empty($existing['authkey'])) ? 'xss_clean' : 'required|xss_clean';

        $this->form_validation->set_error_delimiters('', '');
        $this->form_validation->set_rules('meta_access_token', $this->lang->line('access_token'), $tokenRule);
        $this->form_validation->set_rules('meta_phone_number_id', $this->lang->line('phone_number_id'), 'required|xss_clean');
        $this->form_validation->set_rules('meta_language', $this->lang->line('language'), 'trim|xss_clean');

        if ($this->form_validation->run()) {
            if ($accessToken === '' && !empty($existing['authkey'])) {
                $accessToken = $existing['authkey'];
            }
            $lang = trim((string) $this->input->post('meta_language', true));
            if ($lang === '') {
                $lang = 'en';
            }
            $data = array(
                'type'      => 'meta',
                'name'      => 'Meta WhatsApp',
                'authkey'   => $accessToken,
                'contact'   => $this->input->post('meta_phone_number_id', true),
                'language'  => $lang,
                'is_active' => $this->input->post('meta_status', true),
            );
            $this->Whatsappconfig_model->add($data);
            echo json_encode(array('st' => 0, 'msg' => $this->lang->line('update_message')));
            return;
        }

        echo json_encode(array(
            'st'  => 1,
            'msg' => array(
                'meta_access_token'    => form_error('meta_access_token'),
                'meta_phone_number_id' => form_error('meta_phone_number_id'),
                'meta_language'        => form_error('meta_language'),
            ),
        ));
    }

    public function msg91()
    {
        if (!$this->rbac->hasPrivilege('whatsapp_setting', 'can_edit')) {
            echo json_encode(array('st' => 1, 'msg' => array('error' => $this->lang->line('access_denied'))));
            return;
        }

        $existing = $this->Whatsappconfig_model->getByType('msg91');
        $authkey  = trim((string) $this->input->post('msg91_authkey', true));
        $tokenRule = ($authkey === '' && !empty($existing['authkey'])) ? 'xss_clean' : 'required|xss_clean';

        $this->form_validation->set_error_delimiters('', '');
        $this->form_validation->set_rules('msg91_authkey', $this->lang->line('auth_key'), $tokenRule);
        $this->form_validation->set_rules('msg91_integrated_number', $this->lang->line('integrated_number'), 'required|xss_clean');
        $this->form_validation->set_rules('msg91_namespace', $this->lang->line('whatsapp_namespace'), 'trim|xss_clean');
        $this->form_validation->set_rules('msg91_language', $this->lang->line('language'), 'trim|xss_clean');

        if ($this->form_validation->run()) {
            if ($authkey === '' && !empty($existing['authkey'])) {
                $authkey = $existing['authkey'];
            }
            $lang = trim((string) $this->input->post('msg91_language', true));
            if ($lang === '') {
                $lang = 'en';
            }
            $data = array(
                'type'      => 'msg91',
                'name'      => 'MSG91 WhatsApp',
                'authkey'   => $authkey,
                'contact'   => $this->input->post('msg91_integrated_number', true),
                'username'  => $this->input->post('msg91_namespace', true),
                'language'  => $lang,
                'is_active' => $this->input->post('msg91_status', true),
            );
            $this->Whatsappconfig_model->add($data);
            echo json_encode(array('st' => 0, 'msg' => $this->lang->line('update_message')));
            return;
        }

        echo json_encode(array(
            'st'  => 1,
            'msg' => array(
                'msg91_authkey'            => form_error('msg91_authkey'),
                'msg91_integrated_number'  => form_error('msg91_integrated_number'),
                'msg91_namespace'          => form_error('msg91_namespace'),
                'msg91_language'           => form_error('msg91_language'),
            ),
        ));
    }
}
