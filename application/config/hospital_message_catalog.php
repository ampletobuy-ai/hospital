<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Central message_templates catalog for product_code=hospital.
 * Keys match notification_setting.type (Smart Hospital Notification Setting).
 *
 * @return array<int, array{product_code:string,key:string,name:string,channel:string,body:string,variables:array<int,string>}>
 */
function hospital_message_catalog_definitions()
{
    $p = 'hospital';
    $row = function ($key, $name, $channel, $body, array $variables) use ($p) {
        return array(
            'product_code' => $p,
            'key' => $key,
            'name' => $name,
            'channel' => $channel,
            'body' => $body,
            'variables' => $variables,
        );
    };

    return array(
        $row('opd_patient_registration', 'OPD PATIENT REGISTRATION', 'sms', 'Dear [Patient Name], your OPD Registration at [Hospital Name] is successful on date [Appointment Date] with Patient Id [Patient Id] and OPD No [Opd No].', array('[Patient Name]', '[Appointment Date]', '[Patient Id]', '[Opd No]', '[Hospital Name]')),
        $row('opd_patient_registration', 'OPD PATIENT REGISTRATION', 'whatsapp', 'Dear {{patient_name}} your OPD Registration at {{hospital_name}} is successful on date {{appointment_date}} with Patient Id {{patient_id}} and OPD No {{opdno}}', array('patient_name', 'hospital_name', 'appointment_date', 'patient_id', 'opdno')),

        $row('ipd_patient_registration', 'IPD PATIENT REGISTRATION', 'sms', 'Dear [Patient Name], your IPD Registration at [Hospital Name] is successful with Patient Id [Patient Id] and IPD No [Ipd No].', array('[Patient Name]', '[Patient Id]', '[Ipd No]', '[Hospital Name]')),
        $row('ipd_patient_registration', 'IPD PATIENT REGISTRATION', 'whatsapp', 'Dear {{patient_name}} your IPD Registration at {{hospital_name}} is successful with Patient Id {{patient_id}} and IPD No {{ipd_no}}', array('patient_name', 'hospital_name', 'patient_id', 'ipd_no')),

        $row('ipd_patient_discharged', 'IPD PATIENT DISCHARGE', 'sms', 'IPD Patient [Patient Name] is now discharged from [Hospital Name]. Total bill [Total Amount]. Paid [Paid Amount]. Balance [Balance Amount].', array('[Patient Name]', '[Hospital Name]', '[Total Amount]', '[Paid Amount]', '[Balance Amount]')),
        $row('ipd_patient_discharged', 'IPD PATIENT DISCHARGE', 'whatsapp', 'IPD Patient {{patient_name}} is now discharged from {{hospital_name}}. Total bill amount is {{currency_symbol}}{{total_amount}}. Total paid amount is {{currency_symbol}}{{paid_amount}}. Total balance bill amount is {{currency_symbol}}{{balance_amount}}', array('patient_name', 'hospital_name', 'currency_symbol', 'total_amount', 'paid_amount', 'balance_amount')),

        $row('login_credential', 'LOGIN CREDENTIAL', 'sms', 'Hello [Display Name], your [Hospital Name] login details are Url: [Url] Username: [Username] Password: [Password]', array('[Display Name]', '[Hospital Name]', '[Url]', '[Username]', '[Password]')),
        $row('login_credential', 'LOGIN CREDENTIAL', 'whatsapp', 'Hello {{display_name}} your {{hospital_name}} login details are Url: {{url}} Username: {{username}} Password: {{password}}', array('display_name', 'hospital_name', 'url', 'username', 'password')),

        $row('appointment_approved', 'APPOINTMENT APPROVED', 'sms', 'Dear [Patient Name], your appointment with [Staff Name] [Staff Surname] is confirmed on [Date] with appointment no: [Appointment No].', array('[Patient Name]', '[Staff Name]', '[Staff Surname]', '[Date]', '[Appointment No]')),
        $row('appointment_approved', 'APPOINTMENT APPROVED', 'whatsapp', 'Dear {{patient_name}} your appointment with {{staff_name}} {{staff_surname}} is confirmed on {{date}} with appointment no: {{appointment_no}}', array('patient_name', 'staff_name', 'staff_surname', 'date', 'appointment_no')),

        $row('live_meeting', 'LIVE MEETING', 'sms', 'Dear staff, your live meeting [Title] has been scheduled on [Date] for the duration of [Duration] minute.', array('[Title]', '[Date]', '[Duration]')),
        $row('live_meeting', 'LIVE MEETING', 'whatsapp', 'Dear staff, your live meeting {{title}} has been scheduled on {{date}} for the duration of {{duration}} minute.', array('title', 'date', 'duration')),

        $row('live_consult', 'LIVE CONSULTATION', 'sms', 'Dear patient, your live consultation [Title] has been scheduled on [Date] for the duration of [Duration] minute.', array('[Title]', '[Date]', '[Duration]')),
        $row('live_consult', 'LIVE CONSULTATION', 'whatsapp', 'Dear patient, your live consultation {{title}} has been scheduled on {{date}} for the duration of {{duration}} minute.', array('title', 'date', 'duration')),

        $row('opd_patient_discharged', 'OPD PATIENT DISCHARGED', 'sms', 'OPD No [Opd No] [Patient Name] is now discharged from [Hospital Name]. Total bill [Total Amount]. Paid [Paid Amount]. Balance [Balance Amount].', array('[Opd No]', '[Patient Name]', '[Hospital Name]', '[Total Amount]', '[Paid Amount]', '[Balance Amount]')),
        $row('opd_patient_discharged', 'OPD PATIENT DISCHARGED', 'whatsapp', 'OPD No {{opd_no}} {{patient_name}} is now discharged from {{hospital_name}}. Total bill amount was {{currency_symbol}}{{total_amount}}. Total paid amount was {{currency_symbol}}{{paid_amount}}. Total balance amount is {{currency_symbol}}{{balance_amount}}', array('opd_no', 'patient_name', 'hospital_name', 'currency_symbol', 'total_amount', 'paid_amount', 'balance_amount')),

        $row('forgot_password', 'FORGOT PASSWORD', 'sms', 'Dear [Display Name], reset your [Hospital Name] password using this link: [Reset Pass Link]', array('[Display Name]', '[Hospital Name]', '[Reset Pass Link]')),
        $row('forgot_password', 'FORGOT PASSWORD', 'whatsapp', 'Dear {{display_name}}, recently a request was submitted to reset password for your {{hospital_name}} account ({{email}}). Reset your password using this link: {{resetpasslink}}', array('display_name', 'hospital_name', 'email', 'resetpasslink')),
    );
}
