<?php
/**
 * Mockery
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://github.com/padraic/mockery/master/LICENSE
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to padraic@php.net so we can send you a copy immediately.
 *
 * @category   Mockery
 * @package    Mockery
 * @subpackage UnitTests
 * @copyright  Copyright (c) 2010 Pádraic Brady (http://blog.astrumfutura.com)
 * @license    http://github.com/padraic/mockery/blob/master/LICENSE New BSD License
 */

namespace Mockery\Test\Generator\StringManipulation\Pass;

use Mockery as m;
use Mockery\Generator\DefinedTargetClass;
use Mockery\Generator\StringManipulation\Pass\MagicMethodTypeHintsPass;

class MagicMethodTypeHintsPassTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var MagicMethodTypeHintsPass
     */
    private $pass;

    /**
     * @var MockConfiguration
     */
    private $mockedConfiguration;

    /**
     * Setup method
     * @return void
     */
    public function setup()
    {
        $this->pass = new MagicMethodTypeHintsPass;
        $this->mockedConfiguration = m::mock(
            'Mockery\Generator\MockConfiguration'
        );
    }

    /**
     * @test
     */
    public function itShouldWork()
    {
        $this->assertTrue(true);
    }

    /**
     * @test
     */
    public function itShouldGrabClassMagicMethods()
    {
        $targetClass = DefinedTargetClass::factory(
            'Mockery\Test\Generator\StringManipulation\Pass\MagicDummy'
        );
        $magicMethods = $this->pass->getMagicMethods($targetClass);

        $this->assertCount(1, $magicMethods);
        $this->assertEquals('__isset', $magicMethods[0]->getName());
    }
}

class MagicDummy
{
    public function __isset(string $name) : boolean
    {
        return false;
    }

    public function nonMagicMethod()
    {
    }
}
