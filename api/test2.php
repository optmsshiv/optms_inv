<?php
echo __DIR__;
echo "<br>";
echo dirname(__DIR__);
echo "<br>";
echo file_exists(dirname(__DIR__) . '/vendor/autoload.php') ? 'FOUND' : 'NOT FOUND';