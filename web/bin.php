<?php

session_start();

include_once "lib/settings.php";
include_once "lib/webapp.php";

define("pageLabel", "bin");

if (!\mySpires\users\user()) {
    header("Location: /");
    exit();
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

            <?php webapp::display_alerts(); ?>

            <nav class="title-nav navbar navbar-expand-lg navbar-light">
                <div class="main-title">
                    <i class="main-icon fas fa-trash-alt"></i>
                    <div><h1>Bin</h1></div>
                </div>
            </nav>

            <div id="empty-message">
                <img class="mySpires-logo" src="img/mySpires512_333.png" alt="mySpires">
                <p class="introduction">
                    Your bin is empty! When you delete a record from your library, it will show up here.
                </p>
            </div>

            <div class="row paper-boxes"></div>

            <div class="load-more-boxes">
                <button type="button" class="btn btn-outline-secondary mx-auto">Load More</button>
            </div>

        </div> <!-- /container -->

    </div>

    <?php include "footbar.php"; ?>
</div>

<?php include "foot.php"; ?>

</body>

</html>

