<?php

function __lphp_string() {
    $args = func_get_args();
    return implode('',$args);
}
$__lphp_string = '__lphp_string';

function uniq() {
    LisphpSpecialForms::$gensymid++;
    return current_uniq();
}
$uniq = 'uniq';

function current_uniq() {
    return '__UNIQ__' . LisphpSpecialForms::$gensymid;
}
$current__lphp_subuniq = 'current_uniq';


function __lphp_gt($l, $r) {
    return $l > $r;
}
$__lphp_gt = '__lphp_gt';

function __lphp_lt($l, $r) {
    return $l < $r;
}
$__lphp_lt = '__lphp_lt';

function __lphp_mul() {
    $args = func_get_args();
    $return = array_shift($args); 
    foreach($args as $arg) {
        $return *= $arg; 
    }
    return $return;
}
$__lphp_mul = '__lphp_mul';

function __lphp_div() {
    $args = func_get_args();
    $return = array_shift($args); 
    foreach($args as $arg) {
        $return /= $arg; 
    }
    return $return;
}
$__lphp_div = '__lphp_div';

function __lphp_add() {
    $args = func_get_args();
    $return = array_shift($args); 
    foreach($args as $arg) {
        $return += $arg;
    }
    return $return;
}

$__lphp_add = '__lphp_add';

function __lphp_sub() {
    $args = func_get_args();
    $return = array_shift($args); 
    foreach($args as $arg) {
        $return -= $arg;
    }
    return $return;
}

$__lphp_sub = '__lphp_sub';

function __lphp_eql() {
    $args = func_get_args();
    $a = array_shift($args);
    foreach($args as $b) {
        if($a !== $b) return false;
    }
    return true;
}

$__lphp_eql = '__lphp_eql';
$equal__lphp_que = '__lphp_eql';

function __lphp_cons($a, $b = false) {
    $b = $b ? $b : array();
    array_unshift($b, $a);
    return $b;
}
$nil = array();
$cons = '__lphp_cons';

function __lphp_car($a) {
    return array_shift($a);
}
$car = '__lphp_car';

function __lphp_cdr($a) {
    array_shift($a);
    return $a;
}
$cdr = '__lphp_cdr';

function __lphp_cadr($a) {
    return __lphp_car(__lphp_cdr($a));
}
$cadr = '__lphp_cadr';

function __lphp_no($x) {
    return empty($x) || !$x;
}
$no = '__lphp_no';

function __lphp_not($x) {
    return !$x;
}
$not = '__lphp_not';

function __lphp_in() {
    $args = func_get_args();
    return in_array(array_shift($args), $args);
}
//$in = '__lphp_in';

function __lphp_is($a, $b) {
    return $a == $b;
}
$is = '__lphp_is'; 

function append() {
    $args = func_get_args();
    $start = array_shift($args);
    foreach($args as $part) {
	foreach($part as $item) {
	    $start[] = $item;
	}
    }
    return $start;
}
$append = 'append';

function reverse($list) {
    $new = array();
    foreach($list as $item) {
	array_unshift($new, $item);
    }
    return $new;
}
$reverse = 'reverse';


function member($item, $list) {
    $result = array();
    foreach($list as $test) {
	if($test == $item || !empty($result)) {
            $result[] = $test;
	}
    }
    return empty($result) ? false : $result;
}
$member = 'member';

function __lphp_null__lphp_que($list) {
    return empty($list);
}
$null__lphp_que = '__lphp_null__lphp_que';

function __lphp_pair__lphp_que($item) {
    return !is_array($item) ? false : (empty($item) ? false : true);
}

$pair__lphp_que = '__lphp_pair__lphp_que';

function __lphp_integer__lphp_que($item) {
    return is_int($item);
}
$integer__lphp_que = '__lphp_integer__lphp_que';

function __lphp_number__lphp_que($item) {
    return is_float($item) || is_int($item);
}
$number__lphp_que = '__lphp_number__lphp_que';

function atomCount($x) {
    return count(explode(' ', $x));
}
function __lphp_export($var) {
    foreach($var as $id => &$item) {      
        if(is_array($item)) {
            $item = __lphp_export($item);
        } else if(atomCount($item) > 1) {
            $item = '"'.$item.'"';
        }
	if(!is_numeric($id)) {
	    $item = "'{$id}': {$item}";
	}
    }
    return '('.implode(' ',$var).')';
}


function __lphp_print($string) {
    echo (is_array($string) ? __lphp_export($string) : $string) . "\n";
}

function eachPair($dict, $func) {
    $result = array();
    foreach($dict as $key => $val) {
        $result[] = call_user_func_array($func, array($key, $val));
    }
    return $result;
}
$eachPair = 'eachPair';

function __lphp_each($list, $func) {
    $result = array();
    foreach($list as $val) {
        $result[] = call_user_func_array($func, array($val));
    }
    return $result;
}
$__lphp_each = '__lphp_each';

$map = 'array_map';

function __lphp_dict($options) {
    if(count($options) % 2) {
        throw new Exception("A dictionary must have as many keys as it has values");
    }

    $dict = array();
    while(!empty($options)) {
        $dict[array_shift($options)] = array_shift($options);
    }
    return $dict;
}
$dict = '__lphp_dict';

function at($dict, $key) {
    return $dict[$key];
}
$at = 'at';

function has($dict, $key) {
    return isset($dict[$key]);
}
$has = 'has';

$call_user_func_array = 'call_user_func_array';
$print = '__lphp_print';


function __lphp_collect($func) {
    $result = array();
    while($x = call_user_func_array($func, array(false))) {
        $result[] = $x;
    }
    return $result;
}
$collect = '__lphp_collect';

function __lphp_collectEach($obj, $filter) {
    $result = array();
    foreach($obj as $x) {
        $result[] = call_user_func_array($filter, array($x));
    }
    return $result;
}
$collectEach = '__lphp_collectEach';

function __lphp_filter($array, $func) {
    return array_filter($array, $func);
}
$filter = '__lphp_filter';