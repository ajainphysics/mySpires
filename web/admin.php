<?php

use mySpires\Record;
use function library\tools\null_populate;
use function mySpires\mysqli;
use function mySpires\config;
use function mySpires\query;

session_start();

include_once "lib/settings.php";

define("pageLabel", "admin");

null_populate($_POST, ["clear_messages"]);
null_populate($_GET, ["p", "user"]);

if (!\mySpires\users\admin()) {
    header("Location: " . webRoot);
    exit();
}

$db = mysqli();

if ($_POST["clear_messages"]) {
    $db->query("TRUNCATE TABLE messages");
}

if ($_GET["p"]) $sub_page = $_GET["p"];
else $sub_page = "";

if ($_GET["user"]) {
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

                <nav class="title-nav navbar navbar-expand-lg navbar-light">
                    <div class="main-title">
                        <i class="main-icon fa fa-user-secret"></i>
                        <div><h1>Admin Panel</h1></div>
                    </div>
                </nav>

                <section>
                    <div class="panel-head">
                        <h4>mySa</h4>
                        <form method="post">
                            <a id="mysa-wake-btn" class="btn btn-primary btn-sm" href="#">Wake mySa</a>
                        </form>
                    </div>
                    <div id="mySa-wrapper">
                        <pre id="mysa-output"></pre>
                    </div>
                </section>

                <section>
                    <div class="panel-head">
                        <h4>Messages</h4>
                        <form method="post">
                            <input type="hidden" name="clear_messages" value="1">
                            <button class="btn btn-link btn-sm">Clear</button>
                        </form>
                    </div>
                    <div id="messages-wrapper" class="stat-table">
                        <table class="table table-striped table-bordered table-sm">
                            <tbody>
                            <?php
                            $results = $db->query("SELECT * FROM messages ORDER BY timestamp DESC LIMIT 100");

                            while ($result = $results->fetch_object()) { ?>
                                <tr>
                                    <th scope="row" style="min-width: 100px;"><?php echo $result->timestamp; ?></th>
                                    <td>
                                        <a href="preferences.php?user=<?php echo $result->username; ?>"><?php echo $result->username; ?></a>
                                    </td>
                                    <td><?php echo "<pre>" . $result->message . "</pre>"; ?></td>
                                    <td><?php echo $result->sender; ?></td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section>
                    <?php
                    $records = query("SELECT count(*) AS total FROM records");

                    $users = query("SELECT * FROM users ORDER BY last_seen DESC");
                    $user_stats = [];
                    while ($user = $users->fetch_object()) {
                        $stats = $user;

                        $records = query("SELECT count(*) AS total FROM entries WHERE username='{$user->username}' AND bin = 0");
                        $stats->entries = $records->fetch_assoc()["total"];

                        $records = query("SELECT count(*) AS total FROM entries WHERE username='{$user->username}' AND bin = 1");
                        $stats->bin = $records->fetch_assoc()["total"];

                        $records = query("SELECT count(*) AS total FROM custom_bibtex WHERE username='{$user->username}'");
                        $stats->custom_bibtex = $records->fetch_assoc()["total"];

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
                                <th scope="col" class="text-center"><i class="fas fa-database"></i></th>
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
                                    <th scope="row">
                                        <a href="preferences.php?user=<?php echo $username; ?>"><?php echo $username; ?></a>
                                    </th>
                                    <td class="text-center">
                                        <?php
                                        echo $stats->entries;
                                        if ($stats->bin) echo " (+" . $stats->bin . ")";
                                        ?>
                                    </td>
                                    <td class="text-center"><?php if($stats->custom_bibtex) echo $stats->custom_bibtex; ?></td>
                                    <td class="text-center"><?php echo pretty_date_format($stats->last_seen); ?></td>
                                    <td class="text-center"><?php echo $stats->plugin_version; ?></td>
                                    <td class="text-center">
                                        <?php
                                        if ($stats->dbxtoken) echo "<i class='fas fa-thumbs-up'></i>";
                                        elseif (!$stats->dbx_reminder) echo "<i class='fas fa-thumbs-down text-secondary'></i>";
                                        ?>
                                    </td>
                                    <td class="text-center"><?php if ($stats->history_enabled) echo "<i class='fas fa-thumbs-up'></i>"; ?> </td>
                                    <td class="text-center"><?php echo $stats->first_name . " " . $stats->last_name; ?></td>
                                    <td class="text-center"><?php echo $stats->email; ?></td>
                                </tr>
                            <? }
                            ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!--
            <section>
                <?php
                /*
                $stats = [];

                $results = mySpires::db_query("SELECT * FROM stats WHERE label = 'api_calls'");
                $stats["api_calls"] = ["dates" => [], "values" => []];
                while($result = $results->fetch_object()) {
                    array_push($stats["api_calls"]["dates"], $result->sublabel);
                    array_push($stats["api_calls"]["values"], $result->value);
                }

                $results = mySpires::db_query("SELECT * FROM stats WHERE label = 'mySa_calls'");
                $stats["mySa_calls"] = ["dates" => [], "values" => []];
                while($result = $results->fetch_object()) {
                    array_push($stats["mySa_calls"]["dates"], $result->sublabel);
                    array_push($stats["mySa_calls"]["values"], $result->value);
                }

                $results = mySpires::db_query("SELECT * FROM stats WHERE label = 'api_plugin_calls'");
                $stats["api_plugin_calls"] = ["dates" => [], "values" => []];
                while($result = $results->fetch_object()) {
                    array_push($stats["api_plugin_calls"]["dates"], $result->sublabel);
                    array_push($stats["api_plugin_calls"]["values"], $result->value);
                }

                file_put_contents(__DIR__ . "/stats.json",
                    json_encode($stats, JSON_UNESCAPED_SLASHES));
                */
                ?>

                <div class="panel-head"><h4>Data</h4></div>

                <canvas id="myChart" width="400" height="200"></canvas>
            </section> -->

                <?php
                function linked_id($id)
                {
                    return "<a href='#record-" . $id . "'>" . $id . "</a>";
                }

                ?>

                <section>
                    <div class="panel-head"><h4>Records</h4></div>

                    <div id="records-list-wrapper" class="stat-table">
                        <table class="table table-striped table-sm">
                            <thead class="thead-dark">
                            <tr>
                                <th scope="col">ID</th>
                                <th scope="col">arXiv</th>
                                <th scope="col">INSPIRE</th>
                                <th scope="col">BibKey</th>
                                <th scope="col">ADS</th>
                                <th scope="col">Title</th>
                                <th scope="col">Author(s)</th>
                                <th scope="col">DOI</th>
                                <th scope="col"><i class="far fa-image"></i></th>
                                <th scope="col"><i class="fa fas fa-database"></i></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php
                            $server = config("server");
                            $records = query("SELECT * FROM records ORDER BY id DESC");
                            $thumbnails = scandir($server->content_root . "/thumbnails/");

                            $current_id = null;
                            $missing_ids = [];
                            $missing_thumbnails = [];
                            $missing_bibtex = [];
                            $all_ids = [];
                            while ($record = $records->fetch_object()) {
                                $record = new Record($record);

                                array_push($all_ids, $record->id);
                                if (!$current_id) $current_id = $record->id;
                                while ($record->id != $current_id) {
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
                                    <td style="min-width: 19ex;">
                                        <a href="//arxiv.org/abs/<?php echo $record->arxiv; ?>" target="_blank">
                                            <?php echo $record->arxiv; ?></a>
                                    </td>
                                    <td><a href="//inspirehep.net/record/<?php echo $record->inspire; ?>"
                                           target="_blank">
                                            <?php echo $record->inspire; ?></a></td>
                                    <td><?php echo $record->bibkey; ?></td>
                                    <td><a href="//ui.adsabs.harvard.edu/abs/<?php echo $record->ads; ?>/abstract"
                                           target="_blank">
                                            <?php echo $record->ads; ?></a></td>
                                    <td><?php echo $record->title; ?></td>
                                    <td><?php echo utf8_decode($record->author_lastnames()); ?></td>
                                    <td><a href="//doi.org/<?php echo $record->doi; ?>" target="_blank">
                                        <?php echo $record->doi; ?></td>
                                    <td>
                                        <?php if ($record->arxiv && !in_array($record->id . ".jpg", $thumbnails)) {
                                            array_push($missing_thumbnails, $record->id);
                                            ?>
                                            <i class="fa fas fa-times"></i>
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <?php if (!$record->bibtex) {
                                            array_push($missing_bibtex, $record->id);
                                            ?>
                                            <i class="fa fas fa-times"></i>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>

                    <p>Missing IDs (<?php echo sizeof($missing_ids) ?>):
                        <?php echo implode(", ", $missing_ids); ?>.</p>

                    <?php if (sizeof($missing_thumbnails)) { ?>
                        <p>Missing thumbnails (<?php echo sizeof($missing_thumbnails) ?>):
                            <?php echo implode(", ", array_map("linked_id", $missing_thumbnails)); ?>.</p>
                    <?php } ?>

                    <?php if (sizeof($missing_bibtex)) { ?>
                        <p>Missing BibTeX (<?php echo sizeof($missing_bibtex) ?>):
                            <?php echo implode(", ", array_map("linked_id", $missing_bibtex)); ?>.</p>
                    <?php } ?>
                </section>

                <section>
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
                            $entries = query("SELECT * FROM entries ORDER BY updated DESC");
                            $missing_entries = [];
                            while ($entry = $entries->fetch_object()) {
                                if (in_array($entry->id, $missing_ids))
                                    array_push($missing_entries, $entry->username . ":" . $entry->id);
                                ?>
                                <tr>
                                    <th scope="row"><?php echo $entry->username; ?></th>
                                    <th scope="row"><?php echo linked_id($entry->id); ?></th>
                                    <td><?php echo $entry->tags; ?></td>
                                    <td><?php echo $entry->comments; ?></td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>

                    <?php
                    if ($missing_entries)
                        echo "Missing entries: " . implode(", ", $missing_entries) . ".";
                    ?>
                </section>

                <section>
                    <div class="panel-head"><h4>Database Backups</h4></div>
                    <?php
                    $server = config("server");
                    $latest_date = null;
                    $backups = array_filter(scandir($server->content_root . "/database_backups/"), function ($a) {
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
                </section>
            </div>
        </div>

        <?php include "footbar.php"; ?>

    </div>

    <?php include "foot.php"; ?>

    </body>

    </html>

<?php

function pretty_date_format($date)
{
    if (!$date) return "";

    if (is_string($date)) $date = strtotime($date);

    switch (floor((time() - $date) / (60 * 60 * 24))) {
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
            if (date("Y", $date) == date("Y"))
                return date("d M", $date);
            else
                return date("d M Y", $date);
    }
}

?>