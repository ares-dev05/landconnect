<?php
error_reporting(E_ALL);

$abc = (object) ['def' => 123];

echo ($abc->def ?? '<br/>nope<br/>');
echo ($def ?? '<br/>nope<br/>');
echo ($abc->def->ghi->jkl);
