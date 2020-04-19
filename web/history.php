<?php

use function mySpires\users\username;

session_start();

include_once "lib/settings.php";
include_once "lib/webapp.php";

define("pageLabel", "history");

if (!username()) {
    header("Location: /");
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">

<?php include "head.php"; // page head ?>

<body id="page-history">

<div class="main-wrapper">

    <?php include "navbar.php"; // navbar ?>

    <div class="main-content">

        <div class="container">

            <?php webapp::display_alerts(); ?>

            <nav class="title-nav navbar navbar-expand-lg navbar-light">
                <div class="main-title">
                    <i class="main-icon fa fa-history"></i>
                    <div id="title-wrapper"><h1>History</h1></div>
                </div>

                <button class="btn btn-outline-danger ml-auto purge-history-button" type="submit">Purge History</button>
            </nav>

            <?php if(!\mySpires\users\user()->info->history_enabled) { ?>
                <div id="history-disabled">
                    <img class="mySpires-logo" src="img/mySpires512_333.png" alt="mySpires">

                    <p class="introduction">
                        Hey <?php echo \mySpires\users\user()->display_name; ?>! History is currently disabled on your mySpires account. When enabled, the mySpires browser plugin will keep track of the references you visit. You can return here at any time to review your history and save the references that you found were helpful.
                    </p>

                    <button class="btn btn-success enable-history-button">Enable History</button>

                    <p class="introduction">
                        If you change your mind later, you can always go to preferences to disable history.
                    </p>

                    <p id="residual-history-message">
                        The following activity was recorded before history was disabled. <a href="#" class="purge-history-button text-danger">Purge history</a>.
                    </p>
                </div>
            <?php } else { ?>
                <div id="empty-message">
                    <img class="mySpires-logo" src="img/mySpires512_333.png" alt="mySpires">
                    <p class="introduction">
                        Hey <?php echo \mySpires\users\user()->display_name; ?>! You currently have nothing in your mySpires history. Install the mySpires browser plugin from <a href="support.php#help-plugin">here</a> if you haven't already and visit some references to start recording.
                    </p>
                </div>
            <?php } ?>

            <div class="row paper-boxes"></div>

            <div class="load-more-boxes">
                <button type="button" class="btn btn-outline-secondary">Load More</button>
            </div>

        </div> <!-- /container -->

    </div>

    <?php include "footbar.php"; ?>
</div>

<?php include "foot.php"; ?>

</body>

</html>

