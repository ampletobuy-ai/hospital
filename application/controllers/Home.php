<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * Platform apex home — SaaS marketing landing (not Front CMS).
 * Front CMS hospital website remains at /frontend.
 */
class Home extends Public_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->config('tenancy-config');
        $this->load->config('hospital_portal');
    }

    private function saasEnabled()
    {
        return (bool) $this->config->item('saas_register')
            || (bool) $this->config->item('hospital_saas_register');
    }

    public function index()
    {
        if (!$this->saasEnabled()) {
            // Non-SaaS installs: keep classic hospital Front CMS as home.
            redirect('frontend');
        }

        $setting = $this->setting_model->get();
        $data = array(
            'sch_name' => $setting[0]['name'] ?? product_name(),
            'hospital_plans' => (array) $this->config->item('hospital_plans'),
            'trial_days' => (int) $this->config->item('trial_days'),
        );

        $this->load->view('site/hospital_home', $data);
    }
}
