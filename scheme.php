<?php
/*
lisphp is a lisp interperter written to take scheme r5 and turn it into php4+
Copyright (C) 2008  shaun gilchrist

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

define('__HSHt',true);
define('__HSHf',false);
define('N',"\n");
define('n',"\n");

require_once('system.inc.php');

function ipos($arr){
   return is_array($arr) ? pos($arr) : $arr;
}

class Scheme{
  var $strings = Array();
  var $vars = Array();
  var $methods;
  var $scope;
  var $macros = Array(); 

  var $reserved = Array(
	"'" => 'quote ',
	'`' => 'quasiquote ',
	',@' => 'unquotesplicing ',
	',' => 'unquote ',
	'(:=' => '(define',
	'*' => '__MUL',
	'+' => '__PLU',
	'/' => '__DIV',
	'?' => '__QUE',
	'!' => '__EXC',
	'#(' => '(__list ',
	'(list' => '(__list',
	'( list' => '(__list',
	'#' => '__HSH',
	'$' => '__DOL',
	'^' => '__CAR',
	'%' => '__PER',
	'@' => '__ATS',
	'&' => '__AMP',
	'-' => '__DSH',
	'<' => '__LST',
	'>' => '__GRT',
	'=' => '__EQL',
	'\\' => '__SLS',
	':' => '__COL',
	'~' => '__TIL',
	'.' => '__DOT',
	'|' => '__PIP',
	'[' => '__SQL',
	']' => '__SQR',
	'((' => '(iapply (',
	'( (' => '(iapply (',
	')(' => ') (');

  function __iapply($args){
    $method = array_shift($args);
    if(is_array($method)){
      $method = $this->to_code($method);
    }
    
    $alist = Array();
    foreach($args as $a){
      $alist[] = $this->to_code($a);
    }

    return "call_user_func_array(".
      "{$method},Array(".implode(',',$alist)."))";
  }

  function add_var($var){
    if(!isset($this->vars[$this->scope]))  $this->vars[$this->scope] = Array();    
    $this->vars[$this->scope][$var] = $var;
    return $this;
  }
  
  function get_var($var){
    if(isset($this->vars[$this->scope]) && isset($this->vars[$this->scope][$var])) return true;
    return false;
  }

  function get_method($meth){
    return false;
  }
  
  function __define($args){
    if(D) puts('DEFINE CALLED WITH',$args,N);
    $sig = array_shift($args);
    if(!is_array($sig)){
      //DEFINE VAR
      if(D)puts('DEFINE VAR');
      $this->add_var($sig);
      $body = $this->to_code($args);
      if(is_array($body)){
        $body = implode('',$body);
      }
      
      return "$".$sig." = ".$body;
    } else {
      //DEFINE METHOD
      if(D)puts('DEFINE METHOD');
      $name = array_shift($sig);
      $alist = Array();
      foreach($sig as $arg){
        $this->add_var($arg);
	    $alist[] = '$'.$arg;
      }
      if(D)puts('ARGS ARE NOW:',$alist);

      $body = $this->to_code($args);
      if(D)puts('+++++BODY',$body);
      if(is_array($body)){
          $body = implode(';',$body).';';
      } else {
          $body = 'return '.$body.';';
      }
      $func = "function $name(".implode(',',$alist)."){".$body."}";
      if(D)puts('COMPILED TO:',$func);
      return $func;
    }
  }

  function __lambda($args){
    if(D)puts("LAMBDA TIME",$args);
    $sig = array_shift($args);
    $alist = Array();
    foreach($sig as $arg)$alist[] = '$'.$arg;
    
    $body = $this->to_code($args);
    if(is_array($body) && is_array(pos($body))){
        $body = implode(';',pos($body)).';';
    } else {
        $body = 'return '.ipos($body).';';
    }
    
    return "create_function('".implode(',',$alist)."','".
      str_replace($sig, $alist, $body)."')";
  }

  function __let($args){
    //expand to __lambda!
  }

  function __if($args){
    if(D)puts("IF CALLED WITH ",$args);
    $pred = $this->to_code(array_shift($args));
    $r1 = $this->to_code(array_shift($args));
    $r2 = $this->to_code(array_shift($args));
    if(is_array($r1)){
      $if = "if({$pred}){\n".implode(";\n",$r1).";\n}";
      if($r2) $if .= "else{\n".implode(";\n",$r2).";\n}";
    } else {
      $r2 = $r2 ? $r2 : false;
      $if = "({$pred} ? {$r1} : {$r2})";
    }
    if(D)puts("IF:",$if);
    return $if;

  }

  function __cond($args){
    if(D)puts('COND CALLED WITH',$args);
    //transform into nested ternary!!!
    $body = Array();
    foreach($args as $clause){
      //CHECK FOR ELSE
      if(pos($clause) == 'else'){
	array_shift($clause);
	$body[] = '{'.$this->to_code($clause).';}';
      } else {
	array_shift($clause); //get rid of iapply as this is special form
	if(D)puts("CLAUSE ",$clause);
	$body[] = 'if('.$this->to_code(array_shift($clause)).
          '){ return '.$this->to_code($clause).';}';
      }
    }
    return Array(implode(' else ',$body));
  }

  function __begin($args,$buildreturn=false){
    if(!$buildreturn) return $args; 
    $last = array_pop($args);
    $args[] = 'return '.$last;
    return $args;
  }

  function __quote($args){
    if(D) puts('QUOTE:',$args);
    if(is_scalar($args)) return "'$args'";
    $args = $this->to_code($args);
    return is_array($args) ? var_export($args,1) : (is_string($args) ? "'{$args}'" : $args);
  }

  function __and($args){
  }

  function __or($args){
  }

  function __not($args){
  }

  public function parse($string){
    $this->scope = 0;
    $this->vars[0] = array_keys($GLOBALS);
 
    //strip comments!! i.e. ;comment, ;;;comment etc. up til the new line!
    if(D)puts($string);

    //strip the strings!
    $max = strlen($string);
    while(($start = strpos($string, '"')) !== false){
      $end = false;
      $pos = $start;
      $skip = false;
      while(!$end || $pos < $max){
        $char = $string{++$pos};
        if($char == '"' && !$skip){
            $sub = substr($string, $start, ($pos-$start)+1);
            puts($sub);
            $key = count($this->strings);
            $string = str_replace($sub, '~~STRING_'.$key.'~~', $string);
            $this->strings[$key] = $sub;
            $end = $pos;
            break;
        }
        $skip = ($char =='\\');
      }
    }

    eval('$x = Array('.str_replace(
      '(',
      'Array(',
      preg_replace(
        '/([a-z0-9_]+)/i', 
        '\'${1}\'', 
        str_replace(
          ' ',
          ',',
          str_replace(
            array_keys($this->reserved), 
            $this->reserved,
            trim(
              ereg_replace(
                ' +', 
                ' ', 
                str_replace(
                  Array("\r\n","\r","\n","\t"),
                  ' ',
                  $string))))))).');');

    $x = $this->to_quote($x);
    $x = $this->find_macros($x);
    $x = $this->expand_macros($x);
    if(D) puts($x);
    $code = $this->to_code($x);
    $x = is_array($code) ? implode(";\n", $this->to_code($x)) : $code.";\n";
    foreach($this->strings as $key => $val){
      $x = str_replace('__TIL__TILSTRING_'.$key.'__TIL__TIL', $val, $x);
    }

    return $x;
  }
  
  function to_quote($ast,$tab=0){
    $tree = Array();
    $skip = Array();
    $quote_types = Array('quote','quasiquote','unquote','unquotesplicing'); 

    foreach($ast as $key => $val){
      if(in_array($key, $skip)) continue;
      if(D) puts(str_repeat("\t",$tab)."TEST $key and $val");
      
      if(in_array($val, $quote_types) && isset($ast[$key+1])){
	$val = Array('__'.$val, is_array($ast[$key+1]) ? $this->to_quote($ast[$key+1],$tab+1) : $ast[$key+1]);
	$skip[] = $key+1;
      } else if(is_array($val)){
	    $val = $this->to_quote($val,$tab+1);
      }

      $tree[] = $val;
    }
    return $tree;
  }

  function find_macros($ast){
    //find macros! add to list of macros and remove from AST
    //let-syntax, letrec-syntax, define-syntax, syntax-rules
    //assumes macros are only on "base level"
    foreach($ast as $key => $leaf){
        if(pos($leaf) == 'define__DSHmacro'){
            $this->macros[array_shift($leaf[1])] = Array(
              'args' => $leaf[1],
              'body' => $leaf[2][1]); //**ASSUMING [2][1] IS A QUASIQUOTE? CHECK FOR THIS
            unset($ast[$key]);
        }
    }
    return $ast;
  }

  function macro_replace_vars($branch, $vars){
    $new_branch = Array();
    foreach($branch as $leaf){
      if(D) puts('check leaf:',$leaf);    
      if(is_array($leaf)){
	if(pos($leaf) == '__unquote'){
	  $new_branch[] = $vars[$leaf[1]];
	} else if(pos($leaf) == '__unquotesplicing'){
	  foreach($vars[$leaf[1]] as $part){
	    $new_branch[] = $part;
	  }
	} else {
	  $new_branch[] = $this->macro_replace_vars($leaf, $vars);
	}
      } else {
	$new_branch[] = $leaf;
      }
    }
    if(D) puts("\n","NEW BRANCH:",$new_branch,"\n");
    return $new_branch;
  }
  
  function expand_macros($ast){
    $new_tree = Array();
    if(D) puts("\n", "PRE-EXPAND AST",$ast,"\n");
    foreach($ast as $key => $leaf){
	//**CHECK FOR RECURSIVE EXPANDING
        if(isset($this->macros[pos($leaf)])){
            $macro = $this->macros[pos($leaf)];
            if(D) puts('MACRO DEFINITION:',$macro);
            if(D) puts('FOUND MACRO - EXPAND');
	    if(D) puts('EXPAND THIS:',$leaf);

	    //shift off macro name
        $macro_name = array_shift($leaf); 

	    //populate vars for macro 
        $vars = Array(); 	    
	    $dotted = false;
	    foreach($macro['args'] as $arg){	      
	      if($arg == '__DOT') 
		$dotted = true; 
	      else 
		$vars[$arg] = $dotted ? $leaf : array_shift($leaf);
	    }
	    //**CHECK FOR MISSING EXPECTED VARS, THROW EXCEPTION!	    
	    if(D) puts("\n",'VARS:',$vars,"\n");

	    $leaf = $this->macro_replace_vars($macro['body'], $vars); 
        }
	$new_tree[] = $leaf;
    }
    if(D) puts("\n","NEW POST MACRO TREE",$new_tree,"\n");
    return $new_tree;
  }

  function __call($meth, $args){
    if(D)puts("CALLED FOR:",$meth);
    if(D)puts("WITH ARGS:",$args);
    $alist = is_array($args) ? pos($args) : $args;
    if(D)puts('SO APPLY',$meth,' TO ',$alist,N);
    $call = $meth.'('.implode(',',$alist).')';
    if(D)puts('GENERATED:',$call);
    return $call;
  }
  
  function to_code($ast,$t='-'){
    if(D)puts($t.'AST:',$ast);

    if(is_array($ast) && count($ast) == 1 && !$this->get_method(pos($ast))) $ast = pos($ast);    

    //IS IT AN ATOM?
    if(!is_array($ast)){
      //is it a float or negative number?
      $f = str_replace(Array('__DOT','__DSH'),Array('.','-'),$ast);
      if(is_numeric($f)) return $f;
     
      return $this->get_var($ast) && !is_numeric($ast) ? '$'.$ast:$ast;
    }
    
    //IS IT A SINGLE LIST?
    if(!is_array(pos($ast))){
      $ast = Array($ast);
    }
    
    //CREATE PARSED AST
    $code = Array();
    $special_forms = Array('__quote', 'lambda', 'define', 'cond', 'if', 'and', 'or', 'not', 'iapply');
    foreach($ast as $node){
      if(D)puts(N,$t.'CHECK:',$node,N);
      $method = array_shift($node);

      if(in_array($method, $special_forms)){
        $args = $node;
        $method = '__'.$method;
      } else {
        $args = Array();
        foreach((array)$node as $arg){
          $args[] = $this->to_code($arg);
        }
        if(D)puts('BLIND',$args);
      }
      $code[] = $this->$method(empty($args) ? NULL : $args);
    }

    return count($code) == 1 ? array_shift($code) : $code;
  }
}

if(isset($argv)){
   
  $file = array_pop($argv);
  if($file == '-d'){
    define('D', true); 
    $file = array_pop($argv);
  }

  if(!defined('D'))define('D',false);

  if(file_exists($file)) {
    $contents = file_get_contents($file);
    if(!empty($contents)) {
      $S = new Scheme();
      $code = $S->parse($contents).';'.N;
      if(D)puts($code);
      eval($code);
    } else {
      echo "file was empty".N;
    }
  } else {
    echo "Could not find a file to parse.".N;
  }
}
?>
