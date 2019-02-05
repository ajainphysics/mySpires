<?php

include_once "lib/settings.php";

null_populate($_POST, ["username", "password", "remember"]);
null_populate($_GET, ["logout", "q", "source"]);

if($_GET["logout"] == 1) {
    mySpires::logout();
    header("Location: /");
}

define("pageLabel", "index");

$username = $_POST["username"];
$password = $_POST["password"];
if($_POST["remember"] == 1) $remember = true;
else $remember = false;

if($username && $password && !mySpires::login($username, $password, $remember)) {
    $loginError = "Login Failed";
}

if($_GET["source"] == "registration_successful") {
    webapp::alert("Registration was successful! Please log in to continue.", "success");
} elseif($_GET["source"] == "password_reset_requested") {
    webapp::alert("An email has been sent to your registered email address. Follow the instructions in the email to reset your password. Note that the email might be in your spam/junk folder.", "primary");
} elseif($_GET["source"] == "password_reset_successful") {
    webapp::alert("Your password has been reset. Please log in with your new credentials.", "success");
}

?>

<!DOCTYPE html>
<html lang="en">

<?php include_once "head.php"; // page head ?>

<body id="page-<?php echo pageLabel; ?>">

<?php include_once "navbar.php"; // navbar ?>
    
<div class="main-wrapper">

    <?php if(!mySpires::user()) { ?>

    <div class="welcome-header">
        <div class="container">
            <div class="row">
    
                <h1 class="welcome-head col-md-7 col-lg-8">welcome to <br> <span class="head-myspires">mySpires.</span></h1>
                    <form class="welcome-login col-md-5 col-lg-4" method="post" action="/">
                        <input id="username" class="form-control" name="username" placeholder="Username/Email">
                        <input id="password" type="password" class="form-control" name="password" placeholder="Password">
                        <button class="btn btn-primary float-right">Sign In</button>
                        <label class="custom-control custom-checkbox welcome-remember-me">
                            <input type="checkbox" class="custom-control-input" name="remember" value="1" checked>
                            <span class="custom-control-indicator"></span>
                            <span class="custom-control-description">Remember Me</span>
                        </label>
                    </form>
            </div>
        </div>
    </div>

    <?php } ?>

    <div class="busy-loader-wrapper">
        <div class="loader busy-loader"></div>
    </div>

    <div class="main-content">
        <div class="container">

            <?php webapp::display_alerts(); ?>

            <?php if(mySpires::user()) {
                $q = $_GET["q"];
                if(!$q) $q = mySpires::user()->info->inspire_query;
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
            } else { ?>

                <div class="welcome-marketing">
                    <img class="mySpires-logo" src="img/mySpires512_333.png" alt="mySpires">

                    <p class="introduction">
                        Are you a researcher or a student working in physics or mathematics? Do you find yourself at INSPIRE-HEP or arXiv quite often for your work and wouldn't mind an efficient and seamless way to organise your workflow? mySpires might be for you!
                    </p>

                    <img src="img/screenshot_library.png" alt="mySpires Library" class="img-thumbnail solo-image">

                    <p class="introduction">
                        mySpires is a free, light-weight, and completely browser-implemented reference management system. It keeps track of your references on the go while you focus on writing your research papers, dissertations, theses, or are just on a literature hunt for your next groundbreaking idea.
                    </p>

                    <a class="d-block" href="register.php">
                        <button class="btn btn-primary d-block mx-auto my-4">Try it out!</button>
                    </a>

                    <p class="introduction">
                        mySpires can directly integrate with your browser to give you a seamless experience. You never have to leave your browser or go to a dedicated website to keep track of your literature survey.
                    </p>

                    <div id="carouselExampleIndicators" class="carousel slide" data-ride="carousel">
                        <ol class="carousel-indicators">
                            <li data-target="#carouselExampleIndicators" data-slide-to="0" class="active"></li>
                            <li data-target="#carouselExampleIndicators" data-slide-to="1"></li>
                            <li data-target="#carouselExampleIndicators" data-slide-to="2"></li>
                            <li data-target="#carouselExampleIndicators" data-slide-to="3"></li>
                        </ol>
                        <div class="carousel-inner">
                            <div class="carousel-item active">
                                <img src="img/screenshot_arxiv_abs.png" class="d-block w-100" alt="arXiv Abstract">
                            </div>
                            <div class="carousel-item">
                                <img src="img/screenshot_arxiv_pdf.png" class="d-block w-100" alt="arXiv PDF">
                            </div>
                            <div class="carousel-item">
                                <img src="img/screenshot_inspire_search.png" class="d-block w-100" alt="INSPIRE-HEP Search">
                            </div>
                            <div class="carousel-item">
                                <img src="img/screenshot_inspire_record.png" class="d-block w-100" alt="INSPIRE-HEP Record">
                            </div>
                        </div>
                    </div>

                    <p class="introduction">
                        This functionality is made possible by the mySpires browser plugin. The plugin is available for <a href="https://chrome.google.com/webstore/detail/myspires/ejidfomdndeogeipjkjigaeaeohbgcpf" target="_blank">Google Chrome</a> and <a href="bin" target="_blank">Mozilla Firefox</a>.
                    </p>
                </div>

            <?php } ?>
        </div>
    </div>

    <?php include "footbar.php"; ?>

</div>

<?php include "foot.php"; ?>

</body>

</html>