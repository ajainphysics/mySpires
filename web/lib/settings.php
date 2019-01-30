<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set("Europe/London");

define('recordTable', 'records');
define('entryTable', 'entries');
define('userTable', 'users');

$SITEOPTIONS = Array();

$SITEOPTIONS["webRoot"] = "https://myspires.ajainphysics.com/";
define('webRoot', 'https://myspires.ajainphysics.com/');

$SITEOPTIONS["pages"] = Array(
    "index"       => Array("name" => "Welcome",     "path" => ".",               "auth" => 0),
    "library"     => Array("name" => "Library",     "path" => "library.php",     "auth" => 1),
    "cite"        => Array("name" => "Author Citations",     "path" => "cite.php",     "auth" => 1),
    "history"     => Array("name" => "History",     "path" => "history.php",     "auth" => 1),
    "preferences" => Array("name" => "Preferences", "path" => "preferences.php", "auth" => 1),
    "bin"         => Array("name" => "Bin",         "path" => "bin.php",          "auth" => 1),
    "support"     => Array("name" => "Support",     "path" => "#",               "auth" => 0),
    "logout"      => Array("name" => "Sign Out",    "path" => ".?logout=1",      "auth" => 1),
    "login"       => Array("name" => "Sign In",     "path" => ".",               "auth" => -1),
    "share"       => Array("name" => "Share",       "path" => "share.php",       "auth" => -1),
    "admin"       => Array("name" => "Admin Panel", "path" => "admin.php",       "auth" => 1)
);

$SITEOPTIONS["dependencies"] = "remote";

include_once(__DIR__."/../api/lib/mySpires.php"); // Always include mySpires.
include_once(__DIR__."/../api/lib/mySa.php"); // Always include mySpires.
include_once(__DIR__."/functions.php"); // Always include custom functions.