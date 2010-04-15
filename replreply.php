
<div id="output"><?php
    if(!isset($_POST['command']) || empty($_POST['command'])){
        echo 'Must provide a command';  
    } else {
        require_once('reader.php'); 
        require_once('core.inc.php');
        $script = '';

        if(isset($_POST['filename'])) {
            if(isset($_POST['saveAs']) || isset($_POST['save'])) {
		$file = (isset($_POST['saveAs']) ? "files/" : '') . 
                        str_replace('..','',$_POST['filename']) . 
                        (isset($_POST['saveAs']) ? ".let" : ''); 
		echo "Saving $file\n";
                file_put_contents($file, stripslashes($_POST['command']));
		$script = 'parent.loadFileList()';
            }
        }

	if(isset($_POST['printResult'])) $_POST['command'] = '(print '.$_POST['command'].')';
        $lisp = new Lisphp(stripslashes($_POST['command']));

     
	$globalMacros = '';
	if(isset($_POST['showSource'])) {
	    echo implode("\n\n", LisphpSpecialForms::$lambdas) ."\n\n";
        }
	eval(implode(LisphpSpecialForms::$lambdas));
        foreach(LisphpSpecialForms::$macros as $macro => $mac) {
	    if(isset($_POST['showSource'])) {
                echo "macro $macro :: $mac\n\n";
	    }
	    eval('LisphpSpecialForms::$macros["'.$macro.'"] = '.$mac.';');
	    $globalMacros .= '$'.$macro.' =& LisphpSpecialForms::$macros["'.$macro.'"];'."\n";
        }
	LisphpSpecialForms::$lambdas = array();

    //   $this->ast = $this->expandMacros($this->ast);


	$ast = $lisp->getAst();
        array_unshift($ast, 'begin');
        $php = evalLisp($ast);
        $transformed = implode("\n", LisphpSpecialForms::$lambdas) . $globalMacros . implode(";\n", $php) . ";";

        if(isset($_POST['showAst'])) {
            echo json_encode($lisp->getAst())."\n\n";
        }


        if(isset($_POST['showSource'])) {
            foreach(explode("\n", $transformed) as $num => $line) {
		echo (isset($_POST['showLineNumbers']) ? '#'.($num+1).' ': '') . "{$line}\n";
	    }
        }
 
        eval($transformed);
    }
  ?>
</div>
<script>
  parent.displayResult(document.getElementById('output').innerHTML);
  <?php echo $script; ?>
</script>
?>