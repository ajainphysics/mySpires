<?php

include_once "lib/settings.php";

null_populate($_POST, ["support-message"]);

define("pageLabel", "support");

$user = mySpiresUser::info();

$post_message = false;
if($_POST["support-message"]) {
    if($user || webapp::validate_recaptcha($_POST["g-recaptcha-response"])) {
        $subject = "[mySpires Support] " . $_POST["message-subject"];

        $msg = "The following message was received by mySpires support.\r\n\r\n";
        $msg .= "============================================================\r\n\r\n";
        if($user) $msg .= "Sender: " . $user->name . " (" . $user->username . ") <" . $_POST["message-sender"] . ">\r\n\r\n";
        else $msg .= "Sender: " . $_POST["message-sender"] . "\r\n\r\n";
        $msg .= "Subject: " . $_POST["message-subject"] . "\r\n\r\n";
        $msg .= $_POST["message-body"] . "\r\n\r\n";
        $msg .= "============================================================\r\n\r\n";
        $msg .= "mySpires.";

        $msg = wordwrap($msg, 70);

        $headers = "From: mySpires <admin@ajainphysics.com>\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        $post_message = mail("admin@ajainphysics.com", $subject, $msg, $headers);

        mySa::message($msg,1);
    }

    if($post_message) {
        webapp::alert("Message was sent!", "success", "5000");
    } else {
        webapp::alert("An error occurred!", "danger");
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
            <div class="support-form-wrapper">
                <img class="mySpires-logo" src="img/mySpires.png" alt="mySpires">

                <?php webapp::display_alerts(); ?>

                <?php if($user) { ?>
                    <p>
                        Hey <?php echo $user->first_name ?>! Thanks for using mySpires. If you have any questions or comments about the framework or would like to get involved with the project, drop me a line.
                    </p>
                <?php } else { ?>
                    <p>
                        Hello there! Thanks for checking out mySpires. If you have any questions or comments about the framework or would like to get involved with the project, drop me a line.
                    </p>
                <?php  } ?>

                Support.

                <form method="post" class="support-form" novalidate>
                    <input type="hidden" name="support-message" value="1">
                    <input id="message-username" type="hidden" name="message-username" value="<?php if($user) echo $user->username; ?>">

                    <small class="text-muted"><label for="message-subject">Subject</label></small>
                    <input id="message-subject" class="form-control" name="message-subject" type="text" placeholder="Subject">

                    <small class="text-muted"><label class="label-required" for="message-body">Message</label></small>
                    <textarea rows="4" id="message-body" class="form-control" name="message-body" placeholder="Message" required></textarea>
                    <small id="body-invalid-warning" class="invalid-warning form-text text-danger">Body of the message cannot be empty.</small>

                    <small class="text-muted"><label class="label-required" for="message-sender">Your Email Address</label></small>
                    <input id="message-sender" class="form-control" name="message-sender" type="email" placeholder="john.doe@email.com" value="<?php if($user) echo $user->email; ?>" required>
                    <small id="email-invalid-warning" class="invalid-warning form-text text-danger">Email address appears to be invalid.</small>

                    <?php if(!$user) { ?>
                        <div class="g-recaptcha" data-sitekey="6LetfoQUAAAAAFUizejAuy_PLWduJkEOJ2AHvcpe"></div>
                        <small id="recaptcha-warning" class="invalid-warning form-text text-danger">Please tick the recaptcha checkbox.</small>
                    <?php } ?>

                    <button class="btn btn-primary">Send</button>
                </form>

            </div>


        </div>
    </div>

    <?php include "footbar.php"; ?>

</div>

<?php include "foot.php"; ?>

</body>

</html>