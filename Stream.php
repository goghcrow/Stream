<?php
namespace xiaofeng;
require_once __DIR__ . "/iter/src/bootstrap.php";
use \iter;

/**
 * Class Stream
 * @package xiaofeng
 * @author xiaofeng
 *
 * 第n版决定站在巨人的肩膀上~~(●'◡'●)不造轮子了
 * 其实可以用魔术方法__call做代理~ 但是不够直观
 * 且对IDE也不够友好(虽然可以加@method ~)
 */
class Stream
{
    private $opQueue = [];
    private $iterable = null;
    private $isClosed = false;
    private $isDebug = false;

    public static function of($iterable) {
        if($iterable instanceof \Closure) {
            $iterable = $iterable();
        }
        iter\_assertIterable($iterable, "First Argument");
        return new static($iterable);
    }

    public static function from(/* ...$iterables */) {
        $iterables = func_get_args();
        iter\_assertAllIterable($iterables);
        return new static($iterables);
    }

    public static function range($start, $end, $step = null) {
        return self::of(iter\range($start, $end, $step));
    }

    public static function repeat($value, $num = INF) {
        return self::of(iter\repeat($value, $num));
    }

    private function __construct($iterable) {
        $this->iterable = $iterable;
    }

    private function checkAndSetEnd($end = false) {
        if($this->isClosed === true) {
            throw new \Exception("Cannot traverse an already closed stream");
        }
        $this->isClosed = $end;
    }

    private function _peek(callable $userfn, $iterable) {
        iter\_assertIterable($iterable, 'Second argument');
        foreach ($iterable as $key => $value) {
            $userfn($value, $key);
            yield $key => $value;
        }
    }

    public function debug($isDebug = true) {
        $this->isDebug = $isDebug;
        return $this;
    }

    public function peek(callable $peeker) {
        $this->checkAndSetEnd();
        if($this->isDebug) {
            $this->opQueue[] = [[$this, "_peek"], $peeker, null];
        }
        return $this;
    }

    public function map(callable $mapper) {
        $this->checkAndSetEnd();
        $this->opQueue[] = ["iter\\map", $mapper, null];
        return $this;
    }

    public function mapKeys(callable $mapKeyser) {
        $this->checkAndSetEnd();
        $this->opQueue[] = ["iter\\mapKeys", $mapKeyser, null];
        return $this;
    }

    public function reindex(callable $reindexer) {
        $this->checkAndSetEnd();
        $this->opQueue[] = ["iter\\reindex", $reindexer, null];
        return $this;
    }

    public function apply(callable $applyer) {
        $this->checkAndSetEnd(true);
        $this->opQueue[] = ["iter\\apply", $applyer, null];
    }

    public function filter(callable $predicate) {
        $this->checkAndSetEnd();
        $this->opQueue[] = ["iter\\filter", $predicate, null];
        return $this;
    }

    public function reduce(callable $reducer, $initial = null) {
        $this->checkAndSetEnd(true);
        $this->opQueue[] = ["iter\\reduce", $reducer, $initial];
        return $this->execute();
    }

    public function reductions(callable $reductionser, $initial = null) {
        $this->checkAndSetEnd();
        $this->opQueue[] = ["iter\\reductions", $reductionser, $initial];
        return $this;
    }

    private function _zip($iterable, array $iterables) {
        array_unshift($iterables, $iterable);
        // return iter\zip(...$iterable);
        return call_user_func_array("iter\\zip", $iterables);
    }

    public function zip(/* ...$iterables */) {
        $this->checkAndSetEnd();
        $iterables = func_get_args();
        $this->opQueue[] = [[$this, "_zip"], null, $iterables];
        return $this;
    }

    public function _zipKey($iterable, $keys) {
        return iter\zipKeyValue($keys, $iterable);
    }

    public function zipKey($keys) {
        $this->checkAndSetEnd();
        $this->opQueue[] = [[$this, "_zipKey"], null, $keys];
        return $this;
    }

    public function _zipValue($iterable, $values) {
        return iter\zipKeyValue($iterable, $values);
    }

    public function zipValue($values) {
        $this->checkAndSetEnd();
        $this->opQueue[] = [[$this, "_zipValue"], null, $values];
        return $this;
    }

    private function _chain($iterable, array $iterables) {
        array_unshift($iterables, $iterable);
        // return iter\chain(...$iterable);
        return call_user_func_array("iter\\chain", $iterables);
    }

    public function chain(/* ...$iterables */) {
        $this->checkAndSetEnd();
        $iterables = func_get_args();
        $this->opQueue[] = [[$this, "_chain"], null, $iterables];
        return $this;
    }

    private function _product($iterable, array $iterables) {
        array_unshift($iterables, $iterable);
        // return iter\product(...$iterable);
        return call_user_func_array("iter\\product", $iterables);
    }

    public function product(/* ...$iterables */) {
        $this->checkAndSetEnd();
        $iterables = func_get_args();
        $this->opQueue[] = [[$this, "_product"], null, $iterables];
        return $this;
    }

    private function _slice($iterable, array $args) {
        list($start, $length) = $args;
        return iter\slice($iterable, $start, $length);
    }

    public function slice($start, $length = INF) {
        $this->checkAndSetEnd();
        $this->opQueue[] = [[$this, "_slice"], null, [$start, $length]];
        return $this;
    }

    public function take($num) {
        return $this->slice(0, $num);
    }

    public function drop($num) {
        return $this->slice($num);
    }

    public function keys() {
        $this->checkAndSetEnd();
        $this->opQueue[] = ["iter\\keys", null, null];
        return $this;
    }

    public function values() {
        $this->checkAndSetEnd();
        $this->opQueue[] = ["iter\\values", null, null];
        return $this;
    }

    public function any(callable $predicate) {
        $this->checkAndSetEnd(true);
        $this->opQueue[] = ["iter\\any", $predicate, null];
        return $this->execute();
    }

    public function all(callable $predicate) {
        $this->checkAndSetEnd(true);
        $this->opQueue[] = ["iter\\all", $predicate, null];
        return $this->execute();
    }
    
    public function findFirst(callable $predicate) {
        $this->checkAndSetEnd(true);
        $this->opQueue[] = ["iter\\search", $predicate, null];
        return $this->execute();
    }

    public function takeWhile(callable $predicate) {
        $this->checkAndSetEnd();
        $this->opQueue[] = ["iter\\takeWhile", $predicate, null];
        return $this;
    }

    public function dropWhile(callable $predicate) {
        $this->checkAndSetEnd();
        $this->opQueue[] = ["iter\\dropWhile", $predicate, null];
        return $this;
    }

    public function flatten() {
        $this->checkAndSetEnd();
        $this->opQueue[] = ["iter\\flatten", null, null];
        return $this;
    }

    public function flatMap(callable $predicate) {
        $this->checkAndSetEnd();
        $this->opQueue[] = ["iter\\flatten", null, null];
        $this->opQueue[] = ["iter\\map", $predicate, null];
        return $this;
    }

    public function flip() {
        $this->checkAndSetEnd();
        $this->opQueue[] = ["iter\\flip", null, null];
        return $this;
    }

    public function chunk($size) {
        $this->checkAndSetEnd();
        $this->opQueue[] = ["iter\\chunk", null, $size];
        return $this;
    }

    private function _join($iterable, $separator) {
        return iter\join($separator, $iterable);
    }

    public function join($separator = "") {
        $this->checkAndSetEnd(true);
        $this->opQueue[] = [[$this, "_join"], null, $separator];
        return $this->execute();
    }

    public function count() {
        $this->checkAndSetEnd(true);
        $this->opQueue[] = ["iter\\count", null, null];
        return $this->execute();
    }

    public function toIter() {
        $this->checkAndSetEnd(true);
        return iter\toIter($this->execute());
    }

    public function toArray() {
        $this->checkAndSetEnd(true);
        return iter\toArray($this->execute());
    }

    public function toArrayWithKeys() {
        $this->checkAndSetEnd(true);
        return iter\toArrayWithKeys($this->execute());
    }

    private function execute() {
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

}