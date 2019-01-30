<?php

session_start();

include_once "lib/settings.php";
include_once "api/lib/mySpires.php";
include_once "lib/functions.php";

null_populate($_POST, [
    "personal_details", "email", "first_name", "last_name",
    "site_options", "inspire_query", "inspire_username",
    "linked_services", "no_dbx_reminder",
    "current-password", "new-password"
]);

define("pageLabel", "preferences");

$username = mySpiresUser::current_username();

if (!$username) {
    header("Location: " . webRoot);
    exit();
}

if(array_key_exists("user", $_GET))
    $control_username = $_GET["user"];
else
    $control_username = $username;

if(!mySpiresUser::auth($control_username)) {
    header("Location: preferences.php");
}

$current_password = $_POST["current-password"];
$new_password = $_POST["new-password"];

if($_POST["personal_details"]) {
    mySpiresUser::update_info(Array(
        "email" => $_POST["email"],
        "first_name" => $_POST["first_name"],
        "last_name" => $_POST["last_name"]
    ), $control_username);

} elseif($_POST["site_options"]) {
    mySpiresUser::update_info(Array(
        "inspire_query" => $_POST["inspire_query"],
        "inspire_username" => $_POST["inspire_username"]
    ), $control_username);

} elseif($_POST["linked_services"]) {
    if($_POST["no_dbx_reminder"]) $dbx_reminder = 0;
    else $dbx_reminder = 1;
    mySpiresUser::update_info(Array("dbx_reminder" => $dbx_reminder), $control_username);

} elseif ($new_password) {
    if (mySpiresUser::change_password($control_username, $current_password, $new_password)) {
        webapp::alert("Password Updated.", "success", 5000);
    } else {
        webapp::alert("Authentication Error! Could not update password.", "danger", 5000);
    }
}

$control_user = mySpiresUser::info($control_username);

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

            <div id="page-title" class="row main-title">
                <div class="col-md-12">
                    <i id="parent-page-link" class="fa fa-cogs"></i>
                    <h2>Preferences
                        <?php if($control_username !=  $username) echo "for " . $control_user->name . " ("  . $control_username .  ")"; ?> </h2>
                </div>
            </div>

            <form method="post">
                <h3 class="settings-section"><i class="fas fa-sliders-h"></i> Site Options</h3>
                <input type="hidden" name="site_options" value="1">
                <div class="form-group row">
                    <label for="inspire_query" class="col-md-3 col-form-label">Default INSPIRE Query</label>
                    <div class="col-md-9">
                        <input id="inspire_query" class="form-control" name="inspire_query"
                               value="<?php echo $control_user->inspire_query; ?>"
                               placeholder="find primarch hep-th">
                    </div>
                    <label for="inspire_username" class="col-md-3 col-form-label">INSPIRE Username</label>
                    <div class="col-md-9">
                        <input id="inspire_username" class="form-control" name="inspire_username"
                               value="<?php echo $control_user->inspire_username; ?>"
                               placeholder="A.Jain.5">
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
                        $url = urlencode(mySpires::$server . "preferences.php?user=" . $control_username);
                        $dbx = mySpiresUser::dropbox($control_username);
                        $dbxuser = $dbx->user();
                        if($dbxuser) { ?>
                            <div class="form-noinput">
                                <img class="dbxthumb" src="<?php echo $dbxuser->profile_photo_url; ?>">
                                <?php echo $dbxuser->name->display_name; ?>
                                (<a href="api/dbxauth.php?user=<?php echo $control_username; ?>&unlink=1&redirect=<?php echo $url; ?>">Unlink</a>)
                            </div>
                        <?php } else { ?>
                            <div class="form-noinput">
                                <a href="api/dbxauth.php?user=<?php echo $control_username; ?>&redirect=<?php echo $url; ?>">Link</a>

                                <label class="form-check-label" style="margin-left: 10px">
                                    <input class="form-check-input" type="checkbox" id="no_dbx_reminder"
                                           name="no_dbx_reminder" value="1" style="margin-top: 5px; position: absolute;"
                                           <?php if(!$control_user->dbx_reminder) echo "checked"; ?>>
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
                               value="<?php echo $control_user->first_name; ?>">
                    </div>

                    <label for="last_name" class="col-md-3 col-form-label">Last Name</label>
                    <div class="col-md-9">
                        <input id="last_name" class="form-control" name="last_name" placeholder="Last Name"
                               value="<?php echo $control_user->last_name; ?>">
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
                    <?php if(!mySpiresUser::auth()) { ?>
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