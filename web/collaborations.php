<?php

session_start();

include_once "lib/settings.php";
include_once "api/lib/mySpires.php";
include_once "lib/functions.php";

define("pageLabel", "collaborations");

if (!mySpires::user()) {
    header("Location: /");
    exit();
}

$results = mySpires::db_query("SELECT cid FROM collaborations ORDER BY name");
$user_collaborations = [];
$user_pending_collaborations = [];

while($result = $results->fetch_object()) {
    $collaboration = new mySpires_Collaboration($result->cid);

    if(in_array(mySpires::user()->username, $collaboration->collaborators))
        array_push($user_collaborations, $collaboration);
    elseif(in_array(mySpires::user()->username, $collaboration->pending_collaborators))
        array_push($user_pending_collaborations, $collaboration);
}

?>

<!DOCTYPE html>
<html lang="en">

<?php include "head.php"; // page head ?>

<body id="page-history">

<div class="main-wrapper">

    <?php include "navbar.php"; // navbar ?>

    <div class="busy-loader-wrapper">
        <div class="loader busy-loader"></div>
    </div>

    <div class="main-content">

        <div class="container">

            <?php webapp::display_alerts(); ?>

            <nav id="title-nav" class="navbar navbar-expand-lg navbar-light">
                <div id="page-title" class="main-title">
                    <i id="parent-page-link" class="fas fa-handshake"></i>
                    <div id="title-wrapper"><h2>Collaborations</h2></div>
                </div>
            </nav>

            <div>
                <?php foreach($user_collaborations as $collaboration) { ?>
                    <div class="collaboration-box">
                        <h3><?php echo $collaboration->name; ?></h3>

                        <?php foreach($collaboration->people() as $collaborator) {
                            if(in_array($collaborator, $collaboration->collaborators))
                                $status = "active";
                            elseif(in_array($collaborator, $collaboration->pending_collaborators))
                                $status = "pending";
                            elseif(in_array($collaborator, $collaboration->suggested_collaborators))
                                $status = "suggested";

                            $collaborator = new mySpires_User($collaborator);
                            ?>
                        <div class="collaborator-box collaborator-<?php echo $status ?>">
                            <p class="name"><?php echo $collaborator->name; ?></p>
                            <p class="details">
                                <span class="username"><?php echo $collaborator->username; ?></span>
                                <span class="sep">&bull;</span>
                                <span class="email"><?php echo $collaborator->email; ?></span>
                            </p>
                        </div>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>

        </div> <!-- /container -->

    </div>

    <?php include "footbar.php"; ?>
</div>

<?php include "foot.php"; ?>

</body>

</html>

