<?php

use mySpires\Records;
use mySpires\Tag;
use function library\tools\null_populate;
use function library\tools\safe_json_encode;
use function mySa\stat_step_daily;
use function mySpires\query;
use function mySpires\records\bin;
use function mySpires\records\find;
use function mySpires\records\lookup;
use function mySpires\users\login;
use function mySpires\users\logout;
use function mySpires\users\user;

session_start(); // Let us begin

date_default_timezone_set("Europe/London");

include_once "core/mySpires.php";
include_once "library/tools.php";

// To allow in-browser implementation.
if (!$_POST) $_POST = $_GET;

null_populate($_POST, [
    "field", "q", "search",
    "save", "remove", "erase",
    "share",
    "tag", "taglist",
    "history", "set_history_status", "purge_history",
    "bin",
    "delete_tag", "rename_tag", "new_name", "star_tag", "describe_tag",
    "val",
    "login", "logout", "username", "password", "remember",
    "plugin",
    "lookup"
]);

$user = user();

stat_step_daily("api_calls");
if($user->username) {
    stat_step_daily("api_user_calls:" . $user->username);
    $user->update_info([]);
} else {
    stat_step_daily("api_unauth_calls");
}

header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=utf-8");

/* ============================== UNAUTH OPERATIONS ============================== */

if($_POST["login"]) {
    echo json_encode(login($_POST["username"], $_POST["password"], boolval($_POST["remember"])));
    exit;
}

if($_POST["logout"]) {
    echo json_encode(logout());
    exit;
}

if ($_POST["search"]) {
    echo json_encode(new Records($_POST["search"],"search")); // Search for records on inspire.
    exit;
}

if ($_POST["q"] && $_POST["field"]) {
    $result = find([$_POST["field"] => $_POST["q"]])->sort($_POST["field"]);
    if (sizeof(explode(",", $_POST["q"])) == 1 && user()) $result->history();
    echo json_encode($result);
    exit;
}

/* ============================== PLUGIN OPERATIONS ============================== */

if($_POST["plugin"]) {
    stat_step_daily("api_plugin_calls");

    $web_extensions_version = "0.4.0";
    $web_extensions_compatible_version = "0.4.0";

    $platform = $_POST["platform"];
    $extension_version = $_POST["version"];

    // future proofing, in case we implement a Safari support.
    if ($platform == "webExtensions") {
        $current_version = $web_extensions_version;
        $compatible_version = $web_extensions_compatible_version;
    } else {
        exit;
    }

    if (version_compare($extension_version, $compatible_version) == -1) {
        echo safe_json_encode((object)Array(
            "status" => "outdated",
            "current_version" => $current_version
        ));
        exit;
    }

    if(user()) {
        $user->update_info(["plugin_version" => $extension_version]);

        $return = (object) Array(
            "user" => user()->safe_info,
            "messages" => Array()
        );
        if (version_compare($extension_version, $current_version) == -1) {
            $return->status = "legacy";
            $return->current_version = $current_version;
        } else {
            $return->status = "updated";
        }
        echo json_encode($return);
        exit;
    }

    echo json_encode((object)Array("status" => "logged_out"));
    exit;
}

/* ============================== AUTH OPERATIONS ============================== */

if(!$user->username) exit;

// If the user is not authenticated, we will not return anything.
// On getting no result mySpires.js should handle it with a JS catch().

/* ============================== SAVE REMOVE ERASE ============================== */

if ($_POST["save"] && $_POST["field"]) {
    stat_step_daily("api_entry_update_calls:" . $user->username);

    // If requested to save (which include updates), load the record, modify fields and save.
    $result = find([$_POST["field"] => $_POST["save"]])->first();
    $result->tags = $_POST["tags"];
    $result->comments = $_POST["comments"];
    $result->save(); // Save the record.
    $result->history(); // Record history for the record.
    echo json_encode(["maintenance" => true, "data" => [$_POST["save"] => $result]]);
    exit;
}

if ($_POST["remove"] && $_POST["field"]) {
    $result = find([$_POST["field"] => $_POST["remove"]])->first();
    $result->delete();
    echo json_encode(["maintenance" => true, "data" => [$_POST["remove"] => $result]]);
    exit;
}

if ($_POST["erase"] && $_POST["field"]) {
    $result = find([$_POST["field"] => $_POST["erase"]])->first();
    $result->erase();
    echo json_encode(["maintenance" => true, "data" => [$_POST["erase"] => $result]]);
    exit;
}

/* ============================== BULK REQUESTS ============================== */

// Load all entries with a particular tag
if ($_POST["tag"] !== null) {
    $result = \mySpires\records\tag($_POST["tag"]);
    echo json_encode($result);
    exit;
}

// Load all entries in bin
if ($_POST["bin"]) {
    $result = bin($_POST["bin"]);
    $total = query("SELECT count(*) AS total FROM entries WHERE username = '{$user->username}' AND bin=1");
    $total = $total->fetch_assoc()["total"];
    echo json_encode(["data" => $result, "total" => $total]);
    exit;
}

// Load all entries matching lookup
if ($_POST["lookup"] !== null) {
    $result = lookup($_POST["lookup"]);
    echo json_encode($result);
    exit;
}

/* ============================== TAG MANIPULATIONS ============================== */

if ($_POST["delete_tag"]) {
    $tag = new Tag($_POST["delete_tag"]);
    $tag->delete();

    echo json_encode(true);
    exit;
}

if ($_POST["rename_tag"] && $_POST["new_name"]) {
    $tag = new Tag($_POST["rename_tag"]);
    echo json_encode($tag->rename($_POST["new_name"]));
    exit;
}

if ($_POST["star_tag"] && $_POST["val"] !== null) {
    $tag = new Tag($_POST["star_tag"]);
    $tag->star(boolval($_POST["val"]));
    echo json_encode(true);
    exit;
}

if ($_POST["describe_tag"] && $_POST["val"] !== null) {
    $tag = new Tag($_POST["describe_tag"]);
    $tag->description($_POST["val"]);
    echo json_encode(true);
    exit;
}

/* ============================== HISTORY OPERATIONS ============================== */

// Load all entries in history
if ($_POST["history"]) {
    $result = \mySpires\records\history($_POST["history"]);
    $total = query("SELECT count(*) as total FROM history WHERE username = '{$user->username}'");
    $total = $total->fetch_assoc()["total"];
    echo json_encode(["data" => $result, "total" => $total]);
    exit;
}

if($_POST["set_history_status"] !== null) {
    $user->update_info(["history_enabled" => boolval($_POST["set_history_status"])]);
    echo json_encode(true);
    exit;
}

if($_POST["purge_history"]) {
    $user = user();
    $user->purge_history();

    echo json_encode(true);
    exit;
}

/* ============================== SHARING OPERATIONS ============================== */

/* ============================== USER INFO ============================== */

// If nothing works, return user information
echo json_encode([
    "user" => user()->safe_info,
    "tagsinfo" => user()->tags()
]);