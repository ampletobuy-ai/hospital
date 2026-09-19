<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Hospital_signup_otp
{
    /** @var CI_Controller */
    public $CI;

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->library('central_db');
        $this->CI->load->config('hospital_portal');
        $this->CI->load->library('mailer');
    }

    public function normalizeIdentifier($channel, $value)
    {
        $value = trim((string) $value);
        if ($channel === 'email') {
            return strtolower($value);
        }
        $digits = preg_replace('/\D+/', '', $value);
        if ($digits === null) {
            $digits = '';
        }

        return strlen($digits) >= 10 ? substr($digits, -10) : $digits;
    }

    /**
     * @return array{message:string,debug_otp?:string}
     */
    public function send($channel, $rawIdentifier, $ipAddress = null)
    {
        if (!$this->CI->central_db->tableExists('portal_signup_otps')) {
            throw new RuntimeException('portal_signup_otps table is missing. Run retail-pos central migrations.');
        }

        $identifier = $this->normalizeIdentifier($channel, $rawIdentifier);
        if ($identifier === '') {
            throw new InvalidArgumentException('Enter a valid email or phone first.');
        }

        $this->CI->central_db->deleteUnverifiedOtps($channel, $identifier);

        $otpCfg = (array) $this->CI->config->item('otp');
        $length = max(4, (int) ($otpCfg['length'] ?? 6));
        $ttlMinutes = max(1, (int) ($otpCfg['ttl_minutes'] ?? 10));
        $max = (int) pow(10, $length) - 1;
        $otp = str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);

        $this->CI->central_db->createOtp(array(
            'channel' => $channel,
            'identifier' => $identifier,
            'otp_hash' => password_hash($otp, PASSWORD_DEFAULT),
            'expires_at' => date('Y-m-d H:i:s', time() + ($ttlMinutes * 60)),
            'ip_address' => $ipAddress,
        ));

        $response = array('message' => 'Verification code sent.');
        $razorpay = (array) $this->CI->config->item('razorpay');
        $exposeOtp = !empty($razorpay['allow_local_orders']);

        if ($channel === 'email') {
            $this->sendOtpEmail($rawIdentifier, $otp, $ttlMinutes);
            if ($exposeOtp) {
                $response['debug_otp'] = $otp;
                $response['message'] = 'Verification code sent (local debug).';
            }
        } else {
            // SMS provider not wired; expose OTP when local orders allowed.
            if ($exposeOtp) {
                $response['debug_otp'] = $otp;
                $response['message'] = 'Verification code ready (local debug — SMS not configured).';
            } else {
                throw new RuntimeException('Phone OTP is not configured. Use email verification.');
            }
        }

        return $response;
    }

    /**
     * @return array{message:string,verification_token:string}
     */
    public function verify($channel, $rawIdentifier, $code)
    {
        $identifier = $this->normalizeIdentifier($channel, $rawIdentifier);
        $record = $this->CI->central_db->latestUnverifiedOtp($channel, $identifier);
        $otpCfg = (array) $this->CI->config->item('otp');

        if (!$record || strtotime($record['expires_at']) < time()) {
            throw new InvalidArgumentException('Verification code expired. Request a new one.');
        }

        $maxAttempts = max(1, (int) ($otpCfg['max_attempts'] ?? 5));
        if ((int) ($record['attempts'] ?? 0) >= $maxAttempts) {
            throw new InvalidArgumentException('Too many attempts. Request a new code.');
        }

        $this->CI->central_db->updateOtp($record['id'], array(
            'attempts' => (int) ($record['attempts'] ?? 0) + 1,
        ));

        if (!password_verify((string) $code, (string) $record['otp_hash'])) {
            throw new InvalidArgumentException('Invalid verification code.');
        }

        $this->CI->central_db->updateOtp($record['id'], array(
            'verified_at' => date('Y-m-d H:i:s'),
        ));

        return array(
            'message' => 'Verified',
            'verification_token' => (string) $record['uuid'],
        );
    }

    public function storeWebVerification($channel, $rawIdentifier)
    {
        $this->CI->session->set_userdata('portal_signup_otp_verified', array(
            'channel' => $channel,
            'identifier' => $this->normalizeIdentifier($channel, $rawIdentifier),
            'verified_at' => date('c'),
        ));
    }

    public function assertVerifiedForRegistration($channel, $rawIdentifier)
    {
        $identifier = $this->normalizeIdentifier($channel, $rawIdentifier);
        $session = $this->CI->session->userdata('portal_signup_otp_verified');

        if (
            !is_array($session)
            || ($session['channel'] ?? null) !== $channel
            || ($session['identifier'] ?? null) !== $identifier
        ) {
            throw new InvalidArgumentException('Please verify your email or phone with OTP first.');
        }

        $record = $this->CI->central_db->latestVerifiedOtp($channel, $identifier);
        $otpCfg = (array) $this->CI->config->item('otp');
        $verificationTtl = max(1, (int) ($otpCfg['verification_ttl_minutes'] ?? 30)) * 60;

        if (!$record || empty($record['verified_at']) || (strtotime($record['verified_at']) + $verificationTtl) < time()) {
            throw new InvalidArgumentException('OTP verification expired. Please verify again.');
        }

        return $record;
    }

    public function consumeVerification($channel, $rawIdentifier)
    {
        $identifier = $this->normalizeIdentifier($channel, $rawIdentifier);
        $record = $this->CI->central_db->latestVerifiedOtp($channel, $identifier);
        if ($record) {
            $this->CI->central_db->updateOtp($record['id'], array(
                'consumed_at' => date('Y-m-d H:i:s'),
            ));
        }
        $this->CI->session->unset_userdata('portal_signup_otp_verified');
    }

    private function sendOtpEmail($email, $otp, $ttlMinutes)
    {
        $subject = 'Your verification code';
        $body = "Your verification code is {$otp}. It expires in {$ttlMinutes} minutes.";
        try {
            if (method_exists($this->CI->mailer, 'send_mail')) {
                $this->CI->mailer->send_mail($email, $subject, $body);
            } elseif (function_exists('mail')) {
                @mail($email, $subject, $body);
            }
        } catch (Exception $e) {
            log_message('error', 'Hospital signup OTP email failed: ' . $e->getMessage());
        }
    }
}
