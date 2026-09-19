<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

require_once APPPATH . 'libraries/Portal_signup_crypt.php';
require_once APPPATH . 'libraries/Hospital_uuid.php';

class Register extends Public_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->config('tenancy-config');
        $this->load->config('hospital_portal');
        $this->load->library(array(
            'central_db',
            'hospital_signup_otp',
            'hospital_signup_quote',
            'hospital_tenant_provisioning',
            'hospital_tenant_provision_runner',
            'hospital_signup_checkout',
        ));
    }

    private function saasEnabled()
    {
        return (bool) $this->config->item('saas_register')
            || (bool) $this->config->item('hospital_saas_register');
    }

    private function requireSaas()
    {
        if (!$this->saasEnabled()) {
            $url = (string) $this->config->item('portal_register_url');
            redirect($url !== '' ? $url : site_url('site/login'));
        }
    }

    private function jsonInput()
    {
        $raw = $this->input->raw_input_stream;
        $json = json_decode((string) $raw, true);
        if (is_array($json)) {
            return $json;
        }

        return $this->input->post(null, true) ?: array();
    }

    private function jsonOut($payload, $status = 200)
    {
        return $this->output
            ->set_content_type('application/json')
            ->set_status_header($status)
            ->set_output(json_encode($payload));
    }

    private function authLayoutData($extra = array())
    {
        $setting = $this->setting_model->get();
        $data = array(
            'sch_name' => $setting[0]['name'] ?? product_name(),
            'sh_variant' => (isset($setting[0]['theme']) && in_array($setting[0]['theme'], array('a', 'b', 'c'), true))
                ? $setting[0]['theme'] : 'a',
            'hospital_plans' => (array) $this->config->item('hospital_plans'),
            'partner_code_prefill' => $this->input->get('partner_code') ?: $this->input->get('ref'),
            'coupon_code_prefill' => $this->input->get('coupon_code') ?: $this->input->get('coupon'),
        );

        return array_merge($data, $extra);
    }

    public function index()
    {
        $this->requireSaas();
        if (strtoupper($this->input->server('REQUEST_METHOD')) === 'POST') {
            return $this->store();
        }
        $this->load->view('site/hospital_register', $this->authLayoutData());
    }

    public function store()
    {
        $this->requireSaas();
        $input = $this->jsonInput();

        $firstName = trim((string) ($input['first_name'] ?? ''));
        $lastName = trim((string) ($input['last_name'] ?? ''));
        $storeName = trim((string) ($input['store_name'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $phone = trim((string) ($input['phone'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $passwordConfirm = (string) ($input['password_confirmation'] ?? '');
        $planCode = (string) ($input['plan_code'] ?? 'trial');
        $billingCycle = (string) ($input['billing_cycle'] ?? 'annual');
        $channel = (string) ($input['verification_channel'] ?? 'email');
        $taxNumber = strtoupper(trim((string) ($input['tax_number'] ?? '')));
        $partnerCode = strtoupper(trim((string) ($input['partner_code'] ?? '')));
        $couponCode = trim((string) ($input['coupon_code'] ?? ''));
        $signupProfile = $this->collectSignupProfile($input);

        $errors = array();
        if ($firstName === '') {
            $errors['first_name'] = 'First name is required.';
        }
        if ($storeName === '') {
            $errors['store_name'] = 'Hospital name is required.';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Valid email is required.';
        }
        if ($phone === '' || !preg_match('/^[\d\s\+\-\(\)]{10,40}$/', $phone)) {
            $errors['phone'] = 'Valid phone is required.';
        }
        if (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }
        if ($password !== $passwordConfirm) {
            $errors['password_confirmation'] = 'Password confirmation does not match.';
        }
        if (!in_array($planCode, array('trial', 'hospital_starter', 'hospital_business', 'hospital_enterprise'), true)) {
            $errors['plan_code'] = 'Invalid plan.';
        }
        if (!in_array($billingCycle, array('monthly', 'annual'), true)) {
            $billingCycle = 'annual';
        }
        if (!in_array($channel, array('email', 'phone'), true)) {
            $errors['verification_channel'] = 'Invalid verification channel.';
        }
        if ($errors) {
            return $this->jsonOut(array('status' => false, 'message' => reset($errors), 'errors' => $errors), 422);
        }

        try {
            $identifier = $channel === 'email' ? $email : $phone;
            $this->hospital_signup_otp->assertVerifiedForRegistration($channel, $identifier);
        } catch (Exception $e) {
            return $this->jsonOut(array('status' => false, 'message' => $e->getMessage()), 422);
        }

        $partnerId = null;
        if ($partnerCode !== '') {
            $partner = $this->central_db->findPartnerByCode($partnerCode);
            if ($partner) {
                $partnerId = (int) $partner['id'];
                $partnerCode = (string) ($partner['code'] ?? $partnerCode);
            }
        }

        if ($planCode !== 'trial') {
            try {
                $response = $this->startPaidSignup(array(
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'store_name' => $storeName,
                    'email' => $email,
                    'phone' => $phone,
                    'tax_number' => $taxNumber !== '' ? $taxNumber : null,
                    'partner_id' => $partnerId,
                    'partner_code' => $partnerCode !== '' ? $partnerCode : null,
                    'coupon_code' => $couponCode,
                    'signup_profile' => $signupProfile,
                ), $password, $planCode, $billingCycle);
                $this->hospital_signup_otp->consumeVerification($channel, $identifier);

                return $this->jsonOut($response);
            } catch (Exception $e) {
                return $this->jsonOut(array('status' => false, 'message' => $e->getMessage()), 422);
            }
        }

        try {
            $portalAccount = $this->resolvePortalAccountForTrial($email, $password, array(
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $phone,
                'tax_number' => $taxNumber !== '' ? $taxNumber : null,
            ));

            $tenant = $this->hospital_tenant_provisioning->provisionFromPortalAccount($portalAccount, array(
                'hospital_name' => $storeName,
                'plan_code' => 'trial',
                'password_plain' => $password,
                'partner_id' => $partnerId,
                'partner_code' => $partnerCode !== '' ? $partnerCode : null,
                'signup_profile' => $signupProfile,
            ));

            $product = (string) $this->config->item('product_code');
            $job = $this->central_db->getProvisionJob($tenant['id'], $product);
            if (!$job) {
                throw new RuntimeException('Provision job was not created.');
            }
            $this->hospital_tenant_provision_runner->runForJob($job);
            $this->hospital_signup_otp->consumeVerification($channel, $identifier);
        } catch (Throwable $e) {
            log_message('error', 'Hospital trial register failed: ' . $e->getMessage());

            return $this->jsonOut(array(
                'status' => false,
                'message' => 'Could not provision your hospital workspace. ' . $e->getMessage(),
            ), 500);
        }

        return $this->jsonOut(array(
            'status' => true,
            'message' => 'Your hospital workspace is ready. You can sign in now.',
            'redirect_url' => site_url('site/login'),
        ));
    }

    private function resolvePortalAccountForTrial($email, $password, array $validated)
    {
        $portalAccount = $this->central_db->findPortalAccountByEmail($email);
        if ($portalAccount) {
            $hash = (string) ($portalAccount['password'] ?? '');
            if ($hash === '' || !password_verify($password, $hash)) {
                throw new InvalidArgumentException('An account with this email already exists. Sign in or use a different email.');
            }

            return $portalAccount;
        }

        return $this->central_db->createPortalAccount(array(
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $email,
            'phone' => $validated['phone'],
            'tax_number' => $validated['tax_number'],
            'password' => $password,
        ));
    }

    private function startPaidSignup(array $validated, $password, $planCode, $billingCycle)
    {
        if ($this->central_db->findPortalAccountByEmail($validated['email'])) {
            throw new InvalidArgumentException('An account with this email already exists. Sign in instead.');
        }
        if ($this->central_db->hasActivePendingSignup($validated['email'])) {
            throw new InvalidArgumentException('A pending checkout already exists for this email.');
        }

        $priced = $this->hospital_signup_quote->pricedSignupQuote($planCode, $billingCycle, $validated['coupon_code'] ?? null);

        $pending = $this->central_db->insertPendingSignup(array(
            'uuid' => Hospital_uuid::v4(),
            'email' => $validated['email'],
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'phone' => $validated['phone'],
            'tax_number' => $validated['tax_number'],
            'password_encrypted' => Portal_signup_crypt::packSignupSecret(
                $password,
                isset($validated['signup_profile']) && is_array($validated['signup_profile'])
                    ? $validated['signup_profile']
                    : array()
            ),
            'store_name' => $validated['store_name'],
            'partner_id' => $validated['partner_id'],
            'partner_code' => $validated['partner_code'],
            'registration_coupon_id' => $priced['registration_coupon_id'],
            'coupon_code' => $priced['coupon_code'],
            'list_amount_paise' => (int) $priced['list_amount_paise'],
            'discount_paise' => (int) $priced['discount_paise'],
            'product_code' => 'hospital',
            'plan_code' => $planCode,
            'billing_cycle' => $billingCycle,
            'amount_paise' => (int) $priced['amount_paise'],
            'gst_paise' => (int) $priced['gst_paise'],
            'status' => 'pending',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+24 hours')),
            'industry_type' => $validated['signup_profile']['facility_type'] ?? null,
        ));

        return array(
            'status' => true,
            'message' => 'Continue to payment to activate your hospital workspace.',
            'redirect_url' => site_url('site/register_checkout/' . $pending['uuid']),
        );
    }

    public function otp_send()
    {
        $this->requireSaas();
        $input = $this->jsonInput();
        $channel = (string) ($input['channel'] ?? 'email');
        $raw = (string) ($input[$channel] ?? '');
        try {
            $result = $this->hospital_signup_otp->send($channel, $raw, $this->input->ip_address());

            return $this->jsonOut(array_merge(array('status' => true), $result));
        } catch (Exception $e) {
            return $this->jsonOut(array('status' => false, 'message' => $e->getMessage()), 422);
        }
    }

    public function otp_verify()
    {
        $this->requireSaas();
        $input = $this->jsonInput();
        $channel = (string) ($input['channel'] ?? 'email');
        $raw = (string) ($input[$channel] ?? '');
        $otp = (string) ($input['otp'] ?? '');
        try {
            $result = $this->hospital_signup_otp->verify($channel, $raw, $otp);
            $this->hospital_signup_otp->storeWebVerification($channel, $raw);

            return $this->jsonOut(array_merge(array('status' => true), $result));
        } catch (Exception $e) {
            return $this->jsonOut(array('status' => false, 'message' => $e->getMessage()), 422);
        }
    }

    public function checkout($uuid = null)
    {
        $this->requireSaas();
        $pending = $this->central_db->getPendingSignupByUuid((string) $uuid);
        if (!$pending) {
            show_404();
        }
        $pendingProduct = (string) ($pending['product_code'] ?? 'hospital');
        if ($pendingProduct !== '' && $pendingProduct !== 'hospital') {
            show_404();
        }
        if (($pending['status'] ?? '') === 'completed') {
            redirect('site/register_complete/' . $pending['uuid']);
        }

        try {
            $checkout = $this->hospital_signup_checkout->createOrder($pending);
            $pending = $this->central_db->getPendingSignupByUuid($pending['uuid']);
        } catch (Exception $e) {
            $data = $this->authLayoutData(array(
                'error_message' => $e->getMessage(),
                'pending' => $pending,
            ));
            $this->load->view('site/hospital_register_checkout', $data);

            return;
        }

        $plans = (array) $this->config->item('hospital_plans');
        $planConfig = $plans[$pending['plan_code']] ?? array();
        $planName = $planConfig['name'] ?? $pending['plan_code'];
        $listSplit = $this->hospital_signup_quote->splitPlanPrice($planConfig, (string) ($pending['billing_cycle'] ?? 'annual'));
        $isComplimentary = $this->hospital_signup_checkout->isComplimentarySignup($pending);
        $razorpay = (array) $this->config->item('razorpay');
        $gstRate = (float) $this->config->item('gst_rate');

        $this->load->view('site/hospital_register_checkout', $this->authLayoutData(array(
            'pending' => $pending,
            'checkout' => $checkout,
            'planName' => $planName,
            'isComplimentary' => $isComplimentary,
            'allowLocal' => !empty($razorpay['allow_local_orders']),
            'listPaise' => (int) $listSplit['exclusive'],
            'discountPaise' => (int) ($pending['discount_paise'] ?? 0),
            'subtotalPaise' => (int) $pending['amount_paise'],
            'gstPaise' => (int) $pending['gst_paise'],
            'totalPaise' => (int) $pending['amount_paise'] + (int) $pending['gst_paise'],
            'gstPercent' => (int) round(((float) $this->config->item('gst_rate')) * 100),
            'priceIncludesGst' => false,
        )));
    }

    public function checkout_verify($uuid = null)
    {
        $this->requireSaas();
        $pending = $this->central_db->getPendingSignupByUuid((string) $uuid);
        if (!$pending) {
            return $this->jsonOut(array('status' => false, 'message' => 'Checkout not found.'), 404);
        }
        $input = $this->jsonInput();
        try {
            $this->hospital_signup_checkout->verifyAndComplete(
                $pending,
                (string) ($input['razorpay_order_id'] ?? ''),
                (string) ($input['razorpay_payment_id'] ?? ''),
                (string) ($input['razorpay_signature'] ?? '')
            );

            return $this->jsonOut(array(
                'status' => true,
                'redirect_url' => site_url('site/register_complete/' . $pending['uuid']),
            ));
        } catch (Exception $e) {
            return $this->jsonOut(array('status' => false, 'message' => $e->getMessage()), 422);
        }
    }

    public function checkout_activate($uuid = null)
    {
        $this->requireSaas();
        $pending = $this->central_db->getPendingSignupByUuid((string) $uuid);
        if (!$pending) {
            show_404();
        }
        try {
            $this->hospital_signup_checkout->completeComplimentary($pending);
            redirect('site/register_complete/' . $pending['uuid']);
        } catch (Exception $e) {
            $this->session->set_flashdata('message', $e->getMessage());
            redirect('site/register_checkout/' . $pending['uuid']);
        }
    }

    public function checkout_simulate($uuid = null)
    {
        $this->requireSaas();
        $pending = $this->central_db->getPendingSignupByUuid((string) $uuid);
        if (!$pending) {
            show_404();
        }
        try {
            $this->hospital_signup_checkout->simulateLocalPayment($pending);
            redirect('site/register_complete/' . $pending['uuid']);
        } catch (Exception $e) {
            $this->session->set_flashdata('message', $e->getMessage());
            redirect('site/register_checkout/' . $pending['uuid']);
        }
    }

    public function complete($uuid = null)
    {
        $this->requireSaas();
        $pending = $this->central_db->getPendingSignupByUuid((string) $uuid);
        if (!$pending) {
            show_404();
        }
        $ready = ($pending['status'] ?? '') === 'completed';
        $this->load->view('site/hospital_register_complete', $this->authLayoutData(array(
            'pending' => $pending,
            'ready' => $ready,
        )));
    }

    /**
     * Optional marketing / follow-up fields for post-trial outreach.
     *
     * @return array<string,mixed>
     */
    private function collectSignupProfile(array $input)
    {
        $allowed = array(
            'facility_type' => array('clinic', 'nursing_home', 'hospital', 'multi_branch'),
            'bed_count' => array('under_20', '20_50', '51_100', '100_plus'),
            'contact_role' => array('owner', 'administrator', 'doctor', 'it', 'other'),
            'go_live_timeline' => array('immediate', '1_2_weeks', '1_month', 'exploring'),
            'referral_source' => array('google', 'referral', 'partner', 'whatsapp', 'social', 'event', 'other'),
        );

        $profile = array();
        foreach ($allowed as $key => $values) {
            $value = trim((string) ($input[$key] ?? ''));
            if ($value !== '' && in_array($value, $values, true)) {
                $profile[$key] = $value;
            }
        }

        $city = trim((string) ($input['city'] ?? ''));
        if ($city !== '') {
            $profile['city'] = mb_substr($city, 0, 80);
        }

        $whatsapp = $input['whatsapp_opt_in'] ?? null;
        $profile['whatsapp_opt_in'] = ($whatsapp === '1' || $whatsapp === 1 || $whatsapp === true || $whatsapp === 'on');
        $profile['captured_at'] = date('c');

        return $profile;
    }
}
