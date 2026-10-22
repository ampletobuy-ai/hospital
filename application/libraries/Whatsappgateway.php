<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Whatsappgateway
{
    private $_CI;
    private $message_queue   = 0; // 0 = direct send, 1 = queue mode
    private $whatsapp_detail = null;

    public function __construct()
    {
        $this->_CI           = &get_instance();
        $this->_CI->load->model('MsgQueue_model');
        $this->message_queue = $this->_CI->customlib->checkMessageMode();
        $this->_CI->load->library('messaging_account');

        // Load whatsapp config if model exists (tenant mode)
        if (file_exists(APPPATH . 'models/Whatsappconfig_model.php')) {
            $this->_CI->load->model('Whatsappconfig_model');
            $this->whatsapp_detail = $this->_CI->Whatsappconfig_model->getActiveWhatsApp();
        }
    }

    /**
     * Queue-aware WhatsApp dispatcher.
     * If queue mode ON  → inserts into message_queue table.
     * If queue mode OFF → sends directly via Twilio, Meta, or MSG91.
     *
     * @param string $notification_type  e.g. 'appointment_approved'
     * @param string $send_to            phone number with country code
     * @param string $msg                message body or template params (JSON array for Meta)
     * @param string $template_id        WhatsApp template id
     * @param int    $priority           1=high, 0=normal
     * @param string $schedule_date      optional datetime
     */
    /**
     * @param string|array $msg Message body or ['message'=>..., 'variables'=>[name=>value]]
     */
    public function send($notification_type, $send_to, $msg, $template_id, $priority = 0, $schedule_date = '')
    {
        if (empty($send_to)) {
            return false;
        }

        $variables = array();
        $message = $msg;
        if (is_array($msg)) {
            $variables = isset($msg['variables']) && is_array($msg['variables']) ? $msg['variables'] : array();
            $message = isset($msg['message']) ? $msg['message'] : '';
        }

        if ($this->_CI->messaging_account->isCentral()) {
            $this->_CI->load->library('central_message_gateway');
            $templateKey = $notification_type;
            if ($template_id !== '' && $template_id !== null) {
                // Prefer notification type key; template_id may be msg91 name.
                $templateKey = $notification_type ?: $template_id;
            }

            return $this->_CI->central_message_gateway->sendWhatsApp(array(
                'template_key' => $templateKey,
                'template_name' => $template_id,
                'phone' => $send_to,
                'message' => is_string($message) ? $message : '',
                'variables' => $variables,
            ));
        }

        if ($this->message_queue) {
            $data = [
                'notification_type' => $notification_type,
                'schedule_date'     => $schedule_date,
                'sender_details'    => json_encode([
                    'forward_through' => 'whatsapp',
                    'forward_value'   => $send_to,
                    'message'         => $message,
                    'template_id'     => $template_id,
                    'variables'       => $variables,
                ]),
                'priority'          => $priority,
            ];
            return $this->_CI->MsgQueue_model->add($data);
        }

        return $this->sentWhatsappMsg($send_to, $message, $template_id);
    }

    /**
     * Direct WhatsApp send — used by Cron queue processor.
     */
    public function sentWhatsappMsg($send_to, $msg, $template_id)
    {
        if (empty($this->whatsapp_detail)) {
            return false;
        }

        if ($this->whatsapp_detail->type == 'twilio') {
            return $this->sendByTwilio($this->whatsapp_detail, $send_to, $template_id, $msg);
        } elseif ($this->whatsapp_detail->type == 'meta') {
            return $this->sendMetaTemplate($this->whatsapp_detail, $send_to, $template_id, $this->whatsapp_detail->language, $msg);
        } elseif ($this->whatsapp_detail->type == 'msg91') {
            return $this->sendByMsg91($this->whatsapp_detail, $send_to, $template_id, $msg);
        }

        return false;
    }

    /* ===================== META ===================== */
    private function sendMetaTemplate($whatsapp_detail, $send_to, $template_id, $language, $msg)
    {
        $params = [
            'access_token'    => $whatsapp_detail->authkey,
            'phone_number_id' => $whatsapp_detail->contact,
        ];
        $this->_CI->load->library('meta_whatsapp', $params);

        $components = [['type' => 'body', 'parameters' => []]];

        if (is_array($msg)) {
            foreach ($msg as $value) {
                $components[0]['parameters'][] = ['type' => 'text', 'text' => $value];
            }
        } else {
            $components[0]['parameters'][] = ['type' => 'text', 'text' => $msg];
        }

        return $this->_CI->meta_whatsapp->sendTemplate($send_to, $template_id, $language, $components);
    }
    /* ===================== META ===================== */

    /* ===================== TWILIO ===================== */
    private function sendByTwilio($whatsapp_detail, $send_to, $template_id, $msg)
    {
        $params = [
            'username'             => $whatsapp_detail->username,
            'password'             => $whatsapp_detail->password,
            'api_version'          => '2010-04-01',
            'number'               => $whatsapp_detail->contact,
            'whatsapp_template_id' => $template_id,
        ];
        $this->_CI->load->library('twilio_whatsapp', $params);
        $response = $this->_CI->twilio_whatsapp->send($whatsapp_detail->contact, $send_to, $msg);
        return ($response && empty($response->error_message));
    }
    /* ===================== TWILIO ===================== */

    /* ===================== MSG91 ===================== */
    private function sendByMsg91($whatsapp_detail, $send_to, $template_id, $msg)
    {
        $params = [
            'authkey'           => $whatsapp_detail->authkey,
            'integrated_number' => $whatsapp_detail->contact,
            'namespace'         => $whatsapp_detail->username,
        ];
        $this->_CI->load->library('msg91_whatsapp', $params);
        $language = !empty($whatsapp_detail->language) ? $whatsapp_detail->language : 'en';

        return (bool) $this->_CI->msg91_whatsapp->sendTemplate($send_to, $template_id, $language, $msg);
    }
    /* ===================== MSG91 ===================== */
}
