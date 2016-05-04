<?php
namespace xiaofeng;
require_once __DIR__ . "/iter/src/bootstrap.php";
use iter;

/**
 * Class Stream
 * @package xiaofeng
 * @author xiaofeng
 *
 * 第n版决定站在巨人的肩膀上~~(●'◡'●)不造轮子了
 * 其实可以用魔术方法__call做代理~ 但是不够直观
 * 且对IDE也不够友好(虽然可以加@method ~)
 *
 * TODO:
 * 1. 补全phpdoc
 * 2. 补全example
 */
class Stream
{
    /**
     * @var array
     */
    private $opQueue = [];
    /**
     * @var array|\Traversable
     */
    private $iterable = null;
    /**
     * @var bool
     */
    private $isClosed = false;

    /**
     * @param array|\Traversable $iterable
     * @return static
     * @throws \InvalidArgumentException
     * @example
     *
     *      $array = range(0, 9);
     *      Stream::of($array);
     *
     *      $generator = function() {
     *          for($i = 0; $i < 10; $i++) yield $i;
     *      };
     *      Stream::of($generator());
     *
     *      $arrayIter = new \ArrayIterator($array);
     *      Stream::of($arrayIter);
     *
     */
    public static function of($iterable) {
        /*
        if($iterable instanceof \Closure) {
            $iterable = $iterable();
        }
        */
        iter\_assertIterable($iterable, "First Argument");
        return new static($iterable);
    }

    /**
     * @deprecated
     * @return static
     * @throws \InvalidArgumentException
     */
    public static function __from(/* ...$iterables */) {
        $iterables = func_get_args();
        iter\_assertAllIterable($iterables);
        return new static($iterables);
    }

    /**
     * @return static
     * @example
     *
     *      Stream::from(0, 1, 2, 3, 4, 5, 6, 7, 8, 9);
     *      Stream::from([0, 1, 2, 3], [4, 5, 6], [7, 8, 9]);
     *      Stream::from($array, $generator(), $arrayIter);
     */
    public static function from() {
        return new static(func_get_args());
    }

    /**
     * @param number $start
     * @param number $end
     * @param number $step
     * @return static
     * @throws \InvalidArgumentException
     */
    public static function range($start, $end, $step = null) {
        return self::of(iter\range($start, $end, $step));
    }

    /**
     * @param $value
     * @param $num
     * @return static
     * @throws \InvalidArgumentException
     */
    public static function repeat($value, $num = INF) {
        return self::of(iter\repeat($value, $num));
    }

    /**
     * @return static
     */
    public static function create() {
        return new static(null);
    }

    /**
     * Stream constructor.
     * @param array|\Traversable $iterable
     */
    private function __construct($iterable) {
        $this->iterable = $iterable;
    }

    /**
     * @param bool $end
     * @throws \RuntimeException, \InvalidArgumentException
     */
    private function checkAndSetEnd($end = false) {
        if($this->isClosed === true) {
            throw new \RuntimeException("Cannot traverse an already closed stream");
        }
        $this->isClosed = $end;
    }

    /**
     * @param $userfn
     * @param array|\Traversable $iterable
     * @return \Generator
     */
    private function _peek(/*callable */$userfn, $iterable) {
        _assertCallable($userfn, "First Argument");
        iter\_assertIterable($iterable, 'Second argument');
        foreach ($iterable as $key => $value) {
            // $userfn($value, $key);
            call_user_func($userfn, $value, $key);
            yield $key => $value;
        }
    }

    /**
     * @param $peeker
     * @return $this
     * @throws \Exception
     */
    public function peek(/*callable*/$peeker) {
        _assertCallable($peeker, "First Argument");
        $this->checkAndSetEnd();
        $this->opQueue[] = [[$this, "_peek"], $peeker, null];
        return $this;
    }

    /**
     * @param $mapper
     * @return $this
     * @throws \Exception
     */
    public function map(/*callable*/$mapper) {
        _assertCallable($mapper, "First Argument");
        $this->checkAndSetEnd();
        $this->opQueue[] = ["iter\\map", $mapper, null];
        return $this;
    }

    /**
     * @param $mapKeyser
     * @return $this
     * @throws \Exception
     */
    public function mapKeys(/*callable*/$mapKeyser) {
        _assertCallable($mapKeyser, "First Argument");
        $this->checkAndSetEnd();
        $this->opQueue[] = ["iter\\mapKeys", $mapKeyser, null];
        return $this;
    }

    /**
     * @param $reindexer
     * @return $this
     * @throws \Exception
     */
    public function reindex(/*callable*/$reindexer) {
        _assertCallable($reindexer, "First Argument");
        $this->checkAndSetEnd();
        $this->opQueue[] = ["iter\\reindex", $reindexer, null];
        return $this;
    }

    /**
     * @param $applyer
     * @return \Closure
     * @throws \Exception
     */
    public function apply(/*callable*/$applyer) {
        _assertCallable($applyer, "First Argument");
        $this->checkAndSetEnd(true);
        $this->opQueue[] = ["iter\\apply", $applyer, null];
        return $this->_then(function() {
            return $this->_execute();
        });
    }

    /**
     * @param $predicate
     * @return $this
     * @throws \Exception
     */
    public function filter(/*callable*/$predicate) {
        _assertCallable($predicate, "First Argument");
        $this->checkAndSetEnd();
        $this->opQueue[] = ["iter\\filter", $predicate, null];
        return $this;
    }

    /**
     * @param $predicate
     * @return Stream
     */
    public function where(/*callable*/$predicate) {
        _assertCallable($predicate, "First Argument");
        return $this->filter($predicate);
    }

    /**
     * @param $reducer
     * @param null $initial
     * @return \Closure
     * @throws \Exception
     */
    public function reduce(/*callable*/$reducer, $initial = null) {
        _assertCallable($reducer, "First Argument");
        $this->checkAndSetEnd(true);
        $this->opQueue[] = ["iter\\reduce", $reducer, $initial];
        return $this->_then(function() {
            return $this->_execute();
        });
    }

    /**
     * @param $reductionser
     * @param null $initial
     * @return $this
     * @throws \Exception
     */
    public function reductions(/*callable*/$reductionser, $initial = null) {
        _assertCallable($reductionser, "First Argument");
        $this->checkAndSetEnd();
        $this->opQueue[] = ["iter\\reductions", $reductionser, $initial];
        return $this;
    }

    /**
     * @param array|\Traversable $iterable
     * @param array $iterables
     * @return mixed
     */
    private function _zip($iterable, array $iterables) {
        array_unshift($iterables, $iterable);
        // return iter\zip(...$iterable);
        return call_user_func_array("iter\\zip", $iterables);
    }

    /**
     * @return $this
     * @throws \Exception
     */
    public function zip(/* ...$iterables */) {
        $this->checkAndSetEnd();
        $iterables = func_get_args();
        $this->opQueue[] = [[$this, "_zip"], null, $iterables];
        return $this;
    }

    /**
     * @param array|\Traversable $iterable
     * @param $keys
     * @return mixed
     */
    private function _zipKey($iterable, $keys) {
        return iter\zipKeyValue($keys, $iterable);
    }

    /**
     * @param $keys
     * @return $this
     * @throws \Exception
     */
    public function zipKey($keys) {
        $this->checkAndSetEnd();
        $this->opQueue[] = [[$this, "_zipKey"], null, $keys];
        return $this;
    }

    /**
     * @param array|\Traversable $iterable
     * @param $values
     * @return mixed
     */
    public function _zipValue($iterable, $values) {
        return iter\zipKeyValue($iterable, $values);
    }

    /**
     * @param $values
     * @return $this
     * @throws \Exception
     */
    public function zipValue($values) {
        $this->checkAndSetEnd();
        $this->opQueue[] = [[$this, "_zipValue"], null, $values];
        return $this;
    }

    /**
     * @param array|\Traversable $iterable
     * @param array $iterables
     * @return mixed
     */
    private function _chain($iterable, array $iterables) {
        array_unshift($iterables, $iterable);
        // return iter\chain(...$iterable);
        return call_user_func_array("iter\\chain", $iterables);
    }

    /**
     * @return $this
     * @throws \Exception
     */
    public function chain(/* ...$iterables */) {
        $this->checkAndSetEnd();
        $iterables = func_get_args();
        $this->opQueue[] = [[$this, "_chain"], null, $iterables];
        return $this;
    }

    /**
     * @param array|\Traversable $iterable
     * @param array $iterables
     * @return mixed
     */
    private function _product($iterable, array $iterables) {
        array_unshift($iterables, $iterable);
        // return iter\product(...$iterable);
        return call_user_func_array("iter\\product", $iterables);
    }

    /**
     * @return $this
     * @throws \Exception
     */
    public function product(/* ...$iterables */) {
        $this->checkAndSetEnd();
        $iterables = func_get_args();
        $this->opQueue[] = [[$this, "_product"], null, $iterables];
        return $this;
    }

    /**
     * @param array|\Traversable $iterable
     * @param array $args
     * @return mixed
     */
    private function _slice($iterable, array $args) {
        list($start, $length) = $args;
        return iter\slice($iterable, $start, $length);
    }

    /**
     * @param $start
     * @param $length
     * @return $this
     * @throws \Exception
     */
    public function slice($start, $length = INF) {
        $this->checkAndSetEnd();
        $this->opQueue[] = [[$this, "_slice"], null, [$start, $length]];
        return $this;
    }

    /**
     * @param $num
     * @return Stream
     */
    public function take($num) {
        return $this->slice(0, $num);
    }

    /**
     * @param $num
     * @return Stream
     */
    public function drop($num) {
        return $this->slice($num);
    }

    /**
     * @return $this
     * @throws \Exception
     */
    public function keys() {
        $this->checkAndSetEnd();
        $this->opQueue[] = ["iter\\keys", null, null];
        return $this;
    }

    /**
     * @return $this
     * @throws \Exception
     */
    public function values() {
        $this->checkAndSetEnd();
        $this->opQueue[] = ["iter\\values", null, null];
        return $this;
    }

    /**
     * @param $predicate
     * @return \Closure
     * @throws \Exception
     */
    public function any(/*callable*/$predicate) {
        _assertCallable($predicate, "First Argument");
        $this->checkAndSetEnd(true);
        $this->opQueue[] = ["iter\\any", $predicate, null];
        return $this->_then(function() {
            return $this->_execute();
        });
    }

    /**
     * @param $predicate
     * @return \Closure
     * @throws \Exception
     */
    public function all(/*callable*/$predicate) {
        _assertCallable($predicate, "First Argument");
        $this->checkAndSetEnd(true);
        $this->opQueue[] = ["iter\\all", $predicate, null];
        return $this->_then(function() {
            return $this->_execute();
        });
    }

    /**
     * @param $predicate
     * @return \Closure
     * @throws \Exception
     */
    public function findFirst(/*callable*/$predicate) {
        _assertCallable($predicate, "First Argument");
        $this->checkAndSetEnd(true);
        $this->opQueue[] = ["iter\\search", $predicate, null];
        return $this->_then(function() {
            return $this->_execute();
        });
    }

    /**
     * @param $predicate
     * @return $this
     * @throws \Exception
     */
    public function takeWhile(/*callable*/$predicate) {
        _assertCallable($predicate, "First Argument");
        $this->checkAndSetEnd();
        $this->opQueue[] = ["iter\\takeWhile", $predicate, null];
        return $this;
    }

    /**
     * @param $predicate
     * @return $this
     * @throws \Exception
     */
    public function dropWhile(/*callable*/$predicate) {
        _assertCallable($predicate, "First Argument");
        $this->checkAndSetEnd();
        $this->opQueue[] = ["iter\\dropWhile", $predicate, null];
        return $this;
    }

    /**
     * @param array|\Traversable $iterable
     * @param $maxLevel
     * @param int $level
     * @return \Generator
     */
    private function _flatten($iterable, $maxLevel, $level = 1) {
        iter\_assertIterable($iterable, 'First argument');
        foreach ($iterable as $value) {
            if (iter\isIterable($value)) {
                if($level > $maxLevel) {
                    yield $value;
                } else {
                    foreach ($this->_flatten($value, $maxLevel, $level + 1) as $v) {
                        yield $v;
                    }
                }
            } else {
                yield $value;
            }
        }
    }

    /**
     * @param int $maxLevel
     * @return $this
     * @throws \Exception
     */
    public function flatten($maxLevel = 1) {
        $this->checkAndSetEnd();
        if($maxLevel === 0) {
            // recursion flatten
            $this->opQueue[] = ["iter\\flatten", null, null];
        } else {
            $this->opQueue[] = [[$this, "_flatten"], null, $maxLevel];
        }
        return $this;
    }

    /**
     * @param $predicate
     * @param int $maxLevel
     * @return $this
     */
    public function flatMap(/*callable*/$predicate, $maxLevel = 1) {
        _assertCallable($predicate, "First Argument");
        $this->checkAndSetEnd();
        if($maxLevel === 0) {
            // recursion flatten
            $this->opQueue[] = ["iter\\flatten", null, null];
        } else {
            $this->opQueue[] = [[$this, "_flatten"], null, $maxLevel];
        }
        $this->opQueue[] = ["iter\\map", $predicate, null];
        return $this;
    }

    /**
     * @return $this
     * @throws \Exception
     */
    public function flip() {
        $this->checkAndSetEnd();
        $this->opQueue[] = ["iter\\flip", null, null];
        return $this;
    }

    /**
     * @param $size
     * @return $this
     * @throws \Exception
     */
    public function chunk($size) {
        $this->checkAndSetEnd();
        $this->opQueue[] = ["iter\\chunk", null, $size];
        return $this;
    }

    /**
     * @param array|\Traversable $iterable
     * @param $separator
     * @return string
     */
    private function _join($iterable, $separator) {
        return iter\join($separator, $iterable);
    }

    /**
     * @param string $separator
     * @return \Closure|string
     * @throws \Exception
     */
    public function join($separator = "") {
        $this->checkAndSetEnd(true);
        $this->opQueue[] = [[$this, "_join"], null, $separator];
        return $this->_then(function() {
            return $this->_execute();
        });
    }

    /**
     * @return \Closure
     * @throws \Exception
     */
    public function count() {
        $this->checkAndSetEnd(true);
        $this->opQueue[] = ["iter\\count", null, null];
        return $this->_then(function() {
            return $this->_execute();
        });
    }

    /**
     * @return \Closure
     * @throws \Exception
     */
    public function toIter() {
        $this->checkAndSetEnd(true);
        return $this->_then(function() {
            return iter\toIter($this->_execute());
        });
    }

    /**
     * @return \Closure
     * @throws \Exception
     */
    public function toArray() {
        $this->checkAndSetEnd(true);
        return $this->_then(function() {
            return iter\toArray($this->_execute());
        });
    }

    /**
     * @return \Closure
     * @throws \Exception
     */
    public function toArrayWithKeys() {
        $this->checkAndSetEnd(true);
        return $this->_then(function() {
            return iter\toArrayWithKeys($this->_execute());
        });
    }

    /**
     * @return null
     */
    private function _execute() {
        $iterable = $this->iterable;
        foreach ($this->opQueue as list($iterfn, $userfn, $arg)) {
            if($userfn == null) {
                $iterable = $iterfn($iterable, $arg);
            } else {
                $iterable = $iterfn($userfn, $iterable, $arg);
            }
        }
        return $iterable;
    }

    /**
     * @param \Closure $fn
     * @return \Closure|mixed
     */
    private function _then(\Closure $fn) {
        if($this->iterable === null) {
            return $this->_build(function() use($fn) {
                return $fn();
            });
        } else {
            return $fn();
        }
    }

    /**
     * @param \Closure $fn
     * @return \Closure
     */
    private function _build(\Closure $fn) {
        /**
         * @param array|\Traversable $iterable
         */
        return function($iterable) use($fn) {
            iter\_assertIterable($iterable, "First Argument");
            $this->iterable = $iterable;
            return $fn();
        };
    }

    /**
     * @return \Closure|string
     */
    public function __toString() {
        return $this->join(", ");
    }
}

/**
 * @internal
 * @access private
 * @param mixed $var
 * @param string $what
 * @throws \InvalidArgumentException
 */
function _assertCallable($var, $what) {
    if(!is_callable($var)) {
        throw new \InvalidArgumentException("$what should be callable");
    }
}
