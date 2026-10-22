<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * Meta Cloud API WhatsApp sender used by Whatsappgateway.
 */
class Meta_whatsapp
{
    /** @var CI_Controller */
    protected $_ci;
    protected $access_token;
    protected $phone_number_id;
    protected $api_version = 'v19.0';

    public function __construct($array = array())
    {
        $this->_ci             = &get_instance();
        $this->access_token    = isset($array['access_token']) ? $array['access_token'] : '';
        $this->phone_number_id = isset($array['phone_number_id']) ? $array['phone_number_id'] : '';
    }

    /**
     * @param string $to           Destination phone (digits / E.164 without whatsapp: prefix)
     * @param string $template_id  Approved template name
     * @param string $language     e.g. en, en_US
     * @param array  $components   Meta template components
     * @return bool|array
     */
    public function sendTemplate($to, $template_id, $language, $components = array())
    {
        $token  = trim((string) $this->access_token);
        $phoneId = trim((string) $this->phone_number_id);
        $to      = preg_replace('/[^\d]/', '', (string) $to);
        $template_id = trim((string) $template_id);
        $language    = trim((string) $language);
        if ($language === '') {
            $language = 'en';
        }

        if ($token === '' || $phoneId === '' || $to === '' || $template_id === '') {
            return false;
        }

        $payload = array(
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'template',
            'template'          => array(
                'name'     => $template_id,
                'language' => array('code' => $language),
            ),
        );

        if (!empty($components) && is_array($components)) {
            $payload['template']['components'] = $components;
        }

        $url = 'https://graph.facebook.com/' . $this->api_version . '/' . rawurlencode($phoneId) . '/messages';
        $ch  = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ));
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $raw  = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            return false;
        }

        $json = json_decode($raw, true);
        if ($code >= 200 && $code < 300 && !empty($json['messages'][0]['id'])) {
            return $json;
        }

        log_message('error', 'Meta WhatsApp send failed HTTP ' . $code . ': ' . $raw);
        return false;
    }
}
