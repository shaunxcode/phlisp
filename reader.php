<?php
require_once('core.inc.php');

class Lisphp {
    private $ast;

    function __construct($code) {
        $this->ast = $this->group($code);
    }

    function group($string, $inquote = false) {
        $ast = array();
 
        $parens = array('(' => ')', '[' => ']', '{' => '}');
        $reserved = array('each', 'for', 'string'); 
        $special = array(
            '!' => 'exc', '?' => 'que', '+' => 'add', '-' => 'sub', '*' => 'mul', 
            '/' => 'div', '%' => 'per', '>' => 'gt', '<' => 'lt', '=' => 'eql');

        $stringLength = strlen($string);
        $token = false;
	$splice = false;
        $quoteone = false;
        for($index = 0; $index <= $stringLength; $index++) {
            $char = isset($string[$index]) ? $string[$index] : false;
            if($char == '`') {
		$inquote = 'quasi';
                continue;
            }

            if($char == '\'') {
	        if(!$inquote) {
		    $quoteone = true;
		}
                $inquote = 'single';
		continue;
            }

            if($char == ',' /*&& $inquote == 'quasi'*/) { 
                $splice = 'unquote';
                continue;
            }

            if($splice && $char == '@') {
                $splice = 'unquotesplice';
                continue;
            }
 
	    //handle parens of all sort
            if(!$token && isset($parens[$char])) {
                $start = $char;
                $stop = $parens[$char];
                $block = '';
                $count = 0;
                $lambda = false;
                for($subindex = $index + 1; $subindex < $stringLength; $subindex++) {
                    $subchar = $string[$subindex];

                    if($subchar == $start) {
                        $count++;
                    }

                    if($subchar == '|' && !$lambda && !$count && $start == '[') {
		        $lambda = array('lambda', $this->group($block, $inquote));
                        $block = '';
                        continue;
                    }

                    if($subchar == $stop) {
                        if($count) {
                            $count --; 
                        } else {
                            $block = trim($block);
                            if($start == '[' && $this->atomCount($block) > 1 && $block[0] != '"' && !isset($parens[$block[0]]) && $block[0] != '`') {
                                $block = '('.$block.')';
                            }

                            $block = $this->group($block, $splice && ($inquote != 'single') ? false : $inquote);
                            break;
                        }
                    }
                    $block .= $subchar;
                }

                if($lambda) {
                    $lambda[] = array_shift($block);
                    $block = $lambda; 
                    $lambda = false;
                } else if($start == '[') {
                    $block = array('lambda', array(array('gensym', '_')), array_shift($block));
                } else if($start == '{') {
		    array_unshift($block, 'ndict');
                } else if($start == '(' && $block[0] == 'while') {
                    $block = array($block);
                }

                if($inquote == 'quasi' && !$splice) {
                    array_unshift($block, 'quasiquote');
		    $block = array($block);
		    $inquote = false;
                } else if($inquote == 'single') {
		    array_unshift($block, 'list');
		    if($quoteone) {
			$inquote = false;
		    }
		}

                if($splice) {
		    $block = array($splice, $block);
		    $splice = false;
                }

                if($start == '(' && $block[0] == 'macro') {
                    array_shift($block);
                    list($args, $body) = $block;
		    $name = array_shift($args);
		    LisphpSpecialForms::$macros[$name] = evalLisp(array('lambda', $args, $body));
                } else {
                    $ast[] = $block;
                }
                $index = $subindex;
                continue;
            }

	    //handle comments
            if($char == ';') {
	        $comment = '';
		for($subindex = $index + 1; $subindex < $stringLength; $subindex++) {
		    $subchar = $string[$subindex];
		    if($subchar == "\n" || $subchar == "\r") {
			break;
		    }
		    $comment .= $subchar;
		}
		$ast[] = array('comment', $comment);
		$index = $subindex;
            }

	    //handle strings
            if($char  == '"') {
               $escaped = false;
               $subString = '';
               for($subindex = $index + 1; $subindex < $stringLength; $subindex++) {
                    $subchar = $string[$subindex];
                    if($subchar == '\\' && !$escaped) {
                        $escaped = true;
                        continue;
                    }

                    if($subchar == '"' && !$escaped) {
                        break;
                    } else {
			$subString .= $subchar;
                        $escaped = false;
                    }
                }

		//parse strings further to allow for code interpolation and lexical scoping 
                $ast[] = array('string', $subString);
                $index = $subindex;
		continue;
            }

	    //handle tokens
	    $char = preg_replace('/[\s\n\r]+/', '', $char);
	    $char = isset($special[$char]) ? '__lphp_' . $special[$char] : $char;
            if($token === false) {
		$token = empty($char) && !is_numeric($char) ? false : $char;
	    } else {
                if(!empty($char) || is_numeric($char)) {
	  	    $token .= $char;
                } else {
		    if(is_numeric($token)) {
			$number = false;
		    } else if($token[0] == '#') {
		        $token = array('literal', $token);
                        $literal = false;
                    } else if($token[0] == ':') {
		        $token = array('symbol', substr($token, 1));
                        $symbol = false;
                    } else if(in_array($token, $reserved)) {
                        $token = '__lphp_' . $token;
                    } else if($splice) {
                        $token = array($splice, $token);
                        $splice = false;
                    } else if($token == '_') {
			$token = array('gensym', $token);
		    }
		    
                    $ast[] = !is_array($token) && $inquote ? array('quote', $token) : $token;
		    if($quoteone) {
			$quoteone = $inquote = false;
		    }

		    if($token == 'quote') {
			$inquote = 'single';
		    }
		    $token = false;
                }
            }
        }
        return $ast;
    }

    function expandMacros($ast) {
	if(!is_array($ast)) return $ast;

        $new = array();
        foreach($ast as $leaf) {
            if(isset(LisphpSpecialForms::$macros[is_array($leaf) ? pos($leaf) : false])) {
		#shift off macro name
                $name = array_shift($leaf);

		#populate vars for macro
		$vars = array();
		$dotted = false;
		foreach($this->macros[$name]['args'] as $arg) {
		    if($arg == '.') {
			$dotted = true;
			continue;
		    } else {
			$vars[$arg] = $this->expandMacros($dotted ? $leaf : array_shift($leaf));
		    }
		}
		$body = $this->macros[$name]['body'];
		array_shift($body);
		$leaf = array_shift($this->replaceMacroVars($body, $vars));

		$dotted = false;
            }
            $new[] = $leaf;
        }
        return $new;
    }

    function replaceMacroVars($branch, $vars) {
	$newBranch = array();
	foreach($branch as $leaf) {
	    if(is_array($leaf)) {
		if(pos($leaf) == 'unquote') {
		    $newBranch[] = $vars[$leaf[1]];
		} else if(pos($leaf) == 'unquotesplice') {
		    foreach($vars[$leaf[1]] as $part) {
			$newBranch[] = $part;
		    }
		} else {
		    $newBranch[] = $this->replaceMacroVars($leaf, $vars);
		}
	    } else {
		$newBranch[] = $leaf;
	    }
	}
	return $newBranch;
    }
 
    function getAst() {
         return $this->ast;
    }
   
    function atomCount($string) {
        return count(explode(' ', trim($string)));
    }


}