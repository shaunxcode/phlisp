<?php 
  $content = file_get_contents($_GET['file']);
  $content = empty($content) ? 'nothing' : $content;
?>
<div id="output"><?php echo $content;?></div>
<script>
  parent.sendFile(document.getElementById('output').innerHTML);
</script>