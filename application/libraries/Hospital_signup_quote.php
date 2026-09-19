<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Hospital_signup_quote
{
    /** @var CI_Controller */
    public $CI;

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->config('hospital_portal');
        $this->CI->load->library('central_db');
    }

    /**
     * @return array{
     *   amount_paise:int,
     *   gst_paise:int,
     *   total_paise:int,
     *   list_amount_paise:int,
     *   list_gst_paise:int,
     *   list_total_paise:int,
     *   discount_paise:int,
     *   registration_coupon_id:?int,
     *   coupon_code:?string
     * }
     */
    public function pricedSignupQuote($planCode, $billingCycle, $rawCouponCode = null)
    {
        $plans = (array) $this->CI->config->item('hospital_plans');
        if (!isset($plans[$planCode])) {
            throw new InvalidArgumentException('Unknown plan.');
        }

        $plan = $plans[$planCode];
        $listSplit = $this->splitPlanPrice($plan, $billingCycle);
        if ($listSplit['exclusive'] <= 0) {
            throw new RuntimeException('Plan price is missing.');
        }

        $couponCode = strtoupper(trim((string) $rawCouponCode));
        $couponCode = preg_replace('/\s+/', '', $couponCode);
        if ($couponCode === '') {
            return array(
                'amount_paise' => $listSplit['exclusive'],
                'gst_paise' => $listSplit['gst'],
                'total_paise' => $listSplit['total'],
                'list_amount_paise' => $listSplit['exclusive'],
                'list_gst_paise' => $listSplit['gst'],
                'list_total_paise' => $listSplit['total'],
                'discount_paise' => 0,
                'registration_coupon_id' => null,
                'coupon_code' => null,
            );
        }

        $coupon = $this->CI->central_db->findRegistrationCoupon($couponCode);
        if (!$coupon) {
            throw new InvalidArgumentException('Invalid coupon code.');
        }

        if (!$this->couponIsActive($coupon)) {
            throw new InvalidArgumentException('Coupon is inactive.');
        }

        $discount = $this->discountPaiseFor($coupon, $listSplit['exclusive']);
        $remainingExclusive = max(0, $listSplit['exclusive'] - $discount);
        $allowsZero = !empty($coupon['allows_zero_charge']) || !empty($coupon['allow_zero_charge']);

        if ($remainingExclusive <= 0 && $allowsZero) {
            return array(
                'amount_paise' => 0,
                'gst_paise' => 0,
                'total_paise' => 0,
                'list_amount_paise' => $listSplit['exclusive'],
                'list_gst_paise' => $listSplit['gst'],
                'list_total_paise' => $listSplit['total'],
                'discount_paise' => $listSplit['exclusive'],
                'registration_coupon_id' => isset($coupon['id']) ? (int) $coupon['id'] : null,
                'coupon_code' => $coupon['code'] ?? $couponCode,
            );
        }

        $amountExclusive = max(100, $remainingExclusive);
        $discount = max(0, $listSplit['exclusive'] - $amountExclusive);
        $gstRate = max(0, (float) $this->CI->config->item('gst_rate'));
        $gstPaise = (int) round($amountExclusive * $gstRate);

        return array(
            'amount_paise' => $amountExclusive,
            'gst_paise' => $gstPaise,
            'total_paise' => $amountExclusive + $gstPaise,
            'list_amount_paise' => $listSplit['exclusive'],
            'list_gst_paise' => $listSplit['gst'],
            'list_total_paise' => $listSplit['total'],
            'discount_paise' => $discount,
            'registration_coupon_id' => isset($coupon['id']) ? (int) $coupon['id'] : null,
            'coupon_code' => $coupon['code'] ?? $couponCode,
        );
    }

    /**
     * @return array{exclusive:int,gst:int,total:int}
     */
    public function splitPlanPrice(array $plan, $billingCycle)
    {
        $gstRate = max(0, (float) $this->CI->config->item('gst_rate'));
        $inclusiveKey = $billingCycle === 'monthly'
            ? 'monthly_price_inclusive_paise'
            : 'annual_price_inclusive_paise';
        $exclusiveKey = $billingCycle === 'monthly'
            ? 'monthly_price_paise'
            : 'annual_price_paise';

        if (!empty($plan['price_includes_gst']) && !empty($plan[$inclusiveKey])) {
            $total = (int) $plan[$inclusiveKey];
            $exclusive = (int) round($total / (1 + $gstRate));
            $gst = max(0, $total - $exclusive);

            return array(
                'exclusive' => $exclusive,
                'gst' => $gst,
                'total' => $total,
            );
        }

        $exclusive = (int) ($plan[$exclusiveKey] ?? 0);
        $gst = (int) round($exclusive * $gstRate);

        return array(
            'exclusive' => $exclusive,
            'gst' => $gst,
            'total' => $exclusive + $gst,
        );
    }

    /**
     * Display list price in rupees (GST exclusive) for register UI.
     */
    public function displayPriceExclusive(array $plan, $billingCycle)
    {
        $split = $this->splitPlanPrice($plan, $billingCycle);

        return (int) round($split['exclusive'] / 100);
    }

    /** @deprecated Use displayPriceExclusive() */
    public function displayPriceInclusive(array $plan, $billingCycle)
    {
        return $this->displayPriceExclusive($plan, $billingCycle);
    }

    private function couponIsActive(array $coupon)
    {
        if (isset($coupon['is_active']) && !(int) $coupon['is_active']) {
            return false;
        }
        if (!empty($coupon['starts_at']) && strtotime($coupon['starts_at']) > time()) {
            return false;
        }
        if (!empty($coupon['ends_at']) && strtotime($coupon['ends_at']) < time()) {
            return false;
        }
        if (isset($coupon['max_redemptions']) && $coupon['max_redemptions'] !== null) {
            $used = (int) ($coupon['redemption_count'] ?? $coupon['redeemed_count'] ?? 0);
            if ($used >= (int) $coupon['max_redemptions']) {
                return false;
            }
        }

        return true;
    }

    private function discountPaiseFor(array $coupon, $listAmountPaise)
    {
        $type = strtolower((string) ($coupon['discount_type'] ?? 'percent'));
        if ($type === 'fixed' || $type === 'flat' || $type === 'amount') {
            return max(0, min($listAmountPaise, (int) ($coupon['discount_value'] ?? $coupon['amount_paise'] ?? 0)));
        }
        $percent = (float) ($coupon['discount_value'] ?? $coupon['percent'] ?? 0);

        return (int) round($listAmountPaise * max(0, min(100, $percent)) / 100);
    }
}
