<?php

session_start(); // Let us begin

date_default_timezone_set("Europe/London");

include_once "lib/mySpires.php"; // Include the mySpires library.
include_once "../lib/functions.php";
include_once "lib/mySa.php";

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
    "plugin"
]);

$user = mySpires::user();

mySa::stat_step_daily("api_calls");
if($user->username) {
    mySa::stat_step_daily("api_user_calls:" . $user->username);
    $user->update_info([]);
} else {
    mySa::stat_step_daily("api_unauth_calls");
}

header("Access-Control-Allow-Credentials: true");
// header("Access-Control-Allow-Origin: https://myspires-dev.ajainphysics.com");
header("Content-Type: application/json; charset=utf-8");

/* ============================== UNAUTH OPERATIONS ============================== */

if($_POST["login"]) {
    echo json_encode(mySpires::login($_POST["username"], $_POST["password"], boolval($_POST["remember"])));
    exit;
}

if($_POST["logout"]) {
    echo json_encode(mySpires::logout());
    exit;
}

if ($_POST["search"]) {
    echo json_encode(new mySpires_Records($_POST["search"],"search")); // Search for records on inspire.
    exit;
}

if ($_POST["q"] && $_POST["field"]) {
    $result = new mySpires_Records($_POST["q"], $_POST["field"]); // Load the record(s)
    if (sizeof(explode(",", $_POST["q"])) == 1 && mySpires::user()) $result->history();
    echo json_encode($result);
    exit;
}

/* ============================== PLUGIN OPERATIONS ============================== */

if($_POST["plugin"]) {
    mySa::stat_step_daily("api_plugin_calls");

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

    if(mySpires::user()) {
        $user->update_info(["plugin_version" => $extension_version]);

        $return = (object) Array(
            "user" => mySpires::user()->safe_info,
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
    mySa::stat_step_daily("api_entry_update_calls:" . $user->username);

    // If requested to save (which include updates), load the record, modify fields and save.
    $result = new mySpires_Record($_POST["save"], $_POST["field"]);
    $result->tags = $_POST["tags"];
    $result->comments = $_POST["comments"];
    $result->save(); // Save the record.
    $result->history(); // Record history for the record.
    echo json_encode(["maintenance" => true, "data" => [$_POST["save"] => $result]]);
    exit;
}

if ($_POST["remove"] && $_POST["field"]) {
    $result = new mySpires_Record($_POST["remove"], $_POST["field"]);
    $result->delete();
    echo json_encode(["maintenance" => true, "data" => [$_POST["remove"] => $result]]);
    exit;
}

if ($_POST["erase"] && $_POST["field"]) {
    $result = new mySpires_Record($_POST["erase"], $_POST["field"]);
    $result->erase();
    echo json_encode(["maintenance" => true, "data" => [$_POST["erase"] => $result]]);
    exit;
}

/* ============================== BULK REQUESTS ============================== */

// Load all entries with a particular tag
if ($_POST["tag"] !== null) {
    echo json_encode(new mySpires_Records($_POST["tag"],"tag"));
    exit;
}

// Load all entries in bin
if ($_POST["bin"]) {
    $result = new mySpires_Records($_POST["bin"], "bin"); // History
    $total = mySpires::db_query("SELECT count(*) AS total FROM entries WHERE username = '{$user->username}' AND bin=1");
    $total = $total->fetch_assoc()["total"];
    echo json_encode(["data" => $result, "total" => $total]);
    exit;
}

/* ============================== TAG MANIPULATIONS ============================== */

// Returns a list of tags
if ($_POST["taglist"]) {
    echo json_encode(mySpires::taglist()); // List of tags
    exit;
}

if ($_POST["delete_tag"]) {
    $tag = new mySpires_Tag($_POST["delete_tag"]);
    $tag->delete();

    echo json_encode(true);
    exit;
}

if ($_POST["rename_tag"] && $_POST["new_name"]) {
    $tag = new mySpires_Tag($_POST["rename_tag"]);
    echo json_encode($tag->rename($_POST["new_name"]));
    exit;
}

if ($_POST["star_tag"] && $_POST["val"] !== null) {
    $tag = new mySpires_Tag($_POST["star_tag"]);
    $tag->star(boolval($_POST["val"]));
    echo json_encode(true);
    exit;
}

if ($_POST["describe_tag"] && $_POST["val"] !== null) {
    $tag = new mySpires_Tag($_POST["describe_tag"]);
    $tag->description($_POST["val"]);
    echo json_encode(true);
    exit;
}

/* ============================== HISTORY OPERATIONS ============================== */

// Load all entries in history
if ($_POST["history"]) {
    $result = new mySpires_Records($_POST["history"], "history"); // History
    $total = mySpires::db_query("SELECT count(*) as total FROM history WHERE username = '{$user->username}'");
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
    echo json_encode(mySpires::purge_history());
    exit;
}

/* ============================== SHARING OPERATIONS ============================== */

// Sharing
if ($share = $_POST["share"]) {
    $e = explode('/', $share, 2);
    $owner_username = $e[0];
    $tag = $e[1];
    echo json_encode(mySpires::sharedtag($tag, $owner_username));
    exit;
}

/* ============================== USER INFO ============================== */

// If nothing works, return user information
echo json_encode([
    "user" => mySpires::user()->safe_info,
    "tagsinfo" => mySpires::user()->tags()
]);