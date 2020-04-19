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
    "index"          => ["name" => "Welcome",     "path" => ".",               "auth" => 0],
    "library"        => ["name" => "Library",     "path" => "library.php",     "auth" => 1],
    "cite"           => ["name" => "Author Citations",     "path" => "cite.php",     "auth" => 1],
    "history"        => ["name" => "History",     "path" => "history.php",     "auth" => 1],
    "preferences"    => ["name" => "Preferences", "path" => "preferences.php", "auth" => 1],
    "bin"            => ["name" => "Bin",         "path" => "bin.php",          "auth" => 1],
    "support"        => ["name" => "Support",     "path" => "#",               "auth" => 0],
    "logout"         => ["name" => "Sign Out",    "path" => ".?logout=1",      "auth" => 1],
    "login"          => ["name" => "Sign In",     "path" => ".",               "auth" => -1],
    "share"          => ["name" => "Share",       "path" => "share.php",       "auth" => -1],
    "admin"          => ["name" => "Admin Panel", "path" => "admin.php",       "auth" => 1],
    "collaborations" => ["name" => "Collaborations", "path" => "collaborations.php",       "auth" => -1],
    "bibtex"         => ["name" => "BibTeX",      "path" => "bibtex.php",       "auth" => -1]
);

$SITEOPTIONS["dependencies"] = "remote";

include_once(__DIR__ . "/webapp.php"); // Always include custom functions.

require_once(__DIR__ . "/../api/library/tools.php"); // Always include custom functions.
require_once(__DIR__ . "/../api/core/mySpires.php"); // mySpires core