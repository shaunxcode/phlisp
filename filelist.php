<?php

function buildTree($dir) { 
    $list = '';
    foreach (new RecursiveDirectoryIterator($dir) as $filename => $file) {
        $list .= '<li>' . (!$file->isDir() ? '<a href="javascript:loadFile(\''.$filename.'\');">' : '').str_replace('.let', '', $file->getFilename()) . ($file->isDir() ? buildTree($file) : '</a>') .'</li>';
    }
    return "<ul>$list</ul>";
}

echo '<div id="output">';
echo buildTree('files');
echo '</div>';

/*
(define (buildTree dir)
    (collectEach (new :RecursiveDirectoryIterator dir)
                 [if (. _ isFile) 
                     (. _ getFilename)
                     {(. _ getFilename) (buildTree _)}]))

(echo (:json_encode (buildTree 'files')))
*/
?>
<script>
  parent.sendFileList(document.getElementById('output').innerHTML);
</script>
