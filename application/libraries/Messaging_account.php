<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Central vs tenant messaging account helpers (retail-pos MessagingAccount port).
 */
class Messaging_account
{
    /** @var CI_Controller */
    public $CI;

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->config('messaging');
    }

    public function isCentral()
    {
        return strtolower(trim((string) $this->CI->config->item('messaging_account'))) !== 'tenant';
    }

    public function productCode()
    {
        $code = trim((string) $this->CI->config->item('messaging_product'));

        return $code !== '' ? $code : 'hospital';
    }

    public function tenantId()
    {
        $tenantId = '';
        if (isset($this->CI->session)) {
            $tenantId = trim((string) $this->CI->session->userdata('tenant_id'));
        }
        if ($tenantId === '' && isset($this->CI->tenant_context) && is_object($this->CI->tenant_context)) {
            $tenantId = trim((string) $this->CI->tenant_context->getTenantId());
        }
        if ($tenantId !== '') {
            return $tenantId;
        }

        $fallback = trim((string) $this->CI->config->item('messaging_fallback_tenant_id'));

        return $fallback !== '' ? $fallback : 'platform';
    }

    public function msg91AuthKey($channel = 'sms')
    {
        $cfg = (array) $this->CI->config->item('messaging_msg91');
        if ($channel === 'whatsapp') {
            $wa = trim((string) ($cfg['whatsapp_auth_key'] ?? ''));
            if ($wa !== '') {
                return $wa;
            }
        }

        return trim((string) ($cfg['auth_key'] ?? ''));
    }

    public function msg91WhatsAppNumber()
    {
        $cfg = (array) $this->CI->config->item('messaging_msg91');

        return preg_replace('/\D/', '', (string) ($cfg['whatsapp_number'] ?? '')) ?: '';
    }

    public function msg91Language()
    {
        $cfg = (array) $this->CI->config->item('messaging_msg91');
        $lang = trim((string) ($cfg['language'] ?? 'en'));

        return $lang !== '' ? $lang : 'en';
    }
}
