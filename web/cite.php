<?php

/**
 * The template file for citations page.
 *
 *
 */

session_start();

include_once "lib/settings.php";
include_once "api/lib/mySpires.php";
include_once "lib/functions.php";

null_populate($_GET, ["tag", "a"]);

define("pageLabel", "cite");

$tag = $_GET["tag"];

if (!mySpires::username()) {
    header("Location: /");
    exit();
}

$a = $_GET["a"];
if(!$a) $a = mySpires::user()->info->inspire_username;
if(!$a) $a = "Jain, Akash";
?>

<!DOCTYPE html>
<html lang="en">

<?php include_once "head.php"; // page head ?>

<body id="page-<?php echo pageLabel; ?>">
    
<div class="main-wrapper">

    <?php include_once "navbar.php"; // navbar ?>

    <div class="busy-loader-wrapper">
        <div class="loader busy-loader"></div>
    </div>

    <div class="main-content">
        <div class="container">

            <?php webapp::display_alerts(); ?>

            <nav id="title-nav" class="navbar navbar-expand-lg navbar-light">

                <div id="page-title" class="main-title">
                    <i id="parent-page-link" class="fas fa-user-tag" aria-hidden="true"></i>
                    <div id="title-wrapper">
                        <h2>Author Citations</h2>
                    </div>
                </div>
            </nav>

            <iframe name="pseudo-search-target" src="about:blank" style="display: none;"></iframe>

            <form id="welcome-search" class="welcome-search" target="pseudo-search-target" action="about:blank" autocomplete="on">
                <div class="form-group">
                    <input class="searchfield form-control form-control-lg" name="q" type="text"
                           placeholder="Search" value = "<?php echo $a; ?>">
                    <div>
                        <span class="fa fa-times search-bar-reset"></span>
                        <button type="submit" class="searchbtn btn btn-primary btn-lg pull-right">Go</button>
                    </div>
                </div>
                <small id="passwordHelpBlock" class="form-text text-muted">
                    Powered by <a href="https://inspirehep.net/" target="_blank">inspirehep.net</a>. Use author name e.g. "Jain, Akash" or INSPIRE author ID e.g. "A.Jain.5". Change default in <a href="preferences.php">preferences</a>.
                </small>
            </form>

            <div id="cite-results"></div>

        </div>
    </div>

    <?php include "footbar.php"; ?>

</div>

<?php include "foot.php"; ?>

</body>

</html>