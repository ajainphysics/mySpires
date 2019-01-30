<?php

session_start();

include_once(__DIR__ . "/../lib/settings.php");
include_once(__DIR__ . "/lib/mySpires.php");


$opts = include(__DIR__ . "/../../.myspires_config.php");
$opts = $opts->dropbox;

$redirect = $_GET["redirect"];

$username = mySpiresUser::current_username();

if(array_key_exists("user", $_GET))
    $control_username = $_GET["user"];
else
    $control_username = $username;

if(!mySpiresUser::auth($control_username)) {
    header("Location: " . webRoot);
}

if($_GET["unlink"] == 1) {
    mySpiresUser::dropbox($control_username)->unlink();
    header("Location: ".$redirect);

} elseif($_GET["no_reminder"] == 1) {
    mySpiresUser::update_info(Array("dbx_reminder" => 0), $control_username);
    echo 1;

} elseif($code = $_GET["code"]) {
    $e = explode("@@@", $_GET["state"]);
    $control_username = $e[0];
    $redirect = $e[1];

    $dbx = mySpiresUser::dropbox($control_username, $code);
    header("Location: ". $redirect);

} else {
    $data = Array(
        "response_type" => "code",
        "client_id"     => $opts->key,
        "redirect_uri"  => mySpires::$server . "api/dbxauth.php",
        "state"         => $control_username . "@@@" . $redirect
    );
    $authURL = "https://www.dropbox.com/oauth2/authorize?" . http_build_query($data);
    header("Location: " . $authURL);
}