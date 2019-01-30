<?php
session_start();

include_once "lib/mySpires.php";

if(!array_key_exists("tag", $_GET)) $_GET["tag"] = null;

$bib = mySpires::bibtex(null, $_GET["tag"]);

// mySpires::getbib($_GET["tag"], true);

ignore_user_abort(true);
header("Content-type: application/x-bibtex");
header("Content-Disposition: attachment; filename='". $bib->filename . "'");
header("Content-length: " . strlen($bib->contents)) ;
header("Cache-control: private"); //use this to open files directly

echo $bib->contents;