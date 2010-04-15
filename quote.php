<?php

function &current_by_ref(&$arr) {
    return $arr[pos($arr)];
}
 
  //$list = array('a', array('unquotesplice', array(1, 2)), 'c', array('unquote', 1));

$c = 200;
$d = array('a','b','c');

$list = array('a','b','20', array('b', array('e', array(array('unquotesplice', array('x','y','z'))))));
$new = array();

function quasiquote($list) {
    $new = array();
    foreach($list as $item) {
	if(is_array($item)) {
	    if($item[0] == 'unquote') {
		
	    } else if ($item[0] == 'unquotesplice') {

	    }
	} else {
	    $new[] = $item;
	}
    }
}

while($item = array_shift($list)) {
    if(!is_array($item)) {
        if(isset($sub)) {
            $sub[] = $item;
	} else {
	    $new[] = $item;
	}
    }

    if(is_array($item)) {
	if(isset($sub)) {
	    $sub[] = array();
	    $sub = current_by_ref($sub);
	} else {
	    $new[] = array();
	    $sub = current_by_ref($new);
	}
	$list = $item;
    }
}
echo "\n";
echo json_encode($new);
/*
$new = array();
foreach($list as $item) {
    if(is_array($item) && $item[0] == 'quote') {
        $new[] = $item[1];
    } else if(is_array($item) && $item[0] == 'unquotesplice') {
         foreach($item[1] as $subitem) {
             $new[] = $subitem;
         }
    } else if(is_array($item) && $item[0] == 'unquote') {
        $new[] = $item[1];
    } else {
        $new[] = $item;
    }
}

print_r($new);

(macro (when test . body)
  `(if ,test (begin ,@body) #f))
 

*/