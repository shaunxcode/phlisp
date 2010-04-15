<?php
require_once('primitives.inc.php');

class StackFrame {
    private $defined; 
    private $uses; 
    private $children;
    private $parent = false;

    public function __construct(&$parent = false) {
        $this->parent = $parent;
        $this->defined = array();
        $this->uses = array();
        $this->children = array();
    }

    public function defined($var) {
        $this->defined[$var] = $var;
        return $this;
    }

    public function uses($var) {
        if(!empty($var) && !isset($this->defined[$var])) {
            $this->uses[$var] = $var;
            if($this->parent) {
                $this->parent->uses($var);
            }
        }
        return $this;
    }

    public function child($child) {
        $this->children[] = $child;
        return $this;
    }

    public function getUses() {
        return array_keys($this->uses);
    }
}

class Closure{
    private $method;
    private $env;

    function __construct($method, $env){
        $this->method = $method;
        $this->env = $env;
    }

    function call(){
        $args = func_get_args();
        $args[] = &$this->env;
        return call_user_func_array($this->method,$args);
    }

    function __toString() {
        return 'Closure method: '.$this->method.' | environment: '.json_encode($this->env);
    }
}

function createClosure($func, $env) {
    $GLOBALS[$func . '_closure'] = new Closure($func, $env);
    return array($GLOBALS[$func . '_closure'], 'call');
}

function isSymbol($node) {
    return !is_array($node) && !is_numeric($node);
}

class LisphpSpecialForms{
    static $gensymid = 0;
    static $lambdaid = 0;
    static $lambdas = array();
    static $macros = array();

    static function letForm($options, &$env) {
	if(!count($options) % 2) {
	    throw new Exception("Must have an uneven number of arguments to let i.e. (let x v b)");
	}

	$body = array_pop($options);

	$args = array();
	$values = array();
	while(!empty($options)) {
	    $args[] = array_shift($options);
	    $values[] = array_shift($options);
	}

        array_unshift($values, array('lambda', $args, $body));

	return evalLisp($values, $env);
    }

    static function beginForm($statements, &$env) {
        foreach($statements as $id => &$ast) {
            $ast = evalLisp($ast, $env);
        }
        return $statements;
    }

    static function defineForm($options, &$env) {
        $args = array_shift($options);
        if(is_array($args)) { 
            $name = array_shift($args);
            $env->defined($name);
            $value = evalLisp(array('lambda', $args, array_shift($options)), $env); 
        } else {
            $name = $args; 
            $env->defined($name);
            $value = evalLisp(array_shift($options), $env);
        }

        return '$' . $name . ' = ' . $value;    
    }

    static function gensymForm($options, &$env) {
	return '$_';
    }

    static function whileForm($options, &$env) {
         return self::lambdaForm($options, $env, 'while');
    }

    static function quasiquoteForm($options, &$env) {
	return self::lambdaForm($options, $env, 'quasiquote');
    }

    static function lambdaForm($options, &$env, $loop = false) {
        $newEnv = new StackFrame($env);
        $env->child($newEnv);
        $name = '__lambda'.self::$lambdaid++;

	$restArg = false;
        if(!$loop) {
            $args = array_shift($options);
	    foreach($args as &$arg) {
	        if($arg == '.') {
		    $restArg = true;
		    continue;
		}

		if(is_array($arg)) {
		    $arg = self::gensymForm($options, $env);
		} else {
		    $newEnv->defined($arg);
		    $arg = '$' . $arg;
		}
	    }

	    $body = array_shift($options); 
	    $body = $body[0] == 'begin' ? $body : array('begin', $body);
            $body = evalLisp($body, $newEnv);
            $last = array_pop($body); 
            $last = "\treturn {$last};";
            $body[] = $last;
	} else if($loop == 'quasiquote') {
            $list = array();
  	    foreach($options as $item) {
	        if(is_array($item)) {
  		    if(pos($item) == 'unquote') {
		        $item = evalLisp($item[1], $newEnv);
		    } else if (pos($item) == 'unquotesplice') {
		        $item[1] = evalLisp($item[1], $newEnv);
		        $item = 'array("unquotesplice",'.$item[1].')';
		    } else if(pos($item) == 'list') {
		        $item = evalLisp($item);
                    } else if(pos($item) == 'quote') {
		        $item = "'{$item[1]}'";
                    } else if($item[0] == 'symbol') {
			$item = "'{$item[1]}'";
		    } else {
			$item = var_export($item, 1);
		    }
	        }
                $list[] = $item;
  	    }

	    $loopbody = "\$new = array();\n\tforeach(array(" . implode(', ', $list) . ') as $item) { 
                if(is_array($item) && $item[0] == "unquotesplice") { 
                    foreach($item[1] as $subitem) {
                        $new[] = $subitem;
                    }
                } else {
                    $new[] = $item;
                }
              
            }  print_r($new); return $new;';
	    $body = array();
	    $args = array();
	} else {
            $args = array();
            $pred = evalLisp(array_shift($options), $newEnv);

            array_unshift($options, 'begin');

            $loopbody = implode(";\n\t\t", evalLisp($options, $newEnv));
            $body = array();
        }

        $closedOver = array();
        foreach($newEnv->getUses() as $lvar) {
            array_unshift($body, "\t\$".$lvar.' =& $env[\''.$lvar.'\']');
            $closedOver[] = "'{$lvar}' => &\${$lvar}";
        }

        $body = implode(";\n", $body); 
        
        if($loop) {
            if($loop == 'while') {
                $body .= ";\n\twhile({$pred}){\n\t\t{$loopbody};\n\t}";
            } else if ($loop == 'quasiquote') {
		$body .= ";\n\t{$loopbody}";
	    }
        }

	//if using rest args must use func_get_args inside of body
	if($restArg) { //[x, y, z, env]
	    $lastArg = array_pop($args);
	    $dot = array_pop($args);
	    if($dot !== '.') {
		throw new Exception("Expected last arg to be preceded by '.'");
	    }

	    $shiftList = '';
            foreach($args as $arg) {
		$shiftList .= "\t{$arg} = array_shift({$lastArg});\n";
	    }

	    $body = "\t{$lastArg} = func_get_args();\n\t\$env = array_pop({$lastArg});\n\t{$shiftList}\n{$body}";
	    $args = '';
	} else {
            $args[] = '$env';
	    $args = implode(', ', $args);
        }

        self::$lambdas[$name] = "function {$name}({$args}){\n".$body."\n}\n";
        return "createClosure('{$name}',  array(".implode(', ', $closedOver)."))";
    }

    static function applyForm($options, &$env) {
        $func = array_shift($options);
        $sendTo = false;
        if(is_array($func)) {
            if($func[0] == 'send') {
                array_shift($func);
                $sendTo = self::sendForm($func, $env);
            } else {
                $func = $func[0] == 'symbol' ? "'{$func[1]}'" : evalLisp($func, $env);
            }
        } else {
            $env->uses($func);
            $func = '$' . $func;
        }

        $args = (array)array_shift($options);

        foreach($args as &$arg) {
            if(isSymbol($arg)) {
                $env->uses($arg);
                $arg = '$'.$arg;
            } else {
                if(is_array($arg) && $arg[0] == 'symbol') {
                    $arg = '"' . array_pop($arg) .'"';
                } else { 
                    $arg = evalLisp($arg, $env);
                }
            }
        }
        $args = implode(', ', $args);

        return $sendTo ? $sendTo . "($args)" : "call_user_func_array({$func}, array({$args}))";
    }

    static function quoteForm($options, &$env) {
        return is_array(pos($options)) ? evalLisp(pos($options)) : '"'.addSlashes(pos($options)).'"';
    }

    static function literalForm($options, $env) {
        $literals = array('f' => 'false', 't' => 'true'); 
        return $literals[array_shift($options)];
    }

    static function symbolForm($options, $env) {
        return array_shift($options);
    }

    static function beginToLambda($begin, &$env) {
	return !is_array($begin) ? $begin : (count($begin) > 1 ? self::lambdaForm(array(array(),$begin), $env) : array_shift($begin));
    }

    static function ifForm($options, &$env) {
        list($pred, $then, $else) = $options;
	$then = self::beginToLambda(evalLisp($then, $env), $env);
	$else = self::beginToLambda(evalLisp($else, $env), $env);
        return '(' . evalLisp($pred, $env) . ' ? ' . $then . ' : ' . $else . ')';
    }

    static function condForm($options, $env) {
        $statement = array_shift($options);
        return evalLisp(array(
	    'if', 
	    array_shift($statement), 
	    array_shift($statement), 
	    count($options) ? 
		self::condForm($options, $env) : 
		array('literal', 'f')), $env);
    }

    static function set__lphp_excForm($options, &$env) {
        $var = array_shift($options); 
        $env->uses($var);
        $value = evalLisp(array_shift($options), $env);
        return "\${$var} = {$value}";
    }

    static function dec__lphp_excForm($options, &$env) {
        $var = array_shift($options); 
        $env->uses($var);
        return "--\${$var}";
    }

    static function sendForm($options, $env) {
        $chain = '';
        foreach($options as $option) {
            $part = evalLisp($option);
            if(empty($chain)) {
                $chain = $part;
            } else {
                if($static) {
                    $chain .= '::' . $part;
                    $static = false;
                } else {
                    $chain .= '->' . $part;
                }
            }

            $static = is_array($option) && $option[0] == 'symbol';
        }

        return $chain;
    }

    static function newForm($options, $env) {

    }

    static function ndictForm($options, $env) {
        if(count($options) % 2) {
            throw new Exception("A dictionary must have as many keys as it has values");
        }

        $dict = array();
        while(!empty($options)) {
            $key = array_shift($options);
            $key = is_array($key) && $key[0] == 'symbol' ? "'{$key[1]}'" : evalLisp($key, $env);
            $val = evalLisp(array_shift($options), $env);
            $dict[] = "$key => $val";
        }
        return 'array(' . implode(",\n", $dict) . ')';
    }


    static function listForm($options, $env) {
	foreach($options as &$option) {
	    $option = evalLisp($option, $env);
	}
        return 'array(' . implode(',', $options).')';
    }

    static function commentForm($options, $env) {
       return '/*' . array_shift($options);
    }

    static function phpForm($options, $env) {
        return array_shift($options);
    }

    static function stringForm($options, &$env) {
	return '"'.array_shift($options).'"';
    }
}

function evalLisp($ast, &$env = false) {
    if(!is_array($ast)) {
        if(is_numeric($ast) || $ast[0] == '(') {
            return $ast;
        } else {
	    if($env) {
		$env->uses($ast);
	    }
            return '$' . $ast;
        }
    }    

    if(!$env) {
        $env = new StackFrame();
    }

    $form = array_shift($ast);
    $formMethod = "{$form}Form";
    if(is_callable(array('LisphpSpecialForms', $formMethod))) {
        return LisphpSpecialForms::$formMethod($ast, $env);
    } else {
        return LisphpSpecialForms::applyForm(array($form, $ast), $env);
    }
}

