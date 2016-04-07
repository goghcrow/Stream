<?php
/**
 * User: 乌鸦
 * Date: 2016/4/3
 * Time: 22:05
 */

namespace xiaofeng;
error_reporting(E_ALL);


class Stream
{
    const OP_MAP = 1;
    const OP_FILTER = 2;
    const OP_REDUCE = 3;
    const OP_FINDFIRST = 4;
    const OP_ANY_MATCH = 5;
    const OP_ALL_MATCH = 6;

    public $debug = false;
    private $opQueue = [];
    private $source = null;
    private $end = false;

    private $limit = null;
    private $distinct = null;

    public static function of($source) {
        $stream = null;
        if($source instanceof \Closure) {
            $source = $source();
        }
        if(is_array($source)) {
            $stream = new static($source);
        } else if(is_object($source) && $source instanceof \Generator) {
            $stream = new static($source);
        } else if(is_object($source) && $source instanceof \Traversable) {
            $stream = new static($source);
        } else {
            throw new \InvalidArgumentException("source must be travel");
        }

        return $stream;
    }

    private function __construct($source) {
        $this->source = $source;
    }

    private function checkAndSetEnd($end = false) {
        if($this->end === true) {
            throw new \LogicException("stream has end");
        }
        $this->end = $end;
    }

    public static function emptygen() {
        if(false) {
            yield;
        }
    }

    public function debug() {
        $this->debug = true;
        return $this;
    }

    public function map(callable $mapper) {
        $this->checkAndSetEnd();
        $this->opQueue[] = [self::OP_MAP, $mapper];
        return $this;
    }

    public function flatmap(callable $flatmapper) {
        // fixme
    }

    public function filter(callable $filter) {
        $this->checkAndSetEnd();
        $this->opQueue[] = [self::OP_FILTER, $filter];
        return $this;
    }

    // 只distinct数据的value,保留key第一次出现的元素
    public function distinct($strict = false) {
        $this->checkAndSetEnd(true);
        $this->distinct = $strict;
        return $this;
    }

    public function limit($n) {
        $this->checkAndSetEnd();
        if($n >= 0) {
            $this->limit = $n;
        }
        return $this;
    }

    public function sort(callable $peeker) {
        // fixme
    }

    // 以下为结果状态

    public function reduce(callable $reducer, $initial = null) {
        $this->checkAndSetEnd();
        $this->opQueue[] = [self::OP_REDUCE, $reducer];
        return $this->exevaluate($initial)->current();
    }

    public function findFirst(callable $predicate) {
        $this->checkAndSetEnd();
        $this->opQueue[] = [self::OP_FINDFIRST, $predicate];
        return $this->exevaluate([null/*k*/, null/*v*/])->current();
    }

    public function anyMatch(callable $predicate) {
        $this->checkAndSetEnd();
        $this->opQueue[] = [self::OP_ANY_MATCH, $predicate];
        return $this->exevaluate(false)->current();
    }

    public function allMatch(callable $predicate) {
        $this->checkAndSetEnd();
        $this->opQueue[] = [self::OP_ALL_MATCH, $predicate];
        return $this->exevaluate(true)->current();
    }

    public function sum() {
        $this->checkAndSetEnd();
        $this->opQueue[] = [self::OP_REDUCE, function($carry, $v) {
            return $carry + $v;
        }];
        return $this->exevaluate(0)->current();
    }

    public function collect() {
        $this->checkAndSetEnd(true);
        return $this->exevaluate();
    }

    private function _limit(\Generator $gen) {
        if($this->limit > 0) {
            foreach($gen as $k => $v) {
                if(--$this->limit < 0) {
                    break;
                }
                yield $k => $v;
            }
        }
    }

    private function varhash($var, $strict = false) {
        if($strict) {
            return md5(serialize($var));
        }
        // is_*函数速度要比gettype快
        switch($strict) {
            case is_bool($var):
                return "b" . $var;
                break;
            case is_int($var):
                break;
            case is_float($var):
                break;
            case is_string($var):
                break;
            case is_array($var):
                break;
            case is_object($var):
                break;
            case is_resource($var):
                break;
            case is_null($var):
                break;
            default:
        }
    }

    private function _distinct(\Generator $gen, $strict = false) {
        $hashset = [];
//        in_array()

        foreach($gen as $k => $v) {
            if($strict) {
                $hashset[] = null;
            }
            switch($strict) {
                case is_bool($v):
                    break;
                case is_int($v):
                    break;
                case is_float($v):
                    break;
                case is_string($v):
                    break;
                case is_array($v):
                    break;
                case is_object($v):
                    break;
                case is_resource($v):
                    break;
                case is_null($v):
            }
            switch(!$strict) {
                case is_bool($v):
                    break;
                case is_int($v):
                    break;
                case is_float($v):
                    break;
                case is_string($v):
                    break;
                case is_array($v):
                    break;
                case is_object($v):
                    break;
                case is_resource($v):
                    break;
                case is_null($v):
            }

        }
    }

    private function exevaluate($toReturn = null) {
        $gen = $this->evaluate($toReturn);
        if($this->distinct !== null) {
            $gen = $this->_distinct($gen, $this->distinct);
        }
        if($this->limit !== null) {
            $gen = $this->_limit($gen);
        }
        return $gen;
    }

    /**
     * @param null $toReturn 需要返回的初始值
     * @return \Generator 需要直接返回的话：
     * 需要直接返回的话：
     * 1. 标记isReturn = true
     * 2. 跳转到switch foreach foreach 外: break 3
     * 3. 获取生成器第一个元素: return $this->evaluate()->current()
     */
    private function evaluate($toReturn = null) {
        if($this->debug) echo PHP_EOL,str_repeat("=", 50),PHP_EOL,PHP_EOL;
        $isReturn = false;
        foreach($this->source as $k => $v) {
            foreach($this->opQueue as $opk => list($type, $op)) {
                switch($type) {

                    case self::OP_MAP:
                        if($this->debug) echo print_r($v,true)," map to ",$op($v, $k),PHP_EOL;
                        $v = $op($v, $k);
                        break;

                    case self::OP_FILTER:
                        if($op($v, $k)) {
                            if($this->debug) echo print_r($v,true)," filter true",PHP_EOL;
                        } else {
                            if($this->debug) echo print_r($v,true)," filter false",PHP_EOL;
                            goto lab_continue;
                        }
                        break;

                    case self::OP_REDUCE:
                        $isReturn = true;
                        $toReturn = $op($toReturn, $v, $k);
                        if($this->debug) echo print_r($v,true)," reduce ",$toReturn,PHP_EOL;
                        $this->opQueue[$opk][2] = $toReturn;
                        break;

                    case self::OP_FINDFIRST:
                        $isReturn = true;
                        if($op($v, $k)) {
                            $toReturn = [$k, $v];
                            if($this->debug) echo print_r($v,true)," findfirst true ","[{$toReturn[0]},{$toReturn[1]}]",PHP_EOL;
                            break 3;
                        }
                        if($this->debug) echo print_r($v,true)," findfirst false ","[{$toReturn[0]},{$toReturn[1]}]",PHP_EOL;
                        break;

                    case self::OP_ANY_MATCH:
                        $isReturn = true;
                        if($op($v, $k)) {
                            $toReturn = true;
                            if($this->debug) echo print_r($v,true)," anymatch true ",$toReturn,PHP_EOL;
                            break 3;
                        }
                        if($this->debug) echo print_r($v,true)," anymatch false ",$toReturn,PHP_EOL;
                        break;

                    case self::OP_ALL_MATCH:
                        $isReturn = true;
                        if(!$op($v, $k)) {
                            $toReturn = false;
                            if($this->debug) echo print_r($v,true)," allmatch true ",$toReturn,PHP_EOL;
                            break 3;
                        }
                        if($this->debug) echo print_r($v,true)," allmatch true ",$toReturn,PHP_EOL;
                        break;

                    default:
                        throw new \RuntimeException("op type error");
                }
            }

            if(!$isReturn) {
                yield $k => $v;
            }

            lab_continue:
        }

        if($this->debug) echo PHP_EOL,str_repeat("=", 50),PHP_EOL,PHP_EOL;
        if($isReturn) {
            yield $toReturn;
        }
    }
}