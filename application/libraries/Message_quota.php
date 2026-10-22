<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Per-tenant messaging quotas for central messaging (retail-pos MessageQuotaService port).
 */
class Message_quota
{
    const WHATSAPP_PACK_CODE = 'whatsapp_message_pack';

    /** @var CI_Controller */
    public $CI;

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->config('messaging');
        $this->CI->load->library('messaging_account');
    }

    public function resolveQuota($tenantId, $channel)
    {
        if ($channel === 'whatsapp') {
            return $this->resolveWhatsAppQuota($tenantId);
        }

        $quota = (array) $this->CI->config->item('messaging_quota');

        return max(0, (int) ($quota['sms'] ?? 500));
    }

    /**
     * SMS: quota 0 = unlimited. WhatsApp: quota 0 = no allowance.
     */
    public function exceedsQuota($channel, $quota, $used, $segments)
    {
        if ($channel === 'whatsapp') {
            return ($used + $segments) > $quota;
        }

        return $quota > 0 && ($used + $segments) > $quota;
    }

    private function resolveWhatsAppQuota($tenantId)
    {
        $quotaCfg = (array) $this->CI->config->item('messaging_quota');
        $base = max(0, (int) ($quotaCfg['whatsapp'] ?? 50));

        $this->CI->load->library('central_db');
        $product = $this->CI->messaging_account->productCode();
        $subscription = $this->CI->central_db->getSubscription($tenantId, $product);
        if (!$subscription) {
            return $base;
        }

        $addons = $this->loadActiveAddons($tenantId, $product);
        $packCode = (string) $this->CI->config->item('messaging_whatsapp_pack_code') ?: self::WHATSAPP_PACK_CODE;
        $perPack = max(1, (int) $this->CI->config->item('messaging_whatsapp_messages_per_pack'));

        $packExtra = 0;
        foreach ($addons as $addon) {
            if (($addon['addon_code'] ?? '') !== $packCode) {
                continue;
            }
            $meta = array();
            if (!empty($addon['metadata'])) {
                $decoded = is_array($addon['metadata']) ? $addon['metadata'] : json_decode((string) $addon['metadata'], true);
                if (is_array($decoded)) {
                    $meta = $decoded;
                }
            }
            if (array_key_exists('messages_allowance', $meta) && $meta['messages_allowance'] !== null) {
                return max(0, (int) $meta['messages_allowance']);
            }
            $packExtra += max(0, (int) ($addon['quantity'] ?? 0)) * $perPack;
        }

        return $base + $packExtra;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadActiveAddons($tenantId, $productCode)
    {
        try {
            if (!$this->CI->central_db->tableExists('subscription_addons')) {
                return array();
            }
            $db = $this->CI->central_db->db();
            $query = $db->select('addon_code, quantity, status, metadata')
                ->from('subscription_addons')
                ->where('tenant_id', $tenantId)
                ->where('product_code', $productCode)
                ->where('status', 'active')
                ->get();

            return $query ? $query->result_array() : array();
        } catch (Throwable $e) {
            log_message('error', 'Message_quota addons: ' . $e->getMessage());

            return array();
        }
    }
}
