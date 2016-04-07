<?php
/**
 * Date: 2016/4/3
 * Time: 22:05
 */
use \xiaofeng\Stream;
require "Stream.php";
error_reporting(E_ALL);

$add1 = function($a) { return $a + 1; };
$isEven = function($a) { return $a % 2 === 0; };
$isOdd = function($a) { return $a % 2 !== 0; };


$pp = function($v, $k) {
    echo "[$k=>$v] -> ", PHP_EOL;
};
$debug = false;

assert(Stream::range(1, 3)
        ->mapKeys(function($v) { return chr(ord('a') + $v); })
        ->keys()
        ->toArrayWithKeys()
    ===
    ["a","b","c"]
);

assert(
    Stream::range(1, 10)
        ->reduce(iter\fn\operator("+"), 100)
    ===
    (Stream::range(1, 10)
            ->reduce(iter\fn\operator("+"), 0) + 100)
);

assert(
    Stream::range(1, 10)
        ->all("is_int")
);

assert(
    Stream::range(1, 10)
        ->debug($debug)
        ->map(function($v) { return "#$v"; })
        ->peek($pp)
        ->any("is_int")
    === false
);

assert(
    Stream::of([1,2,3,"str"])
        ->any("is_string")
    === true
);

assert(
    Stream::of([1,2,3,"str"])
        ->all("is_string")
    === false
);

assert(
    Stream::range(1, 10)
        ->debug($debug)
        ->peek($pp)
        ->findFirst(function($v) { return $v === 5;})
    === 5
);

assert(
    Stream::from(
        iter\range(1, 4),
        iter\range(5, 7),
        iter\range(8, 10)
    )
        ->flatten()
        ->toArray()
    ===
    Stream::range(1, 10)
        ->toArray()
);

assert(
    Stream::from(
        iter\range(1, 4),
        iter\range(5, 7),
        iter\range(8, 10)
    )
        ->flatMap($add1)
        ->toArray()
    ===
    Stream::range(2, 11)
        ->toArray()
);

assert(
    Stream::range(1, 5)
        ->chunk(2)
        ->toArray()
    ===
    [[1,2],["2"=>3,"3"=>4],["4"=>5]]
);

assert(
    Stream::range(1, 3)
        ->reindex(function($v) { return "#$v"; })
        ->flip()
        ->toArrayWithKeys()
    ===
    ["1"=>"#1","2"=>"#2","3"=>"#3"]
);

assert(
    Stream::range(1, 3)
        ->join(", ")
    === "1, 2, 3"
);

assert(
    Stream::range(1, 10)
        ->count()
    === 10
);

assert(
    Stream::repeat("X", 10)
        ->join()
    === str_repeat("X", 10)
);

assert(
    Stream::range(1, 5)
        ->slice(0, 3)
        ->toArray()
    === [1,2,3]
);

assert(
    Stream::range(1, 5)
        ->take(3)
        ->toArray()
    === [1,2,3]
);

assert(
    Stream::range(1, 5)
        ->drop(3)
        ->toArrayWithKeys()
    === ["3"=>4, "4"=>5]
);

assert(
    Stream::range(1,3)
        ->zip([4,5,6], [7,8,9])
        ->toArray()
    ===
    [[1,4,7],[2,5,8],[3,6,9]]
);

assert(
    Stream::range(1, 3)
        ->chain(iter\range(4,6), iter\range(7,9))
        ->join()
    === "123456789"
);

assert(
    Stream::range(1, 2)
    ->product(iter\rewindable\range(3, 4)) // notice
    ->toArray()
    ===
    [[1,3],[1,4],[2,3],[2,4]]
);

assert(
    Stream::of(range("a", "c"))
        ->zipValue(iter\range(1, 3))
        ->toArrayWithKeys()
    ===
    Stream::range(1, 3)
        ->zipKey(range("a", "c"))
        ->toArrayWithKeys()
);
