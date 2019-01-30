<?php

session_start(); // Let us begin

date_default_timezone_set("Europe/London");

include_once "lib/mySpires.php"; // Include the mySpires library.
include_once "../lib/functions.php";

// To allow in-browser implementation.
if (!$_POST) $_POST = $_GET;

$username_found = false;
if(array_key_exists("username", $_POST) && $_POST["username"]) {
    $username_found = mySpiresUser::username_exists($_POST["username"]);
}

$email_found = false;
if(array_key_exists("email", $_POST) && $_POST["email"]) {
    $email_found = boolval(mySpiresUser::email_to_username($_POST["email"]));
}

echo json_encode(!($username_found || $email_found));
