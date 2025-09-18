<?php
// 检查AspectKernel.php的语法
$file = 'src/Runtime/AspectKernel.php';
$output = shell_exec("php -l $file 2>&1");
echo $output;
?>