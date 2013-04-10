<?php
namespace Brightfish\Turtle;

class Console {
    protected $colours = array(
        'green'  => '1;32',
        'red'    => '1;31',
        'blue'   => '1;34',
        'white'  => '1;37',
        'grey'   => '0;37',
        'yellow' => '1;33',
    );

    public function format($input, $colour = '', $nl = true) {
        $output = sprintf(
            "\033[%sm%s\033[0m",
            isset($this->colours[$colour]) ? $this->colours[$colour] : '',
            $input
        );

        if ($nl) {
            $output .= PHP_EOL;
        }

        return $output;
    }
}