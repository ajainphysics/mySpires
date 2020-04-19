<?php

use function library\tools\null_populate;
use function mySpires\config;
use function mySpires\users\admin;
use function mySpires\users\user;
use function mySpires\users\username;

session_start();

include_once "lib/settings.php";

null_populate($_POST, [
    "personal_details", "email", "first_name", "last_name", "history_status",
    "site_options", "inspire_query", "inspire_username",
    "linked_services", "no_dbx_reminder",
    "current-password", "new-password"
]);

null_populate($_GET, ["user"]);

define("pageLabel", "preferences");

if (!user()) {
    header("Location: " . webRoot);
    exit();
}

if($_GET["user"]) $control_user = new \mySpires\User($_GET["user"]);
else $control_user = user();

if(!$control_user->auth()) header("Location: preferences.php");

$current_password = $_POST["current-password"];
$new_password = $_POST["new-password"];

if($_POST["personal_details"]) {
    $control_user->update_info([
        "email" => $_POST["email"],
        "first_name" => $_POST["first_name"],
        "last_name" => $_POST["last_name"]
    ]);

} elseif($_POST["site_options"]) {
    $control_user->update_info([
        "inspire_query" => $_POST["inspire_query"],
        "inspire_username" => $_POST["inspire_username"],
        "history_enabled" => $_POST["history_status"]
    ]);

} elseif($_POST["linked_services"]) {
    if($_POST["no_dbx_reminder"]) $dbx_reminder = 0;
    else $dbx_reminder = 1;
    $control_user->update_info(["dbx_reminder" => $dbx_reminder]);

} elseif ($new_password) {
    $success = false;
    if(admin())
        $success = $control_user->set_password($new_password);
    else
        $success = $control_user->change_password($current_password, $new_password);

    if ($success) {
        webapp::alert("Password Updated.", "success", 5000);
    } else {
        webapp::alert("Authentication Error! Could not update password.", "danger", 5000);
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<?php include "head.php"; // page head ?>

<body id="page-<?php echo pageLabel; ?>">
<div class="main-wrapper">
    <?php include "navbar.php"; // navbar ?>

    <div class="main-content">
        <div class="container">
            <div class="row main-alerts">
                <?php webapp::display_alerts(); ?>
            </div>

            <nav class="title-nav navbar navbar-expand-lg navbar-light">
                <div class="main-title">
                    <i class="main-icon fa fa-cogs"></i>
                    <h1>Preferences
                        <?php if($control_user->username != username()) echo "for " . $control_user->name . " ("  . $control_user->username .  ")"; ?> </h1>
                </div>
            </nav>

            <form method="post">
                <h3 class="settings-section"><i class="fas fa-sliders-h"></i> Site Options</h3>
                <input type="hidden" name="site_options" value="1">
                <div class="form-group row">
                    <label for="inspire_query" class="col-md-3 col-form-label">Default INSPIRE Query</label>
                    <div class="col-md-9">
                        <input id="inspire_query" class="form-control" name="inspire_query"
                               value="<?php echo $control_user->info->inspire_query; ?>"
                               placeholder="find primarch hep-th">
                    </div>

                    <label for="inspire_username" class="col-md-3 col-form-label">INSPIRE Username</label>
                    <div class="col-md-9">
                        <input id="inspire_username" class="form-control" name="inspire_username"
                               value="<?php echo $control_user->info->inspire_username; ?>"
                               placeholder="A.Jain.5">
                    </div>
                </div>

                <div class="form-group row">
                    <label for="history_status" class="col-md-3 col-form-label">Enable History</label>
                    <div class="col-md-9">
                        <input type="checkbox" id="history_status" name="history_status" value="1"
                        <?php if($control_user->info->history_enabled) echo "checked"; ?>>
                        <label for="history_status">Keep an automatic record of the references you visit in your browser. Needs mySpires browser plugin to function.</label>
                    </div>
                </div>

                <div class="form-group row">
                    <div class="offset-md-2 col-md-10">
                        <button class="btn btn-primary">Update</button>
                    </div>
                </div>
            </form>

            <form method="post">
                <h3 class="settings-section"><i class="fa fa-link"></i> Linked Services</h3>
                <input type="hidden" name="linked_services" value="1">
                <div class="form-group row">
                    <div class="alert alert-info col-sm-12" role="alert">
                        If you use BibTeX and Dropbox on your desktop, you can create a symbolic link to
                        your Dropbox's "<i>mySpires</i>/bib/mySpires_<?php echo $control_user->username; ?>.bib"
                        in your texmf folder, and keep your mySpires references on your fingers. Awesome, eh?
                    </div>

                    <label class="col-md-3 col-form-label">Dropbox</label>
                    <div class="col-md-9">
                        <?php
                        $server = config("server");
                        $url = urlencode($server->location . "preferences.php?user=" . $control_user->username);
                        $dbx = $control_user->dropbox();
                        $dbxuser = $dbx->user();
                        if($dbxuser) { ?>
                            <div class="form-noinput">
                                <img class="dbxthumb" src="<?php echo $dbxuser->profile_photo_url; ?>" alt="">
                                <?php echo $dbxuser->name->display_name; ?>
                                (<a href="api/dbxauth.php?user=<?php echo $control_user->username; ?>&unlink=1&redirect=<?php echo $url; ?>">Unlink</a>)
                            </div>
                        <?php } else { ?>
                            <div class="form-noinput">
                                <a href="api/dbxauth.php?user=<?php echo $control_user->username; ?>&redirect=<?php echo $url; ?>">Link</a>

                                <label class="form-check-label" style="margin-left: 10px">
                                    <input class="form-check-input" type="checkbox" id="no_dbx_reminder"
                                           name="no_dbx_reminder" value="1" style="margin-top: 5px; position: absolute;"
                                           <?php if(!$control_user->info->dbx_reminder) echo "checked"; ?>>
                                    Do not remind me.
                                </label>
                            </div>
                        <?php } ?>
                    </div>
                </div>

                <div class="form-group row">
                    <div class="offset-md-2 col-md-10">
                        <button class="btn btn-primary">Update</button>
                    </div>
                </div>
            </form>

            <form method="post">
                <h3 class="settings-section"><i class="fa fa-user"></i> Personal Details</h3>
                <input type="hidden" name="personal_details" value="1">

                <div class="form-group row">
                    <label for="username" class="col-md-3 col-form-label">Username</label>
                    <div class="col-md-9">
                        <input id="username" class="form-control" name="username" placeholder="Username"
                               value="<?php echo $control_user->username; ?>" disabled>
                    </div>

                    <label for="email" class="col-md-3 col-form-label">Email</label>
                    <div class="col-md-9">
                        <input id="email" class="form-control" name="email" placeholder="Email"
                               value="<?php echo $control_user->email; ?>">
                    </div>
                </div>

                <div class="form-group row">
                    <label for="first_name" class="col-md-3 col-form-label">First Name</label>
                    <div class="col-md-9">
                        <input id="first_name" class="form-control" name="first_name" placeholder="First Name"
                               value="<?php echo $control_user->info->first_name; ?>">
                    </div>

                    <label for="last_name" class="col-md-3 col-form-label">Last Name</label>
                    <div class="col-md-9">
                        <input id="last_name" class="form-control" name="last_name" placeholder="Last Name"
                               value="<?php echo $control_user->info->last_name; ?>">
                    </div>
                </div>
                <div class="form-group row">
                    <div class="offset-md-2 col-md-10">
                        <button class="btn btn-primary">Update</button>
                    </div>
                </div>
            </form>

            <form method="post">
                <h3 class="settings-section"><i class="fa fa-key"></i> Password</h3>
                <div class="form-group row">
                    <?php if(!admin()) { ?>
                    <label for="current-password" class="col-md-3 col-form-label">Current Password</label>
                    <div class="col-md-9">
                        <input id="current-password" type="password" class="form-control" name="current-password"
                               placeholder="Current Password">
                    </div>
                    <?php } ?>

                    <label for="new-password" class="col-md-3 col-form-label">New Password</label>
                    <div class="col-md-9">
                        <input id="new-password" type="password" class="form-control" name="new-password"
                               placeholder="New Password">
                    </div>
                </div>
                <div class="form-group row">
                    <div class="offset-md-2 col-md-10">
                        <button class="btn btn-primary">Update</button>
                    </div>
                </div>
            </form>

        </div>

    </div>

    <?php include "footbar.php"; ?>

</div>

<?php include "foot.php"; ?>

</body>

</html>