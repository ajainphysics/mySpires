<?php

session_start();

include_once "lib/settings.php";
include_once "api/lib/mySpires.php";
include_once "lib/functions.php";

define("pageLabel", "history");

if (!mySpiresUser::current_username()) {
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

            <div id="page-title" class="row main-title">
                <div class="col-md-12">
                    <i id="parent-page-link" class="fa fa-history"></i>
                    <div id="title-wrapper"><h2>History</h2></div>
                </div>
            </div>

            <div class="row paper-boxes">
                <div class="col-sm-12">
                    <div class="paper-spinner-wrapper"><i class='fa fa-spinner fa-spin' aria-hidden='true'></i></div>
                </div>
            </div>

            <div id="history-load-more">
                <button type="button" class="btn btn-outline-secondary">Load More</button>
            </div>

            <!--

            <?php
            $sectionLabels = Array("today", "yesterday", "this_week", "this_month");
            $sectionNames = Array("Today", "Yesterday", "This Week", "This Month");

            $str = date("F");
            for ($i = 1; $i <= 12; $i++) {
                $str = $str . " last month";
                array_push($sectionLabels, "previous_month_" . $i);
                array_push($sectionNames, Date("F Y", strtotime($str)));
            }

            foreach ($sectionLabels as $key => $label) { ?>
                <div id="section-<?php echo $label; ?>"
                     class="row paper-boxes history-section">
                    <div class="col-sm-12">
                        <div class="paper-spinner-wrapper">
                            <i class='fa fa-spinner fa-spin' aria-hidden='true'></i>
                        </div>
                        <div id="title-<?php echo $label; ?>" class="type-header history-title">
                            <i class='openable-arrow fa fa-angle-double-down' aria-hidden='true'></i>
                            <h3><?php echo $sectionNames[$key]; ?></h3>
                        </div>
                    </div>
                </div>
            <?php } ?>

            -->


        </div> <!-- /container -->

    </div>

    <?php include "footbar.php"; ?>
</div>

<?php include "foot.php"; ?>

</body>

</html>

