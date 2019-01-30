<?php

session_start();

include_once "lib/settings.php";

if(mySpiresUser::current_username()) {
    header("Location: /");
}

define("pageLabel", "register");

null_populate($_POST, ["registration", "forgot-password", "reset-password"]);
null_populate($_GET, ["forgot", "reset"]);

// Check recaptcha from the registration form and send to mySpiresUser::register if successful.
if($_POST["registration"]) {
    $success = false; // If this is still false at the end, something went wrong.
    if(webapp::validate_recaptcha($_POST["g-recaptcha-response"])) {
        $success = mySpiresUser::register((object)$_POST);
    }

    if($success) {
        header("Location: /?source=registration_successful");
    }
    else {
        webapp::alert("Something went wrong! Please try again. If the problem persists, please contact the <a href='support.php' class='alert-link'>administrator</a>.", "danger");
    }
}

if($_POST["forgot-password"]) {
    $success = false;
    if(webapp::validate_recaptcha($_POST["g-recaptcha-response"])) {
        $success = mySpiresUser::forgot_password($_POST["forgot-username"]);
        // $success = mySpiresUser::register((object)$_POST);
    }

    if($success) {
        header("Location: /?source=password_reset_requested");
    }
    else {
        webapp::alert("Something went wrong! Please try again. If the problem persists, please contact the <a href='support.php' class='alert-link'>administrator</a>.", "danger");
    }
}

if($_GET["reset"] && !mySpiresUser::check_reset_password_code($_GET["username"], $_GET["code"])) {
    header("Location: /");
}

if($_POST["reset-password"]) {
    $success = mySpiresUser::reset_password($_POST["username"], $_POST["code"], $_POST["password"]);

    if($success) {
        header("Location: /?source=password_reset_successful");
    }
    else {
        webapp::alert("Something went wrong! Please try again. If the problem persists, please contact the <a href='support.php' class='alert-link'>administrator</a>.", "danger");
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<?php include_once "head.php"; // page head ?>

<body id="page-<?php echo pageLabel; ?>">

<?php include_once "navbar.php"; // navbar ?>

<div class="main-wrapper">
    <div class="main-content">
        <div class="container">
            <div class="registration-form-wrapper">
                <img class="mySpires-logo" src="img/mySpires.png" alt="mySpires">

                <?php webapp::display_alerts(); ?>

                <?php if($_GET["forgot"]) { ?>

                    <p>
                        Forgot your password? Tell us your username or email address and we will take it from there.
                    </p>

                    <form method="post" class="forgot-password-form" novalidate>
                        <input type="hidden" name="forgot-password" value="1">

                        <small class="text-muted"><label for="forgot-username" class="label-required">Username/Email</label></small>
                        <input id="forgot-username" type="text" name="forgot-username" class="form-control">
                        <small id="forgot-username-invalid-warning" class="invalid-warning form-text text-danger">Username or email not found.</small>

                        <div class="g-recaptcha" data-size="normal" data-sitekey="6LetfoQUAAAAAFUizejAuy_PLWduJkEOJ2AHvcpe"></div>
                        <small id="recaptcha-warning" class="invalid-warning form-text text-danger">Please tick the recaptcha checkbox.</small>

                        <button class="btn btn-primary">Reset Password</button>

                    </form>

                <?php } elseif($_GET["reset"]) { ?>

                    <p>
                        Nearly done! Set a new password here.
                    </p>

                    <form method="post" class="reset-password-form" novalidate>
                        <input type="hidden" name="reset-password" value="1">
                        <input type="hidden" name="code" value="<?php echo $_GET["code"]; ?>">

                        <small class="text-muted"><label for="reset-username">Username</label></small>
                        <input id="reset-username" type="text" name="username" class="form-control" value="<?php echo $_GET["username"]; ?>" readonly>

                        <small class="text-muted"><label for="password" class="label-required">Password</label></small>
                        <input id="password" type="password" name="password" class="form-control" required>
                        <small id="password-invalid-warning" class="invalid-warning form-text text-danger">Must be at least 6 characters long.</small>

                        <small class="text-muted"><label for="re-password" class="label-required">Retype Password</label></small>
                        <input id="re-password" type="password" name="re-password" class="form-control" required>
                        <small id="re-password-invalid-warning" class="invalid-warning form-text text-danger">Password does not match.</small>

                        <button class="btn btn-primary">Change Password</button>

                    </form>

                <?php } else { ?>
                    <p>
                        Hello there! Thanks for your interest in mySpires. Few basic details and your account will be good to go.
                    </p>

                    <form method="post" class="register-form" novalidate>
                        <input type="hidden" name="registration" value="1">

                        <small class="text-muted"><label for="fname" class="label-required">First Name</label></small>
                        <input id="fname" type="text" name="fname" class="form-control" placeholder="John" required>
                        <small id="fname-invalid-warning" class="invalid-warning form-text text-danger">First name cannot be empty.</small>

                        <small class="text-muted"><label for="lname">Last Name</label></small>
                        <input id="lname" type="text" name="lname" class="form-control" placeholder="Doe">

                        <small class="text-muted"><label for="email" class="label-required">Email Address</label></small>
                        <input id="email" type="text" name="email" class="form-control" placeholder="john.doe@email.com" required>
                        <small id="email-invalid-warning" class="invalid-warning form-text text-danger">Email address appears to be invalid.</small>
                        <small id="email-taken-warning" class="invalid-warning form-text text-danger">Email address has already been taken.</small>

                        <small class="text-muted"><label for="username" class="label-required">Username</label></small>
                        <input id="username" type="text" name="username" class="form-control" placeholder="johndoe" required>
                        <small id="username-invalid-warning" class="invalid-warning form-text text-danger">Should be at least 4 characters long. Only lowercase alphabets and numbers are allowed in the username.</small>
                        <small id="username-taken-warning" class="invalid-warning form-text text-danger">Username has already been taken.</small>

                        <small class="text-muted"><label for="password" class="label-required">Password</label></small>
                        <input id="password" type="password" name="password" class="form-control" required>
                        <small id="password-invalid-warning" class="invalid-warning form-text text-danger">Must be at least 6 characters long.</small>

                        <small class="text-muted"><label for="re-password" class="label-required">Retype Password</label></small>
                        <input id="re-password" type="password" name="re-password" class="form-control" required>
                        <small id="re-password-invalid-warning" class="invalid-warning form-text text-danger">Password does not match.</small>

                        <div class="g-recaptcha" data-size="normal" data-sitekey="6LetfoQUAAAAAFUizejAuy_PLWduJkEOJ2AHvcpe"></div>
                        <small id="recaptcha-warning" class="invalid-warning form-text text-danger">Please tick the recaptcha checkbox.</small>

                        <button class="btn btn-primary">Register</button>
                    </form>

                <?php } ?>
            </div>

        </div>
    </div>

    <?php include "footbar.php"; ?>

</div>

<?php include "foot.php"; ?>

</body>

</html>