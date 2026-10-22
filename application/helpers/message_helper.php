<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * Apply {{placeholders}} and return message/subject plus a variables map for central MSG91.
 *
 * @param string $template
 * @param string $subject
 * @param array<string, mixed> $data
 * @return array{message:string,subject:string,variables:array<string,string>}
 */
function hospital_message_apply_placeholders($template, $subject, array $data)
{
    $CI = &get_instance();
    if (empty($data['hospital_name'])) {
        try {
            $settings = $CI->setting_model->get();
            if (!empty($settings[0]['name'])) {
                $data['hospital_name'] = $settings[0]['name'];
            }
        } catch (Throwable $e) {
            // ignore
        }
    }
    if (empty($data['hospital_name'])) {
        $data['hospital_name'] = 'Hospital';
    }

    $variables = array();
    foreach ($data as $key => $value) {
        if (is_array($value) || is_object($value)) {
            continue;
        }
        $str = (string) $value;
        $variables[(string) $key] = $str;
        if ($str !== '') {
            $template = str_replace('{{' . $key . '}}', $str, $template);
            $subject = str_replace('{{' . $key . '}}', $str, $subject);
        }
    }

    return array(
        'message' => $template,
        'subject' => $subject,
        'variables' => $variables,
    );
}

function hospitalOpdRegistration($patient_id, $opd_id, $template, $subject, $appointment_date)
{
    $CI = &get_instance();

    $patient = $CI->patient_model->getDetails($opd_id);
    if (!is_array($patient)) {
        $patient = array();
    }
    $patient['opdno'] = $CI->customlib->getSessionPrefixByType('opd_no') . $opd_id;
    $patient['appointment_date'] = $appointment_date;

    return hospital_message_apply_placeholders($template, $subject, $patient);
}

function hospitalIpdRegistration($patient_id, $ipd_id, $template, $subject)
{
    $CI = &get_instance();

    $patient = $CI->patient_model->getIpdDetails($ipd_id);
    if (!is_array($patient)) {
        $patient = array();
    }
    $patient['ipd_no'] = $CI->customlib->getSessionPrefixByType('ipd_no') . $ipd_id;

    return hospital_message_apply_placeholders($template, $subject, $patient);
}

function hospitalIpdDischarged($details, $template, $subject)
{
    return hospital_message_apply_placeholders($template, $subject, is_array($details) ? $details : array());
}

function hospitalOpdDischarged($details, $template, $subject)
{
    return hospital_message_apply_placeholders($template, $subject, is_array($details) ? $details : array());
}

function hospitalAppointmentConfirmation($sender_details, $appointment_id, $template, $subject)
{
    $CI = &get_instance();

    $patient = $CI->appointment_model->getDetailsFornotification($appointment_id);
    if (!is_array($patient)) {
        $patient = array();
    }
    if (!empty($sender_details['patient_name'])) {
        $patient['patient_name'] = $sender_details['patient_name'];
    }

    return hospital_message_apply_placeholders($template, $subject, $patient);
}

function hospitalLoginCredential($credential_for, $sender_details, $template, $subject)
{
    $CI = &get_instance();
    if (!is_array($sender_details)) {
        $sender_details = array();
    }

    if ($credential_for == 'patient') {
        $patient = $CI->patient_model->patientProfileDetails($sender_details['id']);
        $sender_details['url'] = site_url('site/userlogin');
        $sender_details['display_name'] = $patient['patient_name'] ?? '';
    } elseif ($credential_for == 'staff') {
        $staff = $CI->staff_model->get($sender_details['id']);
        $sender_details['url'] = site_url('site/login');
        $sender_details['display_name'] = trim(($staff['name'] ?? '') . ' ' . ($staff['surname'] ?? ''));
    }

    return hospital_message_apply_placeholders($template, $subject, $sender_details);
}

function hospitalLiveConsult($conference_id, $template, $subject)
{
    $CI = &get_instance();

    $conference = $CI->conference_model->getconference($conference_id);
    if (!is_array($conference)) {
        $conference = array();
    }

    return hospital_message_apply_placeholders($template, $subject, $conference);
}

function hospitalLiveMeetingStaff($staff_detail, $template, $subject)
{
    return hospital_message_apply_placeholders($template, $subject, is_array($staff_detail) ? $staff_detail : array());
}

/**
 * Payload for Smsgateway / Whatsappgateway (supports central MSG91 variable map).
 *
 * @param array{message?:string,variables?:array<string,string>} $body
 * @return array{message:string,variables:array<string,string>}
 */
function hospital_channel_payload(array $body)
{
    return array(
        'message' => isset($body['message']) ? (string) $body['message'] : '',
        'variables' => isset($body['variables']) && is_array($body['variables']) ? $body['variables'] : array(),
    );
}
