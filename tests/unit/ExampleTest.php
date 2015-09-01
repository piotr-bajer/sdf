<?php

namespace SDF;

class ExampleTest extends \PHPUnit_Framework_TestCase
{
    use \Codeception\Specify;
    // use \Codeception\Verify;

    protected function setUp()
    {
        require_once '/Users/savery/Documents/sparksf/wordpress/wp-load.php';
        require_once 'sdf.php';
    }

    protected function tearDown()
    {
    }

    public function testMe() {
        $s = new \SDF();

        verify($s->get_cents(88.77))->equals(8877);
        verify($s->get_cents('88.77'))->equals(8877);
        verify($s->get_cents('$8,888.7'))->equals(888870);
        verify($s->get_cents(88.))->equals(8800);
        verify($s->get_cents('88.'))->equals(8800);
    }
}
