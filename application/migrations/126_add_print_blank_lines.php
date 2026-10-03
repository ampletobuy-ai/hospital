<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Add_print_blank_lines extends CI_Migration
{
    public function up()
    {
        if (!$this->db->field_exists('blank_lines_before', 'print_setting')) {
            $this->dbforge->add_column('print_setting', array(
                'blank_lines_before' => array(
                    'type'       => 'TINYINT',
                    'constraint' => 3,
                    'unsigned'   => true,
                    'null'       => false,
                    'default'    => 0,
                    'after'      => 'show_footer',
                ),
            ));
        }

        if (!$this->db->field_exists('blank_lines_after_header', 'print_setting')) {
            $this->dbforge->add_column('print_setting', array(
                'blank_lines_after_header' => array(
                    'type'       => 'TINYINT',
                    'constraint' => 3,
                    'unsigned'   => true,
                    'null'       => false,
                    'default'    => 0,
                    'after'      => 'blank_lines_before',
                ),
            ));
        }
    }

    public function down()
    {
        if ($this->db->field_exists('blank_lines_after_header', 'print_setting')) {
            $this->dbforge->drop_column('print_setting', 'blank_lines_after_header');
        }

        if ($this->db->field_exists('blank_lines_before', 'print_setting')) {
            $this->dbforge->drop_column('print_setting', 'blank_lines_before');
        }
    }
}
