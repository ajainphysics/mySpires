<?php

session_start();

$query = $_GET["q"];

include_once "lib/settings.php";
include_once "api/lib/mySpires.php";
include_once "lib/functions.php";

define("pageLabel", "share");

?>

<!DOCTYPE html>
<html lang="en">

<?php include "head.php"; // page head ?>

<body id="page-<?php echo pageLabel; ?>">

<div class="main-wrapper">

    <?php include "navbar.php"; // navbar ?>

    <div class="main-content">

        <div class="container">

            <input type="hidden" id="sharequery" value="<?php echo $query; ?>">

            <div id="page-title" class="row main-title">
                <div class="col-md-12">
                    <i id="parent-page-link" class="fa fa-hdd-o"></i>
                    <div id="title-wrapper">
                        <h2>Library</h2>
                        <span id="page-breadcrumb"></span>
                    </div>
                </div>
            </div>

            <div class="row subtags">
                <div class="col-md-12">
                    <div class="type-header"><h3>Subtags</h3></div>
                </div>
            </div>

            <div class="row paper-boxes public">
                <div class="col-md-12">
                    <div class="type-header">
                        <div class="filters">
                            <select class="custom-select" id="filter-authors"></select>

                            <i id="filter-sort-button-bg" class='fa fa-sort'></i>
                            <select class="custom-select pull-right" id="filter-sort">
                                <option value="published">Published</option>
                            </select>
                            <button id="filter-sort-button" type="button" order="desc"
                                    class="btn btn-secondary pull-right"><i class='fa fa-sort-numeric-desc'></i>
                            </button>
                        </div>
                        <h3>Records</h3>
                    </div>
                </div>

                <div class="paper-spinner col-md-12">
                    <div class="paper-spinner-wrapper"><i class='fa fa-spinner fa-spin' aria-hidden='true'></i></div>
                </div>
            </div>
        </div> <!-- /container -->

        <!-- Bootstrap core JavaScript -->

    </div>

    <?php include "footbar.php"; ?>
</div>

<?php include "foot.php"; ?>

</body>

</html>

