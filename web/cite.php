<?php

/**
 * The template file for citations page.
 *
 *
 */

use function library\tools\null_populate;

session_start();

include_once "lib/settings.php";

null_populate($_GET, ["tag", "a"]);

define("pageLabel", "cite");

$tag = $_GET["tag"];

if (!\mySpires\users\username()) {
    header("Location: /");
    exit();
}

$a = $_GET["a"];
if(!$a) $a = \mySpires\users\user()->info->inspire_username;
?>

<!DOCTYPE html>
<html lang="en">

<?php include_once "head.php"; // page head ?>

<body id="page-<?php echo pageLabel; ?>">
    
<div class="main-wrapper">

    <?php include_once "navbar.php"; // navbar ?>

    <div class="main-content">
        <div class="container">

            <?php webapp::display_alerts(); ?>

            <nav class="title-nav navbar navbar-expand-lg navbar-light">
                <div class="main-title">
                    <i class="main-icon fas fa-user-tag" aria-hidden="true"></i>
                    <div><h2>Author Citations</h2></div>
                </div>
            </nav>

            <iframe name="pseudo-search-target" src="about:blank" style="display: none;"></iframe>

            <form class="main-search-bar" target="pseudo-search-target" action="about:blank" autocomplete="on">
                <div class="form-group">
                    <input class="search-field form-control form-control-lg" name="q" type="text"
                           placeholder="Search" value = "<?php echo $a; ?>">
                    <div>
                        <span class="fa fa-times search-reset"></span>
                        <button type="submit" class="search-button btn btn-primary btn-lg pull-right">Go</button>
                    </div>
                </div>
                <small class="form-text text-muted">
                    Powered by <a href="https://inspirehep.net/" target="_blank">inspirehep.net</a>. Use author name e.g. "Jain, Akash" or INSPIRE author ID e.g. "A.Jain.5". Add a default author ID (like your own) in <a href="preferences.php">preferences</a>.
                </small>
                <small class="form-text text-muted">
                    Only citations for the 25 most recent records are shown that are cited within the last 365 days, with a maximum of 250 citations per paper.
                </small>
            </form>

            <div id="empty-message">
                <img class="mySpires-logo" src="img/mySpires512_333.png" alt="mySpires">
                <p class="introduction">
                    Type in the name of an author in the search-box above and we will give you a list of all their citations in the past year.
                </p>
            </div>

            <div id="no-citations-message">No citations!</div>

            <div id="cite-results"></div>

        </div>
    </div>

    <?php include "footbar.php"; ?>

</div>

<?php include "foot.php"; ?>

</body>

</html>