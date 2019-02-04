<?php

session_start();

include_once "lib/settings.php";
include_once "api/lib/mySpires.php";
include_once "lib/functions.php";

define("pageLabel", "admin");

null_populate($_POST, ["clear_messages"]);
null_populate($_GET, ["p", "user"]);

if (!mySpiresUser::auth()) {
    header("Location: " . webRoot);
    exit();
}

$db = mySpires::db();

if($_POST["clear_messages"]) {
    $db->query("TRUNCATE TABLE messages");
}

if($_GET["p"]) $sub_page = $_GET["p"];
else $sub_page = "";

if($_GET["user"]) {
    $sub_page = "user";
}

?>

<!DOCTYPE html>
<html lang="en">

<?php include_once "head.php"; // page head ?>

<body id="page-<?php echo pageLabel; ?>">

<?php include_once "navbar.php"; // navbar ?>

<div class="main-wrapper">
    <div class="main-content">
        <div class="container">

            <?php webapp::display_alerts(); ?>

            <div id="page-title" class="row main-title">
                <div class="col-md-12">
                    <i id="parent-page-link" class="fa fa-user-secret"></i>
                    <div id="title-wrapper"><h2>Admin Panel</h2></div>
                </div>
            </div>

            <div class="panel-head">
                <h4>Messages</h4>
                <div style="margin-top: 15px;">
                    <form method="post">
                        <input type="hidden" name="clear_messages" value="1">
                        <button class="btn btn-link">Clear</button>
                    </form>
                </div>
            </div>
            <div id="messages-wrapper" class="stat-table">
                <table class="table table-striped table-bordered table-sm">
                    <tbody>
                    <?php
                    $results = $db->query("SELECT * FROM messages ORDER BY timestamp DESC LIMIT 100");

                    while ($result = $results->fetch_object()) { ?>
                        <tr>
                            <th scope="row" style="min-width: 100px;"><?php echo $result->timestamp; ?></th>
                            <td><a href="preferences.php?user=<?php echo $result->username; ?>"><?php echo $result->username; ?></a></td>
                            <td><?php echo "<pre>" . $result->message .  "</pre>"; ?></td>
                            <td><?php echo $result->sender; ?></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>

            <?php
            $records = mySpires::db_query("SELECT count(*) AS total FROM records");

            $users = mySpires::db_query("SELECT * FROM users ORDER BY last_seen DESC");
            $user_stats = [];
            while ($user = $users->fetch_object()) {
                $stats = $user;

                $records = mySpires::db_query("SELECT count(*) AS total FROM entries WHERE username='{$user->username}' AND bin = 0");
                $stats->entries = $records->fetch_assoc()["total"];

                $records = mySpires::db_query("SELECT count(*) AS total FROM entries WHERE username='{$user->username}' AND bin = 1");
                $stats->bin = $records->fetch_assoc()["total"];

                $user_stats[$user->username] = $stats;
            }

            ?>
            <div class="panel-head"><h4>User Stats</h4></div>

            <div id="user-stats-wrapper" class="stat-table">
                <table class="table table-striped table-sm">
                    <thead class="thead-dark">
                    <tr>
                        <th scope="col"></th>
                        <th scope="col" class="text-center"><i class="fas fa-hdd"></i></th>
                        <th scope="col" class="text-center"><i class="fas fa-sign-in-alt"></i></th>
                        <th scope="col" class="text-center"><i class="fab fa-chrome"></i></th>
                        <th scope="col" class="text-center"><i class="fab fa-dropbox"></i></th>
                        <th scope="col" class="text-center"><i class="fas fa-history"></i></th>
                        <th scope="col" class="text-center"><i class="fas fa-user"></i></th>
                        <th scope="col" class="text-center"><i class="fas fa-at"></i></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    foreach ($user_stats as $username => $stats) { ?>
                        <tr>
                            <th scope="row"><a href="preferences.php?user=<?php echo $username; ?>"><?php echo $username; ?></a></th>
                            <td class="text-center">
                                <?php
                                echo $stats->entries;
                                if($stats->bin)  echo " (+" . $stats->bin . ")";
                                ?>
                            </td>
                            <td class="text-center"><?php echo pretty_date_format($stats->last_seen); ?></td>
                            <td class="text-center"><?php echo $stats->plugin_version; ?></td>
                            <td class="text-center">
                                <?php
                                if($stats->dbxtoken) echo "<i class='fas fa-thumbs-up'></i>";
                                elseif(!$stats->dbx_reminder) echo "<i class='fas fa-thumbs-down text-secondary'></i>";
                                ?>
                            </td>
                            <td class="text-center"><?php if($stats->history_enabled) echo "<i class='fas fa-thumbs-up'></i>"; ?> </td>
                            <td class="text-center"><?php echo $stats->first_name . " " . $stats->last_name; ?></td>
                            <td class="text-center"><?php echo $stats->email; ?></td>
                        </tr>
                    <? }
                    ?>
                    </tbody>
                </table>
            </div>

            <div class="panel-head"><h4>Records</h4></div>

            <div id="records-list-wrapper" class="stat-table">
                <table class="table table-striped table-sm">
                    <thead class="thead-dark">
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">arXiv</th>
                        <th scope="col">INSPIRE</th>
                        <th scope="col">Title</th>
                        <th scope="col">Author(s)</th>
                        <th scope="col">DOI</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    $records = mySpires::db_query("SELECT * FROM records ORDER BY id DESC");
                    $current_id = null;
                    $missing_ids = [];
                    $all_ids = [];
                    while ($record = $records->fetch_object()) {
                        array_push($all_ids, $record->id);
                        if(!$current_id) $current_id = $record->id;
                        while($record->id != $current_id) {
                            array_push($missing_ids, $current_id);
                            $current_id--;
                        }
                        $current_id--;
                        ?>
                        <tr>
                            <th scope="row">
                                <div id="record-<?php echo $record->id; ?>" class="fake-anchor"></div>
                                <?php echo $record->id; ?>
                            </th>
                            <td><a href="//arxiv.org/abs/<?php echo $record->arxiv; ?>" target="_blank">
                                    <?php echo $record->arxiv; ?></a></td>
                            <td><a href="//inspirehep.net/record/<?php echo $record->inspire; ?>" target="_blank">
                                    <?php echo $record->inspire; ?></a></td>
                            <td><?php echo $record->title; ?></td>
                            <td><?php echo utf8_decode($record->author); ?></td>
                            <td><a href="//doi.org/<?php echo $record->doi; ?>" target="_blank">
                                <?php echo $record->doi; ?></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
            Missing IDs: <?php echo implode(", ", $missing_ids); ?>.

            <div class="panel-head"><h4>Entries</h4></div>

            <div id="entries-list-wrapper" class="stat-table">
                <table class="table table-striped table-sm">
                    <thead class="thead-dark">
                    <tr>
                        <th scope="col">Username</th>
                        <th scope="col">Record</th>
                        <th scope="col">Tags</th>
                        <th scope="col">Comments</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    $entries = mySpires::db_query("SELECT * FROM entries ORDER BY updated DESC");
                    $missing_entries = [];
                    while ($entry = $entries->fetch_object()) {
                        if(in_array($entry->id, $missing_ids))
                            array_push($missing_entries, $entry->username . ":" . $entry->id);
                        ?>
                        <tr>
                            <th scope="row"><?php echo $entry->username; ?></th>
                            <th scope="row"><a href="#record-<?php echo $entry->id; ?>"><?php echo $entry->id; ?></a></th>
                            <td><?php echo $entry->tags; ?></td>
                            <td><?php echo $entry->comments; ?></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>

            <?php
            if($missing_entries)
                echo "Missing entries: " . implode(", ", $missing_entries) . ".";
            ?>

            <div class="panel-head"><h4>Database Backups</h4></div>
            <?php
            $latest_date = null;
            $backups = array_filter(scandir(mySpires::content_root() . "/database_backups/"), function ($a) {
                $e = explode("ajainphysics_", $a);
                if (array_key_exists(1, $e)) return true;
                else return false;
            });

            echo "Total " . sizeof($backups) . " backups found. ";

            foreach ($backups as $backup) {
                $e = explode("ajainphysics_", $backup);
                $e = explode(".sql", $e[1]);
                $date = strtotime($e[0]);

                if (!$latest_date || $date > $latest_date) {
                    $latest_date = $date;
                }
            }

            echo "Latest: " . pretty_date_format($latest_date) . ".";

            ?>
        </div>
    </div>

    <?php include "footbar.php"; ?>

</div>

<?php include "foot.php"; ?>

</body>

</html>

<?php

function pretty_date_format($date) {
    if(!$date) return "";

    if(is_string($date)) $date = strtotime($date);

    switch(floor((time() - $date)/(60*60*24))) {
        case -1:
            return "tomorrow";
            break;
        case 0:
            return "today";
            break;
        case 1:
            return "yesterday";
            break;
        default:
            if(date("Y", $date) == date("Y"))
                return date("d M", $date);
            else
                return date("d M Y", $date);
    }
}

?>