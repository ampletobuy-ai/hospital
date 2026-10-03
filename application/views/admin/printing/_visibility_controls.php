<?php
$print_setting = !empty($printing_list[0]) ? $printing_list[0] : array();
// Explicit 0 = off; missing column/value defaults to on (legacy rows).
$show_header = ((int) ($print_setting['show_header'] ?? 1)) === 1;
$show_footer = ((int) ($print_setting['show_footer'] ?? 1)) === 1;
$blank_lines_before = max(0, min(50, (int) ($print_setting['blank_lines_before'] ?? 0)));
$blank_lines_after_header = max(0, min(50, (int) ($print_setting['blank_lines_after_header'] ?? 0)));

$visibility_title = $this->lang->line('print_visibility') ?: 'Print Visibility';
$header_label     = $this->lang->line('show_header_while_printing') ?: 'Show header while printing';
$footer_label     = $this->lang->line('show_footer_while_printing') ?: 'Show footer while printing';
$blank_before_label = $this->lang->line('blank_lines_before_print') ?: 'Blank lines before print';
$blank_after_label  = $this->lang->line('blank_lines_after_header') ?: 'Blank lines after header space';
?>
<div class="col-12">
    <div class="sh-form-card h-100 mb-0">
        <div class="sh-card-header">
            <span class="sh-card-header-title">
                <i class="fa fa-print me-1 opacity-75"></i><?php echo $visibility_title; ?>
            </span>
        </div>
        <div class="p-3">
            <div class="row g-3 align-items-end">
                <div class="col-md-6">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="show_header" id="show_header_cb" value="1" <?php echo $show_header ? 'checked="checked"' : ''; ?>>
                        <label class="form-check-label" for="show_header_cb"><?php echo $header_label; ?></label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="show_footer" id="show_footer_cb" value="1" <?php echo $show_footer ? 'checked="checked"' : ''; ?>>
                        <label class="form-check-label" for="show_footer_cb"><?php echo $footer_label; ?></label>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="blank_lines_before"><?php echo $blank_before_label; ?></label>
                    <input type="number" class="form-control" name="blank_lines_before" id="blank_lines_before"
                           min="0" max="50" step="1" value="<?php echo $blank_lines_before; ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="blank_lines_after_header"><?php echo $blank_after_label; ?></label>
                    <input type="number" class="form-control" name="blank_lines_after_header" id="blank_lines_after_header"
                           min="0" max="50" step="1" value="<?php echo $blank_lines_after_header; ?>">
                </div>
            </div>
        </div>
    </div>
</div>
