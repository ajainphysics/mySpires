<?php
namespace mySa;

// Hi! I am mySa: mySpires Assistant.
// I automate various things.
// This file defines my core functionality.

date_default_timezone_set("Europe/London");
set_time_limit(0);

include_once(__DIR__ . "/mySpires.php"); // Include the mySpires library.
include_once(__DIR__ . "/../core/mySpires.php"); // Include the mySpires library.
include_once(__DIR__ . "/../library/tools.php");

use mySpires\Record,
    refSpires\RefRecords;

use function library\tools\upload_file;
use function mySpires\bibtex\bib;
use function mySpires\config;
use function mySpires\mysqli;
use function mySpires\query;
use function mySpires\users\user_list;
use function mySpires\users\username;

$error = null;

/* ============================== Stats ============================== */

function stats($label, $value = null)
{
    if (!is_array($label)) $label = explode(":", $label);

    for ($i = 0; $i < 3; $i++) {
        if (!array_key_exists($i, $label) || !$label[$i]) $label[$i] = "";
        if (strlen($label[$i]) > 50) return false;
    }

    if (!$label[0] || sizeof($label) > 3) return false;

    if ($value === null) {
        if ($query = mysqli()->prepare("SELECT value FROM stats WHERE label = ? AND sublabel = ? AND subsublabel = ?")) {
            $query->bind_param("sss", ...$label);
            $query->execute();

            if ($result = $query->get_result()->fetch_object()) {
                return $result->value;
            }
        }
    } else {
        if ($query = mysqli()->prepare("INSERT INTO stats (label, sublabel, subsublabel, value) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE value = ?")) {
            $query->bind_param("sssss", $label[0], $label[1], $label[2], $value, $value);

            if ($query->execute()) return true;
        }
    }

    return false;
}

function stat_step($label)
{
    return stats($label, stats($label) + 1);
}

function stat_step_daily($label)
{
    return stat_step($label . ":" . date("d-m-Y"));
}

/* ============================== Timers ============================== */

$call_time = null;
$quick = null;

function quick()
{
    global $quick;

    if ($quick === null) {
        $last_full_checkup = microtime(true) - stats("last_db_checkup_full");
        if ($last_full_checkup > 60 * 60 * 24 * 7) $quick = false;
        else $quick = true;
    }
    return $quick;
}

/* ============================== Logging ============================== */

$logs = [];
$logs_priority = 0;

function log($message, $priority = 0)
{
    global $logs, $logs_priority;

    $logs[] = Date("h:i:s: ") . $message;
    if ($logs_priority < $priority) $logs_priority = $priority;
}

function dump_logs()
{
    global $logs, $logs_priority;

    echo "Logs priority: " . $logs_priority . "\r\n";
    foreach ($logs as $log) {
        echo $log . "\r\n";
    }

    if ($logs_priority > 0)
        message(implode("\r\n", $logs), $logs_priority);
}

/**
 * Sends a message to the API for debugging purposes.
 * @param string $message Message to be sent.
 * @param int $priority Priority of the message.
 */
function message($message, $priority = 0)
{
    $db = mysqli();

    $backtrace = debug_backtrace();
    $config = config();
    $fileSegments = explode($config->server->document_root, $backtrace[0]["file"]);
    if (sizeof($backtrace) > 1) $sender = $backtrace[1]["function"] . "() @ " . $fileSegments[1] . ":" . $backtrace[0]["line"];
    else $sender = $fileSegments[1] . ":" . $backtrace[0]["line"];

    $username = username();
    if (!$username) $username = "anonymous";

    $sql = sprintf("INSERT INTO messages (username, sender, message, priority) VALUES ('%s', '%s', '%s', '%s')",
        $db->real_escape_string($username),
        $db->real_escape_string($sender),
        $db->real_escape_string($message),
        $db->real_escape_string($priority));

    $db->query($sql);

    return;
}

/* ============================== Database Management ============================== */

function duplicate_records($field)
{
    global $error;

    $results = query(
        "SELECT $field FROM records WHERE ($field != '' AND $field IS NOT NULL) GROUP BY $field HAVING count(*) > 1");
    if ($results->num_rows != 0) {
        $dup = array();
        while ($result = $results->fetch_object()) $dup[] = $result->$field;
        $dup = implode(", ", $dup);
        $error = "Found duplicate $field(s): $dup.";
        return true;
    }
    return false;
}

function check_duplicates()
{
    global $error;

    foreach (["inspire", "bibkey", "arxiv"] as $field) {
        if (duplicate_records($field)) {
            log("Aborting database checkup! " . $error, 5);
            return false;
        }
    }
    return true;
}

function database_select_chunks($query, $field = null)
{
    $results = query($query);

    $records = [];
    while ($result = $results->fetch_object()) {
        if (!$field) { // Pick the first result's field if not provided
            foreach (array("inspire", "arxiv", "doi", "ads") as $f) {
                if (property_exists($result, $f)) {
                    $field = $f;
                    break;
                }
            }
        }
        $records[] = $result->$field;
    }
    return (object)array("chunks" => array_chunk($records, 50), "field" => $field);
}

/**
 * Regenerate records in the database from INSPIRE or NASA-ADS server.
 * @param $filter - MySQL string for records to regenerate.
 * @param $source - "inspire" or "ads".
 * @return object
 */
function regenerate($filter, $source = "inspire")
{
    $results = database_select_chunks($filter);
    $chunks = $results->chunks;
    $field = $results->field;

    $found = [];
    $temp = [];
    $lost = [];
    foreach ($chunks as $chunk) {
        if (in_array($field, ["inspire", "arxiv", "doi", "ads"])) {
            $db_field = $field;
            if ($source == "inspire") {
                if ($field == "inspire") $db_field = "recid";
                if ($field == "arxiv") $db_field = "eprint";

                $query = implode(" OR ", array_map(function ($val) use ($db_field) {
                    return $db_field . ":" . $val;
                }, $chunk));
            } elseif ($source == "ads") {
                if ($field == "ads") $db_field = "bibcode";

                $query = implode(" OR ", array_map(function ($val) use ($db_field) {
                    return $db_field . ":\"" . $val . "\"";
                }, $chunk));
            } else return null;

            $response = null;
            if ($source == "inspire")
                $response = new RefRecords($query, (object)array("size" => sizeof($chunk)), $source);
            elseif ($source == "ads")
                $response = new RefRecords($query, (object)array("rows" => sizeof($chunk)), $source);

            if (!$response) continue;
            foreach ($response->records as $record) {
                $record = new Record($record);
                if ($record->$field && in_array($record->$field, $chunk)) {
                    $found[] = $record->$field;
                    if ($record->temp == 1) $temp[] = $record->inspire;
                    $record->sync();
                }
            }
        }

        $lost = array_merge($lost, array_diff($chunk, $found));
    }
    // self::delete_bibtex(array_diff($found,$temp), $field);

    return (object)array("found" => $found, "lost" => $lost, "temp" => $temp);
}

/**
 * Links naked arXiv IDs and DOIs to INSPIRE or NASA-ADS.
 * The idea is that every record must have either an INSPIRE-ID or NASA-ADS ID.
 * Record will be assigned a NASA-ADS ID only if it doesn't have an INSPIRE ID.
 */
function link_to_sources()
{
    // if(self::quick()) return null;

    // Generate INSPIRE for arXiv IDs which do not have INSPIRE record.
    $stats = regenerate("SELECT arxiv FROM records WHERE (inspire = '' OR inspire IS NULL) AND (arxiv != '' AND arxiv IS NOT NULL) AND no_inspire = 0");
    if (sizeof($stats->found) > 0)
        log(sizeof($stats->found) . " arXiv ids were associated with INSPIRE: " . implode(", ", $stats->found) . ".");
    if (sizeof($stats->lost) > 0) {
        $lost_list = implode(",", array_map(function ($val) {
            return "'" . $val . "'";
        }, $stats->lost));
        // Set no_inspire for lost records older than 1 month ago
        query("UPDATE records SET no_inspire = 1 
                               WHERE (arxiv IN ($lost_list) AND (published < NOW() - INTERVAL 1 MONTH))");
        log(sizeof($stats->lost) . " arXiv ids not found on INSPIRE: " . implode(", ", $stats->lost) . ".");
    }

    // Generate INSPIRE for DOIs which do not have INSPIRE record.
    $stats = regenerate("SELECT doi FROM records WHERE (inspire = '' OR inspire IS NULL) AND (doi != '' AND doi IS NOT NULL) AND no_inspire = 0");
    if (sizeof($stats->found) > 0)
        log(sizeof($stats->found) . " DOIs were associated with INSPIRE: " . implode(", ", $stats->found) . ".");
    if (sizeof($stats->lost) > 0) {
        $lost_list = implode(",", array_map(function ($val) {
            return "'" . $val . "'";
        }, $stats->lost));
        // Set no_inspire for lost records older than 1 month ago
        query("UPDATE records SET no_inspire = 1 
                               WHERE (doi IN ($lost_list) AND (published < NOW() - INTERVAL 1 MONTH))");
        log(sizeof($stats->lost) . " DOIs set to \"no_inspire\": " . implode(", ", $stats->lost) . ".");
    }

    // Generate ADS for arXiv IDs which do not have ADS record.
    $stats = regenerate("SELECT arxiv FROM records WHERE (ads = '' OR ads IS NULL) AND (arxiv != '' AND arxiv IS NOT NULL)", "ads");
    if (sizeof($stats->found) > 0)
        log(sizeof($stats->found) . " arXiv ids were associated with ADS: " . implode(", ", $stats->found) . ".");

    // Generate ADS for DOIs which do not have INSPIRE or ADS record.
    $stats = regenerate("SELECT doi FROM records WHERE (ads = '' OR ads IS NULL) AND (doi != '' AND doi IS NOT NULL)", "ads");
    if (sizeof($stats->found) > 0)
        log(sizeof($stats->found) . " DOIs were associated with ADS: " . implode(", ", $stats->found) . ".");
}

/**
 * Refreshes temporary entries from INSPIRE.
 * Should be followed by fetch_missing_bibtex().
 */
function refresh_temp_records()
{
    if (quick()) return null;

    // Refresh temporary records with an INSPIRE ID
    $stats = regenerate("SELECT inspire FROM records where (inspire != '' AND inspire IS NOT NULL) AND temp = 1");
    $perm = array_diff($stats->found, $stats->temp);
    if (sizeof($perm) > 0)
        log(sizeof($perm) . " temporary INSPIRE records were updated: " . implode(", ", $perm) . ".");

    // Refresh temporary records with an NASA-ADS ID but no INSPIRE ID
    $stats = regenerate("SELECT ads FROM records where (ads != '' AND ads IS NOT NULL) AND (inspire = '' OR inspire IS NULL) AND temp = 1", "ads");
    $perm = array_diff($stats->found, $stats->temp);
    if (sizeof($perm) > 0)
        log(sizeof($perm) . " temporary NASA-ADS records were updated: " . implode(", ", $perm) . ".");
}

/**
 * Creates database backups.
 * Keeps daily backups for the past 7 days and weekly backups within the last 6 months.
 * Backup is only performed once a day, based on if the backup file is available.
 */
function database_backup()
{
    // set the backup directory and filename
    $server = config("server");

    $dir = $server->content_root . "/database_backups/";
    $file = $dir . "ajainphysics_" . date('Y-m-d') . ".sql";

    // If backup was already done, return
    if (file_exists($file) && quick()) return;

    // perform the backup
    // Set .my.cnf in the home folder
    $opts = include(__DIR__ . "/../../../.mysqldb.php");
    $query = "HOME=/home/kz0qr7otxolj/ mysqldump --single-transaction {$opts->dbname} > {$file}";
    exec($query, $output, $error);

    if ($error) {
        message($error, 5);
        log("Database backup was interrupted!", 5);
        return;
    }

    $backups = scandir($dir);
    foreach ($backups as $backup) {
        $e = explode("ajainphysics_", $backup);
        if (key_exists("1", $e) && $e[1]) {
            $e = explode(".sql", $e[1]);
            $backup_date = strtotime($e[0]);
            $days_past = floor((time() - $backup_date) / (60 * 60 * 24));

            if ($days_past > 180 || ($days_past > 7 && date("w", $backup_date) != 0))
                unlink($dir . $backup);
        }
    }

    log("I backed up your database.");
}

/* ========== Thumbnails ========== */

/**
 * Syncs thumbnails for saved records.
 */
function sync_thumbnails()
{
    $server = config("server");

    $thumbnails = scandir($server->content_root . "/thumbnails/");
    $db = mysqli();

    $results = $db->query("SELECT id, inspire, arxiv, no_thumbnail FROM records");


    $found = [];
    $lost = [];
    while ($record = $results->fetch_object()) {
        if (!in_array($record->id . ".jpg", $thumbnails) && !$record->no_thumbnail) {
            if ($record->arxiv) $url = "https://arxiv.org/pdf/$record->arxiv.pdf";
            else continue;

            $tmp = __DIR__ . "/../../.cache/thumb.pdf";
            $filename = $server->content_root . "/thumbnails/$record->id.jpg";

            upload_file($url, $tmp);

            // break;

            try {
                $im = new \imagick();
                $im->setResolution(150, 150);
                $im->readImage($tmp . "[0]");
                $im = $im->flattenImages();
                $im->setImageFormat('jpg');
                $im->scaleImage(600, 1200, true);
                $im->writeImage($filename);
                $found[] = $record->arxiv;
            } catch (\Exception $e) {
                log("Imagick responded for record {$record->id}:" . $e->getMessage(), 1);
                $lost[] = $record->arxiv;
            }
        }
    }

    if (sizeof($found) > 0)
        log("Thumbnails for " . sizeof($found) . " records were generated: " . implode(", ", $found) . ".");
    if (sizeof($lost) > 0)
        log("Thumbnails for " . sizeof($lost) . " records were not found: " . implode(", ", $lost) . ".");
}

function sync_bibtex()
{

    // Users
    $users = user_list();
    $synced = [];
    foreach ($users as $username) {
        $res = bib($username);
        if ($res && $res->dropbox) {
            array_push($synced, $username);
        }
    }

    if (sizeof($synced) > 0)
        log("BibTeX uploaded to Dropbox for " . sizeof($synced) . " users: " . implode(", ", $synced) . ".");

    // Collaborations

    $results = query("SELECT cid, name FROM collaborations");
    $synced = [];
    while ($result = $results->fetch_object()) {
        $res = bib($result->cid, "collaboration");
        if ($res && $res->dropbox) {
            array_push($synced, $result->name);
        }
    }

    if (sizeof($synced) > 0)
        log("BibTeX synced for " . sizeof($synced) . " collaborations: " . implode(", ", $synced) . ".");
}

/**
 * The wake up call for mySa.
 */
function wake()
{
    global $call_time;

    $call_time = microtime(true);
    stat_step_daily("mySa_calls");

    // Check if mySa is busy
    if (stats("mySa_busy")) {
        log("mySa is busy.");
        dump_logs();
        return;
    }

    // Reserve mySa
    // self::stats("mySa_busy", 1);

    // Welcome
    greeting();

    // Backup database. Done once a day based on if the backup file is available.
    database_backup();

    if (check_duplicates()) {
        // Link naked arXiv IDs.
        link_to_sources();

        // Refresh temporary records. Skipped in quick mode.
        refresh_temp_records();

        // Fetch missing bibtex entries.
        // self::fetch_missing_bibtex();

        // Generate bibtex files and upload them to Dropbox
        sync_bibtex();
    }

    // Sync thumbnails
    sync_thumbnails();

    wrapup();

    // Release mySa
    stats("mySa_busy", 0);
}

/**
 * Regenerates the entire database.
 * Should not be used in an automated script.
 */
function regenerate_database()
{
    global $quick;

    $quick = false; // Disable quick mode.

    greeting();
    database_backup(); // Backup database, just in case.

    if (check_duplicates()) {
        link_to_sources(); // Link naked arXiv IDs.

        log("Initiating database regeneration from INSPIRE.");
        $stats = regenerate(
            "SELECT inspire FROM records 
                       WHERE (inspire != '' AND inspire IS NOT NULL AND updated<DATE_SUB(NOW(), INTERVAL 60 MINUTE))");
        if (sizeof($stats->found) > 0)
            log(sizeof($stats->found) . " records were regenerated from INSPIRE.");
        if (sizeof($stats->lost) > 0)
            log(sizeof($stats->lost) . " records were not found on INSPIRE: " . implode(", ", $stats->lost) . ".");

        $stats = regenerate(
            "SELECT ads FROM records where (ads != '' AND ads IS NOT NULL) AND (inspire = '' OR inspire IS NULL) AND updated<DATE_SUB(NOW(), INTERVAL 60 MINUTE)", "ads");
        if (sizeof($stats->found) > 0)
            log(sizeof($stats->found) . " records were regenerated from NASA-ADS.");
        if (sizeof($stats->lost) > 0)
            log(sizeof($stats->lost) . " records were not found on NASA-ADS: " . implode(", ", $stats->lost) . ".");

        // self::fetch_missing_bibtex();
    }

    wrapup();
}

function greeting()
{
    log("Hello there! I am mySa (mySpires assistant).");
}

function wrapup()
{
    if (!quick()) stats("last_db_checkup_full", microtime(true));
    stats("last_db_checkup", microtime(true));

    log("All done! See you later.");

    dump_logs();
}

function test()
{
    greeting();

    regenerate_database();

    wrapup();
}