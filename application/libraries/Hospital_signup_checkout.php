<?php

defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'libraries/Portal_signup_crypt.php';
require_once APPPATH . 'libraries/Hospital_uuid.php';

class Hospital_signup_checkout
{
    /** @var CI_Controller */
    public $CI;

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->library(array('central_db', 'hospital_tenant_provisioning', 'hospital_tenant_provision_runner'));
        $this->CI->load->config('hospital_portal');
        $this->CI->load->config('tenancy-config');
    }

    public function isComplimentarySignup(array $pending)
    {
        return (int) ($pending['amount_paise'] ?? 0) <= 0
            && (int) ($pending['gst_paise'] ?? 0) <= 0
            && (int) ($pending['discount_paise'] ?? 0) > 0
            && trim((string) ($pending['coupon_code'] ?? '')) !== '';
    }

    /**
     * @return array<string,mixed>
     */
    public function createOrder(array $pending)
    {
        if ($this->isComplimentarySignup($pending)) {
            $orderId = trim((string) ($pending['gateway_order_id'] ?? ''));
            if ($orderId === '') {
                $orderId = 'signup_complimentary_' . $pending['id'];
                $this->CI->central_db->updatePendingSignup($pending['id'], array('gateway_order_id' => $orderId));
            }

            return array(
                'gateway_order_id' => $orderId,
                'key_id' => null,
                'currency' => (string) $this->CI->config->item('billing_currency'),
                'amount_paise' => 0,
                'complimentary' => true,
            );
        }

        $chargePaise = (int) $pending['amount_paise'] + (int) $pending['gst_paise'];
        if ($chargePaise < 100) {
            throw new RuntimeException('Minimum payable amount is ₹1.00.');
        }

        $currency = (string) $this->CI->config->item('billing_currency');
        $gatewayOrderId = trim((string) ($pending['gateway_order_id'] ?? ''));

        if ($this->isConfigured()) {
            if ($gatewayOrderId === '' || strpos($gatewayOrderId, 'signup_local_') === 0) {
                $order = $this->razorpayCreateOrder($chargePaise, $currency, 'hospital_signup_' . $pending['id']);
                $gatewayOrderId = (string) ($order['id'] ?? '');
            }
        } elseif ($this->allowsLocalOrders()) {
            $gatewayOrderId = 'signup_local_' . $pending['id'];
        } else {
            throw new RuntimeException('Razorpay is not configured.');
        }

        if ($gatewayOrderId === '') {
            throw new RuntimeException('Could not create payment order.');
        }

        $this->CI->central_db->updatePendingSignup($pending['id'], array('gateway_order_id' => $gatewayOrderId));

        return array(
            'gateway_order_id' => $gatewayOrderId,
            'key_id' => $this->isConfigured() ? $this->keyId() : null,
            'currency' => $currency,
            'amount_paise' => $chargePaise,
        );
    }

    public function completeComplimentary(array $pending)
    {
        if (!$this->isComplimentarySignup($pending)) {
            throw new RuntimeException('This signup is not complimentary.');
        }
        if (trim((string) ($pending['gateway_order_id'] ?? '')) === '') {
            $this->CI->central_db->updatePendingSignup($pending['id'], array(
                'gateway_order_id' => 'signup_complimentary_' . $pending['id'],
            ));
            $pending = $this->CI->central_db->getPendingSignupByUuid($pending['uuid']);
        }

        return $this->complete($pending, 'complimentary_' . $pending['id'], 'complimentary');
    }

    public function verifyAndComplete(array $pending, $orderId, $paymentId, $signature)
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Razorpay is not configured.');
        }
        if (!hash_equals((string) ($pending['gateway_order_id'] ?? ''), (string) $orderId)) {
            throw new RuntimeException('Checkout verification failed.');
        }
        $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, $this->keySecret());
        if (!hash_equals($expected, (string) $signature)) {
            throw new RuntimeException('Checkout verification failed.');
        }

        $payment = $this->razorpayFetchPayment($paymentId);
        $expectedPaise = (int) $pending['amount_paise'] + (int) $pending['gst_paise'];
        if (
            ($payment['order_id'] ?? null) !== $orderId
            || ($payment['status'] ?? null) !== 'captured'
            || (int) ($payment['amount'] ?? 0) !== $expectedPaise
        ) {
            throw new RuntimeException('Checkout verification failed.');
        }

        return $this->complete($pending, $paymentId, 'razorpay');
    }

    public function simulateLocalPayment(array $pending)
    {
        if (!$this->allowsLocalOrders()) {
            throw new RuntimeException('Local payment simulation is not allowed.');
        }
        if (strpos((string) ($pending['gateway_order_id'] ?? ''), 'signup_local_') !== 0) {
            throw new RuntimeException('Checkout verification failed.');
        }

        return $this->complete($pending, 'local_' . bin2hex(random_bytes(8)), 'local');
    }

    public function complete(array $pending, $paymentId, $gateway)
    {
        if (($pending['status'] ?? '') === 'completed') {
            return $pending;
        }

        if (!empty($pending['expires_at']) && strtotime($pending['expires_at']) < time()) {
            $this->CI->central_db->updatePendingSignup($pending['id'], array('status' => 'expired'));
            throw new RuntimeException('This checkout link has expired.');
        }

        $secret = Portal_signup_crypt::unpackSignupSecret((string) ($pending['password_encrypted'] ?? ''));
        $plainPassword = $secret['password'] ?? null;
        $signupProfile = isset($secret['signup_profile']) && is_array($secret['signup_profile'])
            ? $secret['signup_profile']
            : array();
        if ($plainPassword === null || $plainPassword === '') {
            throw new RuntimeException('Could not decrypt signup password. Check PORTAL_SIGNUP_CRYPT_KEY.');
        }

        $product = (string) $this->CI->config->item('product_code');
        $portalAccount = $this->CI->central_db->findPortalAccountByEmail($pending['email']);
        if (!$portalAccount) {
            $portalAccount = $this->CI->central_db->createPortalAccount(array(
                'first_name' => $pending['first_name'],
                'last_name' => $pending['last_name'],
                'email' => $pending['email'],
                'phone' => $pending['phone'],
                'tax_number' => $pending['tax_number'] ?? null,
                'password' => $plainPassword,
            ));
        }

        $tenantId = $this->CI->central_db->findMappingTenantIdByPortalAccount($portalAccount['id'], $product);
        $tenant = $tenantId ? $this->CI->central_db->getTenant($tenantId) : null;
        if (!$tenant) {
            $tenant = $this->CI->hospital_tenant_provisioning->provisionFromPortalAccount($portalAccount, array(
                'hospital_name' => $pending['store_name'],
                'plan_code' => $pending['plan_code'],
                'billing_cycle' => $pending['billing_cycle'],
                'password_plain' => $plainPassword,
                'partner_id' => $pending['partner_id'] ?? null,
                'partner_code' => $pending['partner_code'] ?? null,
                'signup_profile' => $signupProfile,
            ));
        }

        $job = $this->CI->central_db->getProvisionJob($tenant['id'], $product);
        if (!$job) {
            throw new RuntimeException('Provision job missing.');
        }

        $this->CI->central_db->updatePendingSignup($pending['id'], array(
            'status' => 'processing',
            'completed_portal_account_id' => $portalAccount['id'],
        ));

        if (($job['status'] ?? '') !== 'ready') {
            // Ensure password is on the job payload for bootstrap.
            $payload = is_array($job['payload'] ?? null) ? $job['payload'] : array();
            $payload['password_plain'] = $plainPassword;
            $payload['portal_admin_email'] = $portalAccount['email'];
            $payload['hospital_name'] = $pending['store_name'];
            $this->CI->central_db->upsertProvisionJob($tenant['id'], $product, array(
                'payload' => $payload,
                'status' => 'pending',
            ));
            $job = $this->CI->central_db->getProvisionJob($tenant['id'], $product);
            $this->CI->hospital_tenant_provision_runner->runForJob($job);
        }

        $this->CI->central_db->updatePendingSignup($pending['id'], array(
            'status' => 'completed',
            'completed_portal_account_id' => $portalAccount['id'],
        ));

        try {
            $subscription = $this->CI->central_db->getSubscription($tenant['id'], $product);
            $this->CI->central_db->recordPaymentSnapshot(array(
                'gateway' => $gateway,
                'gateway_payment_id' => $paymentId,
                'tenant_subscription_id' => $subscription['id'] ?? null,
                'gateway_order_id' => $pending['gateway_order_id'] ?? null,
                'amount_paise' => (int) $pending['amount_paise'] + (int) $pending['gst_paise'],
                'currency' => 356,
                'gst_paise' => (int) ($pending['gst_paise'] ?? 0),
                'status' => 'paid',
                'paid_at' => date('Y-m-d H:i:s'),
                'metadata' => array(
                    'intent' => 'hospital_signup',
                    'pending_signup_uuid' => $pending['uuid'],
                    'coupon_code' => $pending['coupon_code'] ?? null,
                ),
            ));
        } catch (Exception $e) {
            log_message('error', 'Hospital signup payment snapshot failed: ' . $e->getMessage());
        }

        return $this->CI->central_db->getPendingSignupByUuid($pending['uuid']);
    }

    private function razorpayCreateOrder($amountPaise, $currency, $receipt)
    {
        $payload = json_encode(array(
            'amount' => (int) $amountPaise,
            'currency' => $currency,
            'receipt' => $receipt,
            'payment_capture' => 1,
        ));
        $ch = curl_init('https://api.razorpay.com/v1/orders');
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $this->keyId() . ':' . $this->keySecret(),
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
        ));
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = json_decode((string) $raw, true);
        if ($code < 200 || $code >= 300 || !is_array($data)) {
            throw new RuntimeException('Razorpay order creation failed.');
        }

        return $data;
    }

    private function razorpayFetchPayment($paymentId)
    {
        $ch = curl_init('https://api.razorpay.com/v1/payments/' . rawurlencode($paymentId));
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $this->keyId() . ':' . $this->keySecret(),
        ));
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = json_decode((string) $raw, true);
        if ($code < 200 || $code >= 300 || !is_array($data)) {
            throw new RuntimeException('Razorpay payment fetch failed.');
        }

        return $data;
    }

    private function isConfigured()
    {
        return $this->keyId() !== '' && $this->keySecret() !== '';
    }

    private function allowsLocalOrders()
    {
        $razorpay = (array) $this->CI->config->item('razorpay');

        return !empty($razorpay['allow_local_orders']);
    }

    private function keyId()
    {
        $razorpay = (array) $this->CI->config->item('razorpay');

        return trim((string) ($razorpay['key_id'] ?? ''));
    }

    private function keySecret()
    {
        $razorpay = (array) $this->CI->config->item('razorpay');

        return trim((string) ($razorpay['key_secret'] ?? ''));
    }
}
