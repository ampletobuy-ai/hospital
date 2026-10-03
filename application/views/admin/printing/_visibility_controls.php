<?php
$print_setting = !empty($printing_list[0]) ? $printing_list[0] : array();
// Treat missing column as "on" (legacy rows); only an explicit 0 means off.
$show_header = ((int) ($print_setting['show_header'] ?? 1)) === 1;
$show_footer = ((int) ($print_setting['show_footer'] ?? 1)) === 1;

$visibility_title = $this->lang->line('print_visibility') ?: 'Print Visibility';
$header_label     = $this->lang->line('show_header_while_printing') ?: 'Show header while printing';
$footer_label     = $this->lang->line('show_footer_while_printing') ?: 'Show footer while printing';
?>
<div class="col-12">
    <div class="sh-form-card h-100 mb-0">
        <div class="sh-card-header">
            <span class="sh-card-header-title">
                <i class="fa fa-print me-1 opacity-75"></i><?php echo $visibility_title; ?>
            </span>
        </div>
        <div class="p-3">
            <div class="row g-3">
                <div class="col-md-6">
                    <!-- Single named field (hidden). Checkbox has no name so unchecked
                         never depends on browser omitting the key from multipart POST. -->
                    <input type="hidden" name="show_header" value="<?php echo $show_header ? '1' : '0'; ?>" class="js-print-vis-flag" data-flag="show_header">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input js-print-vis-toggle" id="show_header_cb" data-flag="show_header" <?php echo $show_header ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="show_header_cb"><?php echo $header_label; ?></label>
                    </div>
                </div>
                <div class="col-md-6">
                    <input type="hidden" name="show_footer" value="<?php echo $show_footer ? '1' : '0'; ?>" class="js-print-vis-flag" data-flag="show_footer">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input js-print-vis-toggle" id="show_footer_cb" data-flag="show_footer" <?php echo $show_footer ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="show_footer_cb"><?php echo $footer_label; ?></label>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    function syncPrintVisibilityFlags(root) {
        (root || document).querySelectorAll('.js-print-vis-toggle').forEach(function (cb) {
            var flag = cb.getAttribute('data-flag');
            var hidden = (root || document).querySelector('.js-print-vis-flag[data-flag="' + flag + '"]');
            if (hidden) {
                hidden.value = cb.checked ? '1' : '0';
            }
        });
    }
    document.addEventListener('change', function (e) {
        if (e.target && e.target.classList && e.target.classList.contains('js-print-vis-toggle')) {
            syncPrintVisibilityFlags(e.target.closest('form') || document);
        }
    });
    document.addEventListener('submit', function (e) {
        if (e.target && e.target.tagName === 'FORM') {
            syncPrintVisibilityFlags(e.target);
        }
    }, true);
    syncPrintVisibilityFlags(document);
})();
</script>
