<?php
$current = isset($current) ? (int) $current : 1;
$skip_payment = !empty($skip_payment);
$steps = array(
    array('num' => 1, 'short' => 'Account', 'label' => 'Account', 'key' => 'account'),
    array('num' => 2, 'short' => 'Hospital', 'label' => 'Hospital & plan', 'key' => 'hospital'),
    array('num' => 3, 'short' => 'Pay', 'label' => 'Payment', 'key' => 'pay'),
    array('num' => 4, 'short' => 'Done', 'label' => 'Activate', 'key' => 'done'),
);
$total = $skip_payment ? 3 : 4;
// Normalize "done" for trial (logical 4 or display 3).
$highlight = $current;
if ($skip_payment && $current >= 3) {
    $highlight = 4; // activate Done step key
}
$meta_current = $skip_payment
    ? ($current >= 3 ? 3 : $current)
    : $current;
?>
<div class="portal-auth-steps-wrap<?php echo $skip_payment ? ' is-trial-flow' : ''; ?>" id="portalAuthSteps" data-skip-payment="<?php echo $skip_payment ? '1' : '0'; ?>">
    <p class="portal-auth-steps-meta">
        Step <span data-step-current><?php echo (int) $meta_current; ?></span>
        of
        <span data-step-total><?php echo (int) $total; ?></span>
    </p>
    <ol class="portal-auth-steps" aria-label="Registration steps">
        <?php foreach ($steps as $step):
            $isPay = ($step['key'] === 'pay');
            $state = '';
            if ($isPay && $skip_payment) {
                $state = 'is-skipped';
            } elseif ($step['num'] < $highlight) {
                $state = 'is-done';
            } elseif ($step['num'] === $highlight) {
                $state = 'is-active';
            }
            $indicator = $step['num'];
            if ($skip_payment && $step['key'] === 'done') {
                $indicator = 3;
            }
            if ($state === 'is-done') {
                $indicator = '✓';
            }
            ?>
            <li class="portal-auth-step <?php echo $state; ?>" data-step-key="<?php echo html_escape($step['key']); ?>" data-step-num="<?php echo (int) $step['num']; ?>"<?php echo ($isPay && $skip_payment) ? ' hidden' : ''; ?>>
                <span class="portal-auth-step__indicator"><?php echo $indicator; ?></span>
                <span class="portal-auth-step__short d-none d-sm-inline"><?php echo html_escape($step['short']); ?></span>
                <span class="portal-auth-step__label"><?php echo html_escape($step['label']); ?></span>
            </li>
        <?php endforeach; ?>
    </ol>
</div>
