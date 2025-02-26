<?php
/**
 * Mockery
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://github.com/padraic/mockery/blob/master/LICENSE
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to padraic@php.net so we can send you a copy immediately.
 *
 * @category   Mockery
 * @package    Mockery
 * @copyright  Copyright (c) 2010 Pádraic Brady (http://blog.astrumfutura.com)
 * @license    http://github.com/padraic/mockery/blob/master/LICENSE New BSD License
 */

namespace Mockery;

use Closure;
use Mockery\Matcher\MultiArgumentClosure;

class Expectation implements ExpectationInterface
{
    /**
     * Mock object to which this expectation belongs
     *
     * @var object
     */
    protected $_mock = null;

    /**
     * Method name
     *
     * @var string
     */
    protected $_name = null;

    /**
     * Arguments expected by this expectation
     *
     * @var array
     */
    protected $_expectedArgs = array();

    /**
     * Count validator store
     *
     * @var array
     */
    protected $_countValidators = array();

    /**
     * The count validator class to use
     *
     * @var string
     */
    protected $_countValidatorClass = 'Mockery\CountValidator\Exact';

    /**
     * Actual count of calls to this expectation
     *
     * @var int
     */
    protected $_actualCount = 0;

    /**
     * Value to return from this expectation
     *
     * @var mixed
     */
    protected $_returnValue = null;

    /**
     * Array of return values as a queue for multiple return sequence
     *
     * @var array
     */
    protected $_returnQueue = array();

    /**
     * Array of closures executed with given arguments to generate a result
     * to be returned
     *
     * @var array
     */
    protected $_closureQueue = array();

    /**
     * Array of values to be set when this expectation matches
     *
     * @var array
     */
    protected $_setQueue = array();

    /**
     * Integer representing the call order of this expectation
     *
     * @var int
     */
    protected $_orderNumber = null;

    /**
     * Integer representing the call order of this expectation on a global basis
     *
     * @var int
     */
    protected $_globalOrderNumber = null;

    /**
     * Flag indicating that an exception is expected to be throw (not returned)
     *
     * @var bool
     */
    protected $_throw = false;

    /**
     * Flag indicating whether the order of calling is determined locally or
     * globally
     *
     * @var bool
     */
    protected $_globally = false;

    /**
     * Flag indicating we expect no arguments
     *
     * @var bool
     */
    protected $_noArgsExpectation = false;

    /**
     * Flag indicating if the return value should be obtained from the original
     * class method instead of returning predefined values from the return queue
     *
     * @var bool
     */
    protected $_passthru = false;

    /**
     * Constructor
     *
     * @param \Mockery\MockInterface $mock
     * @param string $name
     */
    public function __construct(\Mockery\MockInterface $mock, $name)
    {
        $this->_mock = $mock;
        $this->_name = $name;
    }

    /**
     * Return a string with the method name and arguments formatted
     *
     * @param string $name Name of the expected method
     * @param array $args List of arguments to the method
     * @return string
     */
    public function __toString()
    {
        return \Mockery::formatArgs($this->_name, $this->_expectedArgs);
    }

    /**
     * Verify the current call, i.e. that the given arguments match those
     * of this expectation
     *
     * @param array $args
     * @return mixed
     */
    public function verifyCall(array $args)
    {
        $this->validateOrder();
        $this->_actualCount++;
        if (true === $this->_passthru) {
            return $this->_mock->mockery_callSubjectMethod($this->_name, $args);
        }
        $return = $this->_getReturnValue($args);
        if ($return instanceof \Exception && $this->_throw === true) {
            throw $return;
        }
        $this->_setValues();
        return $return;
    }

    /**
     * Sets public properties with queued values to the mock object
     *
     * @param array $args
     * @return mixed
     */
    protected function _setValues()
    {
        foreach ($this->_setQueue as $name => &$values) {
            if (count($values) > 0) {
                $value = array_shift($values);
                $this->_mock->{$name} = $value;
            }
        }
    }

    /**
     * Fetch the return value for the matching args
     *
     * @param array $args
     * @return mixed
     */
    protected function _getReturnValue(array $args)
    {
        if (count($this->_closureQueue) > 1) {
            return call_user_func_array(array_shift($this->_closureQueue), $args);
        } elseif (count($this->_closureQueue) > 0) {
            return call_user_func_array(current($this->_closureQueue), $args);
        } elseif (count($this->_returnQueue) > 1) {
            return array_shift($this->_returnQueue);
        } elseif (count($this->_returnQueue) > 0) {
            return current($this->_returnQueue);
        }

        return $this->_mock->mockery_returnValueForMethod($this->_name);
    }

    /**
     * Checks if this expectation is eligible for additional calls
     *
     * @return bool
     */
    public function isEligible()
    {
        foreach ($this->_countValidators as $validator) {
            if (!$validator->isEligible($this->_actualCount)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if there is a constraint on call count
     *
     * @return bool
     */
    public function isCallCountConstrained()
    {
        return (count($this->_countValidators) > 0);
    }

    /**
     * Verify call order
     *
     * @return void
     */
    public function validateOrder()
    {
        if ($this->_orderNumber) {
            $this->_mock->mockery_validateOrder((string) $this, $this->_orderNumber, $this->_mock);
        }
        if ($this->_globalOrderNumber) {
            $this->_mock->mockery_getContainer()
                ->mockery_validateOrder((string) $this, $this->_globalOrderNumber, $this->_mock);
        }
    }

    /**
     * Verify this expectation
     *
     * @return bool
     */
    public function verify()
    {
        foreach ($this->_countValidators as $validator) {
            $validator->validate($this->_actualCount);
        }
    }

    /**
     * Check if the registered expectation is a MultiArgumentClosureExpectation.
     * @return bool
     */
    private function isMultiArgumentClosureExpectation()
    {
        return (count((array) $this->_expectedArgs) === 1 && ($this->_expectedArgs[0] instanceof \Mockery\Matcher\MultiArgumentClosure));
    }

    /**
     * Check if passed arguments match an argument expectation
     *
     * @param array $args
     * @return bool
     */
    public function matchArgs(array $args)
    {
        if (empty($this->_expectedArgs) && !$this->_noArgsExpectation) {
            return true;
        }
        if ($this->isMultiArgumentClosureExpectation()) {
            return $this->_matchArg($this->_expectedArgs[0], $args);
        }
        $argCount = count((array) $args);
        if ($argCount !== count((array) $this->_expectedArgs)) {
            return false;
        }
        for ($i=0; $i<$argCount; $i++) {
            $param =& $args[$i];
            if (!$this->_matchArg($this->_expectedArgs[$i], $param)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if passed argument matches an argument expectation
     *
     * @param mixed $expected
     * @param mixed &$actual
     * @return bool
     */
    protected function _matchArg($expected, &$actual)
    {
        if ($expected === $actual) {
            return true;
        }
        if (!is_object($expected) && !is_object($actual) && $expected == $actual) {
            return true;
        }
        if (is_string($expected) && !is_array($actual) && !is_object($actual)) {
            # push/pop an error handler here to to make sure no error/exception thrown if $expected is not a regex
            set_error_handler(function () {});
            $result = preg_match($expected, (string) $actual);
            restore_error_handler();

            if ($result) {
                return true;
            }
        }
        if (is_string($expected) && is_object($actual)) {
            $result = $actual instanceof $expected;
            if ($result) {
                return true;
            }
        }
        if ($expected instanceof \Mockery\Matcher\MatcherAbstract) {
            return $expected->match($actual);
        }
        if ($expected instanceof \Hamcrest\Matcher || $expected instanceof \Hamcrest_Matcher) {
            return $expected->matches($actual);
        }
        return false;
    }

    /**
     * Expected argument setter for the expectation
     *
     * @param mixed ...
     * @return self
     */
    public function with()
    {
        return $this->withArgs(func_get_args());
    }

    /**
     * Expected arguments for the expectation passed as an array
     *
     * @param array $arguments
     * @return self
     */
    private function withArgsInArray(array $arguments)
    {
        if (empty($arguments)) {
            return $this->withNoArgs();
        }
        $this->_expectedArgs = $arguments;
        $this->_noArgsExpectation = false;
        return $this;
    }

    /**
     * Expected arguments have to be matched by the given closure.
     *
     * @param Closure $closure
     * @return self
     */
    private function withArgsMatchedByClosure(Closure $closure)
    {
        $this->_expectedArgs = [new MultiArgumentClosure($closure)];
        $this->_noArgsExpectation = false;
        return $this;
    }

    /**
     * Expected arguments for the expectation passed as an array or a closure that matches each passed argument on
     * each function call.
     *
     * @param array|Closure $argsOrClosure
     * @return self
     */
    public function withArgs($argsOrClosure)
    {
        if (is_array($argsOrClosure)) {
            $this->withArgsInArray($argsOrClosure);
        } elseif ($argsOrClosure instanceof Closure) {
            $this->withArgsMatchedByClosure($argsOrClosure);
        } else {
            throw new \InvalidArgumentException(sprintf('Call to %s with an invalid argument (%s), only array and '.
                'closure are allowed', __METHOD__, $argsOrClosure));
        }
        return $this;
    }

    /**
     * Set with() as no arguments expected
     *
     * @return self
     */
    public function withNoArgs()
    {
        $this->_noArgsExpectation = true;
        $this->_expectedArgs = null;
        return $this;
    }

    /**
     * Set expectation that any arguments are acceptable
     *
     * @return self
     */
    public function withAnyArgs()
    {
        $this->_expectedArgs = array();
        return $this;
    }

    /**
     * Set a return value, or sequential queue of return values
     *
     * @param mixed ...
     * @return self
     */
    public function andReturn()
    {
        $this->_returnQueue = func_get_args();
        return $this;
    }

    /**
     * Return this mock, like a fluent interface
     *
     * @return self
     */
    public function andReturnSelf()
    {
        return $this->andReturn($this->_mock);
    }

    /**
     * Set a sequential queue of return values with an array
     *
     * @param array $values
     * @return self
     */
    public function andReturnValues(array $values)
    {
        call_user_func_array(array($this, 'andReturn'), $values);
        return $this;
    }

    /**
     * Set a closure or sequence of closures with which to generate return
     * values. The arguments passed to the expected method are passed to the
     * closures as parameters.
     *
     * @param callable ...
     * @return self
     */
    public function andReturnUsing()
    {
        $this->_closureQueue = func_get_args();
        return $this;
    }

    /**
     * Return a self-returning black hole object.
     *
     * @return self
     */
    public function andReturnUndefined()
    {
        $this->andReturn(new \Mockery\Undefined);
        return $this;
    }

    /**
     * Return null. This is merely a language construct for Mock describing.
     *
     * @return self
     */
    public function andReturnNull()
    {
        return $this;
    }

    public function andReturnFalse()
    {
        return $this->andReturn(false);
    }

    public function andReturnTrue()
    {
        return $this->andReturn(true);
    }

    /**
     * Set Exception class and arguments to that class to be thrown
     *
     * @param string|\Exception $exception
     * @param string $message
     * @param int $code
     * @param \Exception $previous
     * @return self
     */
    public function andThrow($exception, $message = '', $code = 0, \Exception $previous = null)
    {
        $this->_throw = true;
        if (is_object($exception)) {
            $this->andReturn($exception);
        } else {
            $this->andReturn(new $exception($message, $code, $previous));
        }
        return $this;
    }

    /**
     * Set Exception classes to be thrown
     *
     * @param array $exceptions
     * @return self
     */
    public function andThrowExceptions(array $exceptions)
    {
        $this->_throw = true;
        foreach ($exceptions as $exception) {
            if (!is_object($exception)) {
                throw new Exception('You must pass an array of exception objects to andThrowExceptions');
            }
        }
        return $this->andReturnValues($exceptions);
    }

    /**
     * Register values to be set to a public property each time this expectation occurs
     *
     * @param string $name
     * @param mixed $value
     * @return self
     */
    public function andSet($name, $value)
    {
        $values = func_get_args();
        array_shift($values);
        $this->_setQueue[$name] = $values;
        return $this;
    }

    /**
     * Alias to andSet(). Allows the natural English construct
     * - set('foo', 'bar')->andReturn('bar')
     *
     * @param string $name
     * @param mixed $value
     * @return self
     */
    public function set($name, $value)
    {
        return call_user_func_array(array($this, 'andSet'), func_get_args());
    }

    /**
     * Indicates this expectation should occur zero or more times
     *
     * @return self
     */
    public function zeroOrMoreTimes()
    {
        $this->atLeast()->never();
    }

    /**
     * Indicates the number of times this expectation should occur
     *
     * @param int $limit
     * @throws InvalidArgumentException
     * @return self
     */
    public function times($limit = null)
    {
        if (is_null($limit)) {
            return $this;
        }
        if (!is_int($limit)) {
            throw new \InvalidArgumentException('The passed Times limit should be an integer value');
        }
        $this->_countValidators[] = new $this->_countValidatorClass($this, $limit);
        $this->_countValidatorClass = 'Mockery\CountValidator\Exact';
        return $this;
    }

    /**
     * Indicates that this expectation is never expected to be called
     *
     * @return self
     */
    public function never()
    {
        return $this->times(0);
    }

    /**
     * Indicates that this expectation is expected exactly once
     *
     * @return self
     */
    public function once()
    {
        return $this->times(1);
    }

    /**
     * Indicates that this expectation is expected exactly twice
     *
     * @return self
     */
    public function twice()
    {
        return $this->times(2);
    }

    /**
     * Sets next count validator to the AtLeast instance
     *
     * @return self
     */
    public function atLeast()
    {
        $this->_countValidatorClass = 'Mockery\CountValidator\AtLeast';
        return $this;
    }

    /**
     * Sets next count validator to the AtMost instance
     *
     * @return self
     */
    public function atMost()
    {
        $this->_countValidatorClass = 'Mockery\CountValidator\AtMost';
        return $this;
    }

    /**
     * Shorthand for setting minimum and maximum constraints on call counts
     *
     * @param int $minimum
     * @param int $maximum
     */
    public function between($minimum, $maximum)
    {
        return $this->atLeast()->times($minimum)->atMost()->times($maximum);
    }

    /**
     * Indicates that this expectation must be called in a specific given order
     *
     * @param string $group Name of the ordered group
     * @return self
     */
    public function ordered($group = null)
    {
        if ($this->_globally) {
            $this->_globalOrderNumber = $this->_defineOrdered($group, $this->_mock->mockery_getContainer());
        } else {
            $this->_orderNumber = $this->_defineOrdered($group, $this->_mock);
        }
        $this->_globally = false;
        return $this;
    }

    /**
     * Indicates call order should apply globally
     *
     * @return self
     */
    public function globally()
    {
        $this->_globally = true;
        return $this;
    }

    /**
     * Setup the ordering tracking on the mock or mock container
     *
     * @param string $group
     * @param object $ordering
     * @return int
     */
    protected function _defineOrdered($group, $ordering)
    {
        $groups = $ordering->mockery_getGroups();
        if (is_null($group)) {
            $result = $ordering->mockery_allocateOrder();
        } elseif (isset($groups[$group])) {
            $result = $groups[$group];
        } else {
            $result = $ordering->mockery_allocateOrder();
            $ordering->mockery_setGroup($group, $result);
        }
        return $result;
    }

    /**
     * Return order number
     *
     * @return int
     */
    public function getOrderNumber()
    {
        return $this->_orderNumber;
    }

    /**
     * Mark this expectation as being a default
     *
     * @return self
     */
    public function byDefault()
    {
        $director = $this->_mock->mockery_getExpectationsFor($this->_name);
        if (!empty($director)) {
            $director->makeExpectationDefault($this);
        }
        return $this;
    }

    /**
     * Return the parent mock of the expectation
     *
     * @return \Mockery\MockInterface
     */
    public function getMock()
    {
        return $this->_mock;
    }

    /**
     * Flag this expectation as calling the original class method with the
     * any provided arguments instead of using a return value queue.
     *
     * @return self
     */
    public function passthru()
    {
        if ($this->_mock instanceof Mock) {
            throw new Exception(
                'Mock Objects not created from a loaded/existing class are '
                . 'incapable of passing method calls through to a parent class'
            );
        }
        $this->_passthru = true;
        return $this;
    }

    /**
     * Cloning logic
     *
     */
    public function __clone()
    {
        $newValidators = array();
        $countValidators = $this->_countValidators;
        foreach ($countValidators as $validator) {
            $newValidators[] = clone $validator;
        }
        $this->_countValidators = $newValidators;
    }

    public function getName()
    {
        return $this->_name;
    }
}
