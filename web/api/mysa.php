<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once "core/mySpires.php";

$pre = false;
if(array_key_exists("pre", $_GET) && $_GET["pre"] == 1)
    $pre = true;

if($pre) echo "<pre>";
\mySa\wake();
if($pre) echo "</pre>";