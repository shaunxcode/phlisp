<?php 
$filename = false;
?> 
   <script src="codemirror/js/codemirror.js" type="text/javascript"></script>
    <link rel="stylesheet" type="text/css" href="codemirror/css/docs.css"/>
    <style type="text/css">
      .CodeMirror-line-numbers {
        width: 2.2em;
        color: #aaa;
        background-color: #eee;
        text-align: right;
        padding-right: .3em;
        font-size: 10pt;
        font-family: monospace;
        padding-top: .4em;
      }
    </style>


<div style="border:1px solid #333; width:170px; height:89%; padding:5px; float:left;" id="fileList"></div>
<div style="position: absolute; left:200px; height:95%; width:80%;">
<form id="replform" method="POST" target="processor" action="replreply.php">
  <div id="output" style="width:100%; height:53%; overflow:auto; border:1px solid #333; margin:0; padding:0;"></div>
<div style="border: 1px solid black; padding: 0px; border-top:0">
  <textarea id="code" name="command"></textarea>
</div>
       <input type="checkbox" name="showAst"> show AST <input type="checkbox" name="showSource"> show php source <input type="checkbox" name="showLineNumbers"> show line numbers <input type="checkbox" name="printResult"> print result <button onclick="return clearResult();">clear result</button> <button onclick="document.getElementById('replform').submit()">run code</button> <button style="margin-left:8em;" name="save">save</button> <input type="hidden" name="filename" id="filename"value="<?php echo $filename; ?>"><button name="saveAs" onclick="saveFileAs()">save as</button>
</form>
</div>

<style>
 ul { 
   margin:0;
   margin-left:-1.5em;
   list-style-type: none;
 }
</style>
<script>
  function loadFileList() {
      document.getElementById('processor').src = 'filelist.php';
  }

  function sendFileList(files) {
      document.getElementById('fileList').innerHTML = files;
  }

  function saveFileAs() {
      var filename = prompt('filename:');
      if(filename) {
          document.getElementById('filename').value = filename;
      }
  }

  var textarea = document.getElementById('code');
  var editor = CodeMirror.fromTextArea('code', {
    height: "40%",
    parserfile: ["tokenizejavascript.js", "parsejavascript.js"],
    stylesheet: "codemirror/css/jscolors.css",
    path: "codemirror/js/",
    autoMatchParens: true,
    lineNumbers: true,
    tabMode: 'spaces'
  });
 
  function loadFile(filename) {
      document.getElementById('filename').value = filename;
      document.getElementById('processor').src = 'loadFile.php?file=' + filename;
  }

  function sendFile(content) {
console.log(content);
      editor.setCode(content);
  }

    function displayResult(result){
      console.log(result);
      var r = document.createElement('pre'); 
      r.style.margin = 0;
      r.style.borderBottom = '1px dashed #333';
      var t = document.createTextNode(result);

      r.appendChild(t);
      var output = document.getElementById('output');
      output.appendChild(r);
      output.scrollTop = output.scrollHeight;
    }

    function clearResult() {
        document.getElementById('output').innerHTML = '';
        return false;
    }
</script>
<iframe name="processor" id="processor" src="filelist.php" style="visibility:hidden; width:0px; height:0px;"></iframe>