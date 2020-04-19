<?php

session_start(); // Let us begin

date_default_timezone_set("Europe/London");

include_once "core/mySpires.php";
include_once "../lib/webapp.php";

// To allow in-browser implementation.
if (!$_POST) $_POST = $_GET;

$username_found = false;
if(array_key_exists("username", $_POST) && $_POST["username"]) {
    $username_found = boolval((new \mySpires\User($_POST["username"], "username"))->info);
}

$email_found = false;
if(array_key_exists("email", $_POST) && $_POST["email"]) {
    $email_found = boolval((new \mySpires\User($_POST["email"], "email"))->info);
}

echo json_encode(!($username_found || $email_found));
