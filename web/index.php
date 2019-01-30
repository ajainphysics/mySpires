<?php

include_once "lib/settings.php";

null_populate($_POST, ["username", "password", "remember"]);
null_populate($_GET, ["logout", "q", "source"]);

if($_GET["logout"] == 1) {
    mySpiresUser::logout();
    header("Location: /");
}

define("pageLabel", "index");

$username = $_POST["username"];
$password = $_POST["password"];
if($_POST["remember"] == 1) $remember = true;
else $remember = false;

if($username && $password && !mySpiresUser::login($username, $password, $remember)) {
    $loginError = "Login Failed";
}

if($_GET["source"] == "registration_successful") {
    webapp::alert("Registration was successful! Please log in to continue.", "success");
} elseif($_GET["source"] == "password_reset_requested") {
    webapp::alert("An email has been sent to your registered email address. Follow the instructions in the email to reset your password. Note that the email might be in your spam/junk folder.", "primary");
} elseif($_GET["source"] == "password_reset_successful") {
    webapp::alert("Your password has been reset. Please log in with your new credentials.", "success");
}

$userData = mySpiresUser::info();
?>

<!DOCTYPE html>
<html lang="en">

<?php include_once "head.php"; // page head ?>

<body id="page-<?php echo pageLabel; ?>">

<?php include_once "navbar.php"; // navbar ?>
    
<div class="main-wrapper">

    <div class="welcome-header">
        <div class="container">
            <div class="row">
    
                <h1 class="welcome-head col-md-7 col-lg-8">welcome to <br> <span class="head-myspires">mySpires.</span></h1>
                <?php if(!isset($userData)) { ?>
                    <form class="welcome-login col-md-5 col-lg-4" method="post">
                        <input id="username" class="form-control" name="username" placeholder="Username/Email">
                        <input id="password" type="password" class="form-control" name="password" placeholder="Password">
                        <button class="btn btn-primary float-right">Sign In</button>
                        <label class="custom-control custom-checkbox welcome-remember-me">
                            <input type="checkbox" class="custom-control-input" name="remember" value="1" checked>
                            <span class="custom-control-indicator"></span>
                            <span class="custom-control-description">Remember Me</span>
                        </label>
                    </form>
                <?php } ?>
            </div>
        </div>
    </div>
    <div class="busy-loader-wrapper">
        <div class="loader busy-loader"></div>
    </div>

    <div class="main-content">
        <div class="container">

            <?php webapp::display_alerts(); ?>

            <?php if(isset($userData)) {
                $q = $_GET["q"];
                if(!$q) $q = $userData->inspire_query;
                if(!$q) $q = "find primarch hep-th";
                ?>

                <iframe name="pseudo-search-target" src="about:blank" style="display: none;"></iframe>

                <form id="welcome-search" class="welcome-search" target="pseudo-search-target" action="about:blank" autocomplete="on">
                    <div class="form-group">
                        <input class="searchfield form-control form-control-lg" name="q" type="text"
                               placeholder="Search" value = "<?php echo $q; ?>">
                        <div>
                            <span class="fa fa-times search-bar-reset"></span>
                            <button type="submit" class="searchbtn btn btn-primary btn-lg pull-right">Go</button>
                        </div>
                    </div>
                    <small id="passwordHelpBlock" class="form-text text-muted">
                        Powered by <a href="https://inspirehep.net/" target="_blank">inspirehep.net</a>. Use SPIRES "find " or Invenio search methods. Change default in <a href="preferences.php">preferences</a>.
                    </small>
                </form>

                <nav class="search-pagination" data-rg="" data-jrec="" data-total-results="">
                    <ul class="pagination pagination-sm">
                        <li class="page-item disabled search-pagination-first">
                            <a class="page-link" href="#"><span class="fa fa-angle-double-left"></span></a>
                        </li>
                        <li class="page-item disabled search-pagination-previous">
                            <a class="page-link" href="#"><span class="fa fa-angle-left"></span></a>
                        </li>
                        <li class="page-item disabled search-pagination-status">
                            <a class="page-link" href="#"></a>
                        </li>
                        <li class="page-item disabled search-pagination-next">
                            <a class="page-link" href="#"><span class="fa fa-angle-right"></span></a>
                        </li>
                        <li class="page-item disabled search-pagination-last">
                            <a class="page-link" href="#"><span class="fa fa-angle-double-right"></span></a>
                        </li>
                    </ul>
                </nav>

                <div class="search-results" id="welcome-search-results"></div>

                <nav class="search-pagination" data-rg="" data-jrec="" data-total-results="">
                    <ul class="pagination pagination-sm">
                        <li class="page-item disabled search-pagination-first">
                            <a class="page-link" href="#"><span class="fa fa-angle-double-left"></span></a>
                        </li>
                        <li class="page-item disabled search-pagination-previous">
                            <a class="page-link" href="#"><span class="fa fa-angle-left"></span></a>
                        </li>
                        <li class="page-item disabled search-pagination-status">
                            <a class="page-link" href="#"></a>
                        </li>
                        <li class="page-item disabled search-pagination-next">
                            <a class="page-link" href="#"><span class="fa fa-angle-right"></span></a>
                        </li>
                        <li class="page-item disabled search-pagination-last">
                            <a class="page-link" href="#"><span class="fa fa-angle-double-right"></span></a>
                        </li>
                    </ul>
                </nav>

                <?php
            } ?>
        </div>
    </div>

    <?php include "footbar.php"; ?>

</div>

<?php include "foot.php"; ?>

</body>

</html>