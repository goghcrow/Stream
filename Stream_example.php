<?php
/**
 * Date: 2016/4/26
 * Time: 13:05
 */
namespace xiaofeng;
require __DIR__ . "/Stream.php";
use iter;
error_reporting(E_ALL);

$array = range(0, 5);
Stream::of($array); // => Stream(0, 1, 2, 3, 4, 5, 6, 7, 8, 9)

$generator = function() {
    for($i = 0; $i < 6; $i++) yield $i;
};
Stream::of($generator()); // => Stream(0, 1, 2, 3, 4, 5, 6, 7, 8, 9)

$arrayIter = new \ArrayIterator($array);
Stream::of($arrayIter); // => Stream(0, 1, 2, 3, 4, 5, 6, 7, 8, 9)

$rwGen = iter\callRewindable($generator);
Stream::of($rwGen); // => Stream(0, 1, 2, 3, 4, 5, 6, 7, 8, 9)

Stream::from(0, 1, 2, 3, 4, 5);
// 0, 1, 2, 3, 4, 5, 6, 7, 8, 9

Stream::from([0, 1], [2, 3], [4, 5])->flatten();
// 0, 1, 2, 3, 4, 5

Stream::from($array, $generator(), $arrayIter, $rwGen)->flatten();
// 0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 0, 1, 2, 3, 4, 5, 6, 7, 8, 9