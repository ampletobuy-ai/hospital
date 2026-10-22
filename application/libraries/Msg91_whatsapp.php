<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * MSG91 WhatsApp template sender used by Whatsappgateway.
 */
class Msg91_whatsapp
{
    /** @var CI_Controller */
    protected $_ci;
    protected $authkey;
    protected $integrated_number;
    protected $namespace;
    protected $api_url = 'https://control.msg91.com/api/v5/whatsapp/whatsapp-outbound-message/bulk/';

    public function __construct($array = array())
    {
        $this->_ci               = &get_instance();
        $this->authkey           = isset($array['authkey']) ? $array['authkey'] : '';
        $this->integrated_number = isset($array['integrated_number']) ? $array['integrated_number'] : '';
        $this->namespace         = isset($array['namespace']) ? $array['namespace'] : '';
    }

    /**
     * @param string       $to           Destination phone (digits / E.164)
     * @param string       $template_id  Approved template name
     * @param string       $language     e.g. en, en_US
     * @param string|array $msg          Body variable values
     * @return bool|array
     */
    public function sendTemplate($to, $template_id, $language, $msg = '')
    {
        $authkey = trim((string) $this->authkey);
        $from    = preg_replace('/[^\d]/', '', (string) $this->integrated_number);
        $to      = preg_replace('/[^\d]/', '', (string) $to);
        $template_id = trim((string) $template_id);
        $language    = trim((string) $language);
        if ($language === '') {
            $language = 'en';
        }

        if ($authkey === '' || $from === '' || $to === '' || $template_id === '') {
            return false;
        }

        $components = array();
        if (is_array($msg)) {
            $i = 1;
            foreach ($msg as $value) {
                $components['body_' . $i] = array(
                    'type'  => 'text',
                    'value' => (string) $value,
                );
                $i++;
            }
        } elseif (is_string($msg) && $msg !== '') {
            if ($this->looksLikeJson($msg)) {
                $decoded = json_decode($msg, true);
                if (is_array($decoded)) {
                    $i = 1;
                    foreach ($decoded as $value) {
                        $components['body_' . $i] = array(
                            'type'  => 'text',
                            'value' => is_scalar($value) ? (string) $value : json_encode($value),
                        );
                        $i++;
                    }
                }
            } else {
                $components['body_1'] = array(
                    'type'  => 'text',
                    'value' => $msg,
                );
            }
        }

        $template = array(
            'name'     => $template_id,
            'language' => array(
                'code'   => $language,
                'policy' => 'deterministic',
            ),
            'to_and_components' => array(
                array(
                    'to'         => array($to),
                    'components' => $components,
                ),
            ),
        );

        $namespace = trim((string) $this->namespace);
        if ($namespace !== '') {
            $template['namespace'] = $namespace;
        }

        $payload = array(
            'integrated_number' => $from,
            'content_type'      => 'template',
            'payload'           => array(
                'messaging_product' => 'whatsapp',
                'type'              => 'template',
                'template'          => $template,
            ),
        );

        $ch = curl_init($this->api_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'authkey: ' . $authkey,
            'Content-Type: application/json',
            'Accept: application/json',
        ));
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $raw  = curl_exec($ch);
        $err  = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            log_message('error', 'MSG91 WhatsApp curl error: ' . $err);
            return false;
        }

        $json = json_decode($raw, true);
        // MSG91 typically returns HTTP 200 with status/type keys when accepted.
        if ($code >= 200 && $code < 300) {
            if (is_array($json) && isset($json['status']) && strtolower((string) $json['status']) === 'error') {
                log_message('error', 'MSG91 WhatsApp send failed: ' . $raw);
                return false;
            }
            return is_array($json) ? $json : true;
        }

        log_message('error', 'MSG91 WhatsApp send failed HTTP ' . $code . ': ' . $raw);
        return false;
    }

    private function looksLikeJson($value)
    {
        $value = trim((string) $value);
        if ($value === '' || ($value[0] !== '{' && $value[0] !== '[')) {
            return false;
        }
        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }
}
