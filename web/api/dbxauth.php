<?php

use function mySpires\config;
use function mySpires\users\user;

session_start();

include_once(__DIR__ . "/../lib/settings.php");

$config = config();
$server = $config->server;
$opts = $config->dropbox;

$redirect = $_GET["redirect"];

if(array_key_exists("user", $_GET) && $_GET["user"]) $control_user = new \mySpires\User($_GET["user"]);
else $control_user = user();


if(!$control_user->auth()) header("Location: " . webRoot);

if($_GET["unlink"] == 1) {
    $control_user->dropbox()->unlink();
    header("Location: ".$redirect);

} elseif($_GET["no_reminder"] == 1) {
    $control_user->update_info(["dbx_reminder" => 0]);
    echo 1;

} elseif($code = $_GET["code"]) {
    $e = explode("@@@", $_GET["state"]);
    $control_username = $e[0];
    $redirect = $e[1];

    $dbx = (new \mySpires\User($control_username))->dropbox($code);
    header("Location: ". $redirect);

} else {
    $data = Array(
        "response_type" => "code",
        "client_id"     => $opts->key,
        "redirect_uri"  => $server->location . "api/dbxauth.php",
        "state"         => $control_user->username . "@@@" . $redirect
    );
    $authURL = "https://www.dropbox.com/oauth2/authorize?" . http_build_query($data);
    header("Location: " . $authURL);
}