<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Platform MSG91 send path using central message_templates + usage meter.
 */
class Central_message_gateway
{
    /** @var CI_Controller */
    public $CI;

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->config('messaging');
        $this->CI->load->library(array('messaging_account', 'message_quota', 'central_db'));
    }

    /**
     * @param array{template_key?:string,template_name?:string,phone?:string,message?:string,variables?:array<string,string>} $data
     * @return bool
     */
    public function sendWhatsApp(array $data)
    {
        $phone = $this->digits((string) ($data['phone'] ?? ''));
        if ($phone === '') {
            return false;
        }

        return $this->send('whatsapp', $data, $phone);
    }

    /**
     * @param array{template_key?:string,template_name?:string,mobile?:string,mobile_numbers?:string,message?:string,variables?:array<string,string>} $data
     * @return bool
     */
    public function sendSms(array $data)
    {
        $raw = (string) ($data['mobile_numbers'] ?? $data['mobile'] ?? $data['phone'] ?? '');
        $numbers = array();
        foreach (explode(',', $raw) as $part) {
            $d = $this->digits($part);
            if ($d !== '') {
                $numbers[$d] = true;
            }
        }
        if (empty($numbers)) {
            return false;
        }

        $ok = true;
        foreach (array_keys($numbers) as $number) {
            if (!$this->send('sms', $data, $number)) {
                $ok = false;
            }
        }

        return $ok;
    }

    /**
     * @return array{sms_used:int,whatsapp_used:int,sms_quota:int,whatsapp_quota:int,whatsapp_needs_pack:bool,is_central:bool}
     */
    public function settingsSummary($tenantId = null)
    {
        $tenantId = $tenantId ?: $this->CI->messaging_account->tenantId();
        $waQuota = $this->CI->message_quota->resolveQuota($tenantId, 'whatsapp');
        $waUsed = $this->usedSegments($tenantId, 'whatsapp');

        return array(
            'is_central' => $this->CI->messaging_account->isCentral(),
            'sms_used' => $this->usedSegments($tenantId, 'sms'),
            'whatsapp_used' => $waUsed,
            'sms_quota' => $this->CI->message_quota->resolveQuota($tenantId, 'sms'),
            'whatsapp_quota' => $waQuota,
            'whatsapp_needs_pack' => $waQuota <= 0 || $waUsed >= $waQuota,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function send($channel, array $data, $recipient)
    {
        $tenantId = $this->CI->messaging_account->tenantId();
        $lookup = trim((string) ($data['template_key'] ?? $data['template_name'] ?? ''));
        $template = $this->findTemplate($channel, $lookup);
        $templateKey = $template ? (string) $template['key'] : $lookup;
        $variables = $this->normalizeVariables($data['variables'] ?? array());
        $preview = $template ? $this->renderBody((string) $template['body'], $variables) : (string) ($data['message'] ?? '');
        $segments = $channel === 'whatsapp' ? 1 : $this->smsSegments($preview);

        if (!$template) {
            log_message('error', 'Central template not found: ' . $channel . '/' . $lookup);

            return false;
        }

        if (!$this->channelAllowed($tenantId, $channel, $templateKey)) {
            log_message('error', 'Central messaging channel disabled for tenant ' . $tenantId . ' ' . $channel);

            return false;
        }

        $status = strtolower((string) ($template['status'] ?? 'pending'));
        // Allow pending in local/dev so seeding works before MSG91 approval; still prefer approved.
        if (!in_array($status, array('approved', 'pending'), true)) {
            log_message('error', 'Central template rejected: ' . $templateKey);

            return false;
        }

        $quota = $this->CI->message_quota->resolveQuota($tenantId, $channel);
        $used = $this->usedSegments($tenantId, $channel);
        if ($this->CI->message_quota->exceedsQuota($channel, $quota, $used, $segments)) {
            log_message('error', 'Central message quota reached for ' . $tenantId . ' ' . $channel);

            return false;
        }

        $authKey = $this->CI->messaging_account->msg91AuthKey($channel);
        if ($authKey === '') {
            log_message('error', 'Central MSG91 auth key missing for ' . $channel);

            return false;
        }

        if ($channel === 'whatsapp') {
            $result = $this->postWhatsApp($authKey, $template, $recipient, $variables);
        } else {
            $result = $this->postSms($authKey, $template, $recipient, $variables);
        }

        if (!$result['ok']) {
            log_message('error', 'Central MSG91 send failed: ' . ($result['error'] ?? ''));
            $this->record($tenantId, $channel, $templateKey, $recipient, $segments, 'failed', null);

            return false;
        }

        $this->record($tenantId, $channel, $templateKey, $recipient, $segments, 'accepted', $result['provider_id'] ?? null);

        return true;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findTemplate($channel, $lookup)
    {
        if ($lookup === '' || !$this->CI->central_db->tableExists('message_templates')) {
            return null;
        }

        $product = $this->CI->messaging_account->productCode();
        $db = $this->CI->central_db->db();

        // Prefer exact key; also accept msg91_template_name (hospital_key_whatsapp).
        $db->from('message_templates');
        $db->where('product_code', $product);
        $db->where('channel', $channel);
        $db->group_start();
        $db->where('key', $lookup);
        $db->or_where('msg91_template_name', $lookup);
        // Strip product/channel suffixes if present.
        $keyGuess = $lookup;
        $prefix = $product . '_';
        $suffix = '_' . $channel;
        if (strpos($keyGuess, $prefix) === 0) {
            $keyGuess = substr($keyGuess, strlen($prefix));
        }
        if (substr($keyGuess, -strlen($suffix)) === $suffix) {
            $keyGuess = substr($keyGuess, 0, -strlen($suffix));
        }
        if ($keyGuess !== '' && $keyGuess !== $lookup) {
            $db->or_where('key', $keyGuess);
        }
        $db->group_end();
        $db->limit(1);
        $query = $db->get();
        $row = $query ? $query->row_array() : null;
        if (!$row) {
            return null;
        }
        if (!empty($row['variables']) && is_string($row['variables'])) {
            $decoded = json_decode($row['variables'], true);
            $row['variables'] = is_array($decoded) ? $decoded : array();
        }

        return $row;
    }

    private function channelAllowed($tenantId, $channel, $templateKey)
    {
        if (!$this->CI->central_db->tableExists('tenant_message_preferences')) {
            return true;
        }

        $product = $this->CI->messaging_account->productCode();
        $db = $this->CI->central_db->db();
        $row = $db->from('tenant_message_preferences')
            ->where('tenant_id', $tenantId)
            ->where('product_code', $product)
            ->limit(1)
            ->get()
            ->row_array();

        if (!$row) {
            // Create defaults (enabled).
            $db->insert('tenant_message_preferences', array(
                'tenant_id' => $tenantId,
                'product_code' => $product,
                'sms_enabled' => 1,
                'whatsapp_enabled' => 1,
                'disabled_template_keys' => null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ));

            return true;
        }

        if ($channel === 'whatsapp' && !(int) ($row['whatsapp_enabled'] ?? 1)) {
            return false;
        }
        if ($channel === 'sms' && !(int) ($row['sms_enabled'] ?? 1)) {
            return false;
        }

        $disabled = array();
        if (!empty($row['disabled_template_keys'])) {
            $decoded = is_array($row['disabled_template_keys'])
                ? $row['disabled_template_keys']
                : json_decode((string) $row['disabled_template_keys'], true);
            if (is_array($decoded)) {
                $disabled = $decoded;
            }
        }

        return !in_array($templateKey, $disabled, true);
    }

    /**
     * @param array<string, mixed> $template
     * @param array<string, string> $variables
     * @return array{ok:bool,provider_id?:string,error?:string}
     */
    private function postWhatsApp($authKey, array $template, $recipient, array $variables)
    {
        $orderedKeys = array_values(is_array($template['variables'] ?? null) ? $template['variables'] : array());
        $components = array();

        // Static IMAGE header (MSG91 header_1) — same pattern as retail-pos sale_invoice.
        $templateKey = trim((string) ($template['key'] ?? ''));
        $headerImageUrl = $templateKey !== '' ? $this->CI->messaging_account->whatsappHeaderImageUrl($templateKey) : null;
        if ($headerImageUrl !== null && $headerImageUrl !== '') {
            $components['header_1'] = array(
                'type' => 'image',
                'value' => $headerImageUrl,
            );
        }

        foreach ($orderedKeys as $index => $name) {
            $value = trim($this->resolveVariable($variables, $name));
            $components['body_' . ($index + 1)] = array(
                'type' => 'text',
                'value' => $value !== '' ? $value : '-',
            );
        }

        $language = trim((string) ($template['msg91_language'] ?? '')) ?: $this->CI->messaging_account->msg91Language();
        $templatePayload = array(
            'name' => (string) $template['msg91_template_name'],
            'language' => array(
                'code' => $language,
                'policy' => 'deterministic',
            ),
            'to_and_components' => array(
                array(
                    'to' => array($recipient),
                    'components' => empty($components) ? new stdClass() : $components,
                ),
            ),
        );
        $namespace = trim((string) ($template['msg91_namespace'] ?? ''));
        if ($namespace !== '') {
            $templatePayload['namespace'] = $namespace;
        }

        $integrated = $this->CI->messaging_account->msg91WhatsAppNumber();
        if ($integrated === '') {
            return array('ok' => false, 'error' => 'MSG91 WhatsApp number missing');
        }

        $payload = array(
            'integrated_number' => $integrated,
            'content_type' => 'template',
            'payload' => array(
                'messaging_product' => 'whatsapp',
                'type' => 'template',
                'template' => $templatePayload,
            ),
        );

        return $this->httpJson(
            'https://control.msg91.com/api/v5/whatsapp/whatsapp-outbound-message/bulk/',
            $authKey,
            $payload,
            true
        );
    }

    /**
     * @param array<string, mixed> $template
     * @param array<string, string> $variables
     * @return array{ok:bool,provider_id?:string,error?:string}
     */
    private function postSms($authKey, array $template, $recipient, array $variables)
    {
        $recipientRow = array('mobiles' => $recipient);
        $orderedKeys = array_values(is_array($template['variables'] ?? null) ? $template['variables'] : array());
        foreach ($orderedKeys as $index => $name) {
            $recipientRow['VAR' . ($index + 1)] = $this->resolveVariable($variables, $name);
        }

        $payload = array(
            'template_id' => (string) $template['msg91_template_name'],
            'short_url' => '0',
            'recipients' => array($recipientRow),
        );

        return $this->httpJson('https://control.msg91.com/api/v5/flow/', $authKey, $payload, false);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{ok:bool,provider_id?:string,error?:string}
     */
    private function httpJson($url, $authKey, array $payload, $requireRequestId)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'authkey: ' . $authKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ));
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            return array('ok' => false, 'error' => $err !== '' ? $err : 'curl failed');
        }

        $json = json_decode($raw, true);
        if ($code < 200 || $code >= 300) {
            return array('ok' => false, 'error' => 'HTTP ' . $code . ': ' . substr($raw, 0, 500));
        }
        if (is_array($json) && isset($json['type']) && strtolower((string) $json['type']) === 'error') {
            return array('ok' => false, 'error' => (string) ($json['message'] ?? $raw));
        }
        if (is_array($json) && isset($json['status']) && strtolower((string) $json['status']) === 'error') {
            return array('ok' => false, 'error' => (string) ($json['message'] ?? $raw));
        }

        $providerId = $this->providerId(is_array($json) ? $json : array());
        if ($requireRequestId && ($providerId === null || $providerId === '')) {
            return array('ok' => false, 'error' => 'MSG91 returned no request_id: ' . substr($raw, 0, 500));
        }

        return array('ok' => true, 'provider_id' => $providerId);
    }

    private function providerId(array $json)
    {
        foreach (array('request_id', 'requestId', 'message_id', 'messageId', 'id') as $key) {
            if (!empty($json[$key]) && is_scalar($json[$key])) {
                return (string) $json[$key];
            }
        }
        if (!empty($json['data']) && is_array($json['data'])) {
            return $this->providerId($json['data']);
        }

        return null;
    }

    private function usedSegments($tenantId, $channel)
    {
        if (!$this->CI->central_db->tableExists('message_usage')) {
            return 0;
        }

        $periodStart = $channel === 'whatsapp'
            ? date('Y-01-01 00:00:00')
            : date('Y-m-01 00:00:00');

        $db = $this->CI->central_db->db();
        $row = $db->select_sum('segments', 'total')
            ->from('message_usage')
            ->where('tenant_id', $tenantId)
            ->where('product_code', $this->CI->messaging_account->productCode())
            ->where('channel', $channel)
            ->where('status', 'accepted')
            ->where('created_at >=', $periodStart)
            ->get()
            ->row_array();

        return (int) ($row['total'] ?? 0);
    }

    private function record($tenantId, $channel, $templateKey, $recipient, $segments, $status, $providerId)
    {
        if (!$this->CI->central_db->tableExists('message_usage')) {
            return;
        }

        $db = $this->CI->central_db->db();
        $db->insert('message_usage', array(
            'tenant_id' => $tenantId,
            'product_code' => $this->CI->messaging_account->productCode(),
            'channel' => $channel,
            'template_key' => $templateKey,
            'recipient' => $recipient,
            'segments' => (int) $segments,
            'provider_message_id' => $providerId,
            'status' => $status,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ));
    }

    /**
     * @param mixed $variables
     * @return array<string, string>
     */
    private function normalizeVariables($variables)
    {
        if (is_string($variables)) {
            $decoded = json_decode($variables, true);
            $variables = is_array($decoded) ? $decoded : array();
        }
        if (!is_array($variables)) {
            return array();
        }

        $clean = array();
        foreach ($variables as $key => $value) {
            if (is_array($value) || is_object($value)) {
                continue;
            }
            $clean[(string) $key] = (string) ($value ?? '');
        }

        return $clean;
    }

    /**
     * Resolve template variable by exact key, snake_case, or bracket alias.
     *
     * @param array<string, string> $variables
     */
    private function resolveVariable(array $variables, $name)
    {
        $name = (string) $name;
        if (array_key_exists($name, $variables)) {
            return (string) $variables[$name];
        }

        $snake = $this->toSnakeKey($name);
        if ($snake !== '' && array_key_exists($snake, $variables)) {
            return (string) $variables[$snake];
        }

        $bracket = '[' . trim($name, "[] \t") . ']';
        if (array_key_exists($bracket, $variables)) {
            return (string) $variables[$bracket];
        }

        return '';
    }

    private function toSnakeKey($name)
    {
        $name = trim((string) $name);
        $name = trim($name, "[]");
        $name = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $name));

        return trim($name, '_');
    }

    private function renderBody($body, array $variables)
    {
        foreach ($variables as $key => $value) {
            $body = str_replace('{{' . $key . '}}', $value, $body);
            $body = str_replace('[' . $key . ']', $value, $body);
        }

        return $body;
    }

    private function digits($value)
    {
        return preg_replace('/\D/', '', (string) $value) ?: '';
    }

    private function smsSegments($message)
    {
        $len = strlen((string) $message);
        if ($len <= 160) {
            return 1;
        }

        return (int) ceil($len / 153);
    }
}
