<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

include_once "core/mySpires.php";
include_once "../lib/webapp.php";

if(!key_exists("tag", $_GET)) $_GET["tag"] = null;

$bib = \mySpires\bibtex\bib(\mySpires\users\username() . ":" . $_GET["tag"]);

// mySpires::getbib($_GET["tag"], true);

ignore_user_abort(true);
header("Content-type: application/x-bibtex");
header("Content-Disposition: attachment; filename=". $bib->filename . "");
header("Content-length: " . strlen($bib->contents)) ;
header("Cache-control: private"); //use this to open files directly

echo $bib->contents;