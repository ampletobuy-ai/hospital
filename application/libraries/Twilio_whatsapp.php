<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * Twilio WhatsApp sender used by Whatsappgateway.
 */
class Twilio_whatsapp
{
    /** @var CI_Controller */
    protected $_ci;
    protected $account_sid;
    protected $auth_token;
    protected $api_version;
    protected $from_number;
    protected $whatsapp_template_id;

    public function __construct($array = array())
    {
        $this->_ci                 = &get_instance();
        $this->account_sid         = isset($array['username']) ? $array['username'] : '';
        $this->auth_token          = isset($array['password']) ? $array['password'] : '';
        $this->api_version         = isset($array['api_version']) ? $array['api_version'] : '2010-04-01';
        $this->from_number         = isset($array['number']) ? $array['number'] : '';
        $this->whatsapp_template_id = isset($array['whatsapp_template_id']) ? $array['whatsapp_template_id'] : '';
    }

    /**
     * @param string $from  Registered Twilio WhatsApp number (or ignored; uses config)
     * @param string $to    Destination phone (E.164)
     * @param string|array $message Body text or template variables
     * @return object Response-like object with error_message when failed
     */
    public function send($from, $to, $message)
    {
        $result = new stdClass();
        $result->error_message = '';
        $result->sid           = '';

        $sid   = trim((string) $this->account_sid);
        $token = trim((string) $this->auth_token);
        $fromN = $this->formatWhatsAppNumber($this->from_number ?: $from);
        $toN   = $this->formatWhatsAppNumber($to);

        if ($sid === '' || $token === '' || $fromN === '' || $toN === '') {
            $result->error_message = 'Missing Twilio WhatsApp credentials or phone number';
            return $result;
        }

        $url  = 'https://api.twilio.com/' . $this->api_version . '/Accounts/' . rawurlencode($sid) . '/Messages.json';
        $data = array(
            'From' => $fromN,
            'To'   => $toN,
        );

        $templateId = trim((string) $this->whatsapp_template_id);
        if ($templateId !== '') {
            // Content API template (ContentSid). Variables as JSON ContentVariables when array/JSON.
            $data['ContentSid'] = $templateId;
            if (is_array($message)) {
                $vars = array();
                $i    = 1;
                foreach ($message as $value) {
                    $vars[(string) $i] = (string) $value;
                    $i++;
                }
                $data['ContentVariables'] = json_encode($vars);
            } elseif (is_string($message) && $message !== '' && $this->looksLikeJson($message)) {
                $data['ContentVariables'] = $message;
            }
        } else {
            if (is_array($message)) {
                $message = implode(' ', $message);
            }
            $data['Body'] = (string) $message;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $sid . ':' . $token);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $raw  = curl_exec($ch);
        $err  = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            $result->error_message = $err !== '' ? $err : 'Twilio request failed';
            return $result;
        }

        $json = json_decode($raw);
        if ($code >= 200 && $code < 300 && is_object($json) && !empty($json->sid)) {
            $result->sid = $json->sid;
            return $result;
        }

        $result->error_message = is_object($json) && !empty($json->message)
            ? $json->message
            : ('Twilio HTTP ' . $code);
        return $result;
    }

    private function formatWhatsAppNumber($number)
    {
        $number = trim((string) $number);
        if ($number === '') {
            return '';
        }
        if (stripos($number, 'whatsapp:') === 0) {
            return $number;
        }
        // Keep leading + for E.164
        $digits = preg_replace('/[^\d+]/', '', $number);
        return 'whatsapp:' . $digits;
    }

    private function looksLikeJson($value)
    {
        $value = trim($value);
        if ($value === '' || ($value[0] !== '{' && $value[0] !== '[')) {
            return false;
        }
        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }
}
