<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Add_print_visibility extends CI_Migration
{
    public function up()
    {
        if (!$this->db->field_exists('show_header', 'print_setting')) {
            $this->dbforge->add_column('print_setting', array(
                'show_header' => array(
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'null'       => false,
                    'default'    => 1,
                    'after'      => 'print_footer',
                ),
            ));
        }

        if (!$this->db->field_exists('show_footer', 'print_setting')) {
            $this->dbforge->add_column('print_setting', array(
                'show_footer' => array(
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'null'       => false,
                    'default'    => 1,
                    'after'      => 'show_header',
                ),
            ));
        }
    }

    public function down()
    {
        if ($this->db->field_exists('show_footer', 'print_setting')) {
            $this->dbforge->drop_column('print_setting', 'show_footer');
        }

        if ($this->db->field_exists('show_header', 'print_setting')) {
            $this->dbforge->drop_column('print_setting', 'show_header');
        }
    }
}
