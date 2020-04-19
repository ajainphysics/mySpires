<?php
/**
 * This is the main mySpires PHP library to communicate with the database.
 * It does not contain any HTML formatting information for mySpires website or plugin.
 * It basically provides a framework for other platform specific code to interact with the mySpires atmosphere.
 *
 * @author Akash Jain
 */

namespace mySpires;

ini_set("include_path",
    '/home/kz0qr7otxolj/php:/home/kz0qr7otxolj/resources:/home/kz0qr7otxolj/cdn:'
    . ini_get("include_path") );

require_once(__DIR__ . "/../library/Dropbox.php");
require_once(__DIR__ . "/../library/BibTeX.php");

// ============================== Database Tools ============================== //

use mysqli;

$db = null;

function config($opt = null)
{
    $opts = include(__DIR__ . "/../../../.myspires_config.php");
    if(!$opt) return $opts;
    elseif(property_exists($opts, $opt)) return $opts->$opt;
    else return null;
}

function mysqli()
{
    global $db;
    if($db) return $db; // If already logged into the database, return the saved instance.

    $opts = config("mysql");
    $db = new mysqli($opts->host, $opts->username, $opts->passwd, $opts->dbname);

    if ($db->connect_errno) {
        printf("Connection to the database failed: %s\n", $db->connect_error);
        exit();
    }
    else return $db;
}

function query($query) {
    $db = mysqli();
    $result = $db->query($query);

    if ($db->errno) {
        echo "Error during database query: $query. Dumping current status:";
        echo "<pre>";
        var_dump($db);
        echo "</pre>";
        exit();
    }
    else return $result;
}

require_once __DIR__ . "/mySpires_bibtex.php";
require_once __DIR__ . "/mySpires_records.php";
require_once __DIR__ . "/mySpires_users.php";
require_once __DIR__ . "/mySpires_tags.php";

require_once __DIR__ . "/_class_record.php";
require_once __DIR__ . "/_class_records.php";
require_once __DIR__ . "/_class_collaboration.php";
require_once __DIR__ . "/_class_user.php";
require_once __DIR__ . "/_class_tag.php";

require_once __DIR__ . "/mySa.php";