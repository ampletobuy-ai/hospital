<?php
$print_setting = !empty($printing_list[0]) ? $printing_list[0] : array();
$show_header   = !isset($print_setting['show_header']) || (int) $print_setting['show_header'] === 1;
$show_footer   = !isset($print_setting['show_footer']) || (int) $print_setting['show_footer'] === 1;

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
            <div class="row">
                <div class="col-md-6">
                    <label class="checkbox-inline">
                        <input type="checkbox" name="show_header" value="1" <?php echo $show_header ? 'checked' : ''; ?>>
                        <?php echo $header_label; ?>
                    </label>
                </div>
                <div class="col-md-6">
                    <label class="checkbox-inline">
                        <input type="checkbox" name="show_footer" value="1" <?php echo $show_footer ? 'checked' : ''; ?>>
                        <?php echo $footer_label; ?>
                    </label>
                </div>
            </div>
        </div>
    </div>
</div>
