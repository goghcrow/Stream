<?php
/**
 * User: 乌鸦
 * Date: 2016/4/3
 * Time: 22:05
 */

namespace xiaofeng;
use xiaofeng\test;
require "../test/test.php";
require "Stream.php";
error_reporting(E_ALL);

function dumpg(\Generator $g) {
    print_r(iterator_to_array($g));
}

function i2a(\Generator $g) {
    return iterator_to_array($g);
}

$one2five = range(1, 5);
$one2ten = range(1, 10);
$add1 = function($a) { return $a + 1; };
$sum = function($carry, $a) { return $carry + $a; };
$isEven = function($a) { return $a % 2 === 0; };
$isOdd = function($a) { return $a % 2 !== 0; };


assert(iterator_count(Stream::of([])->collect()) === 0);
assert(iterator_count(Stream::of(new \ArrayIterator)->collect()) === 0);
assert(iterator_count(Stream::of(function(){if(false) yield;})->collect()) === 0);

// test map filter
$g = Stream::of(range(1, 9))
    ->map(function($a) { return $a + 1; })
    ->filter(function($a) { return $a % 2 == 0; })
    ->map(function($a) { return $a - 1; })
    ->collect();
//dumpg($g);

test\assert_array_eq_r(i2a($g), [
    0=>1,
    2=>3,
    4=>5,
    6=>7,
    8=>9,
]);

// test reduce
$ret = Stream::of($one2five)->map($add1)->reduce($sum, 0);
assert($ret === 20);


// test find first
list($k, $v) = Stream::of($one2five)->map($add1)
    ->findFirst(function($v) { return $v > 3; });
assert($k === 2);
assert($v === 4);

// test sum
$ret = Stream::of($one2five)->map($add1)->sum();
assert($ret === 20);

// test any match
// test all match
$is = Stream::of($one2ten)->filter($isEven)->anyMatch($isEven);
assert($is === true);

$is = Stream::of($one2ten)->filter($isEven)->anyMatch($isOdd);
assert($is === false);

$is = Stream::of($one2ten)->filter($isOdd)->allMatch($isOdd);
assert($is === true);

$is = Stream::of($one2ten)->filter($isEven)->allMatch($isOdd);
assert($is === false);


// test limit
$ret = Stream::of($one2ten)->filter($isEven)
    ->limit(3)->collect();
test\assert_array_eq_r(i2a($ret), [
    1 => 2,
    3 => 4,
    5 => 6
]);

// fixme 性能测试