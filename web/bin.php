<?php

session_start();

include_once "lib/settings.php";
include_once "api/lib/mySpires.php";
include_once "lib/functions.php";

define("pageLabel", "bin");

if (!mySpiresUser::current_username()) {
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

            <div id="page-title" class="row main-title">
                <div class="col-md-12">
                    <i id="parent-page-link" class="fas fa-trash-alt"></i>
                    <div id="title-wrapper"><h2>Bin</h2></div>
                </div>
            </div>

            <div class="row paper-boxes">
                <div class="col-sm-12">
                    <div class="paper-spinner-wrapper"><i class='fa fa-spinner fa-spin' aria-hidden='true'></i></div>
                </div>
            </div>

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

