<?php

use mySpires\Collaboration;
use mySpires\User;
use function mySpires\query;
use function mySpires\users\user;

session_start();

include_once "lib/settings.php";
include_once "lib/webapp.php";

define("pageLabel", "collaborations");

if (!user()) {
    header("Location: /");
    exit();
}

$results = query("SELECT cid FROM collaborations ORDER BY name");
$user_collaborations = [];
$user_pending_collaborations = [];

while($result = $results->fetch_object()) {
    $collaboration = new Collaboration($result->cid);

    if(in_array(user()->username, $collaboration->collaborators))
        array_push($user_collaborations, $collaboration);
    elseif(in_array(user()->username, $collaboration->pending_collaborators))
        array_push($user_pending_collaborations, $collaboration);
}

?>

<!DOCTYPE html>
<html lang="en">

<?php include "head.php"; // page head ?>

<body id="page-collaborations">

<div class="main-wrapper">

    <?php include "navbar.php"; // navbar ?>

    <div class="main-content">

        <div class="container">

            <?php webapp::display_alerts(); ?>

            <nav class="title-nav navbar navbar-expand-lg navbar-light">
                <div class="main-title">
                    <i class="main-icon fas fa-handshake"></i>
                    <div><h2>Collaborations</h2></div>
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

                            $collaborator = new User($collaborator);
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

