<?php
namespace Brightfish\Turtle;

class Console {
    protected $noColour = false;
    protected $colours = array(
        'grey'   => '0;37',
        'red'    => '1;31',
        'green'  => '1;32',
        'yellow' => '1;33',
        'blue'   => '1;34',
        'white'  => '1;37',
    );
    const TEMPLATE = "\033[%sm%s\033[0m";

    public function __construct($noColour = false) {
        $this->noColour = $noColour;
    }

    public function format($input, $colour = '', $nl = true) {
        $output = $this->noColour
                ? $input
                : sprintf(self::TEMPLATE, $this->getColour($colour), $input);

        if ($nl) {
            $output .= PHP_EOL;
        }

        return $output;
    }

    protected function getColour($colourName) {
        return isset($this->colours[$colourName]) ? $this->colours[$colourName] : '';
    }
}
