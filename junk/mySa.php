<?php

// Hi! I am mySa: mySpires Assistant.
// I automate various things.
// This file defines my core functionality.

date_default_timezone_set("Europe/London");
set_time_limit(0);

include_once (__DIR__ . "/mySpires.php"); // Include the mySpires library.
include_once (__DIR__ . "/../core/mySpires.php"); // Include the mySpires library.
include_once (__DIR__ . "/../library/tools.php");

use refSpires\RefRecords;
use function library\tools\upload_file;
use function mySpires\bibtex\bib;
use function mySpires\mysqli;
use function mySpires\query;
use function mySpires\users\user_list;
use function mySpires\users\username;

class mySa {
    static private $error;

    /* ========== Stats ========== */

    static function stats($label, $value = null) {
        if(!is_array($label)) $label = explode(":",  $label);

        for($i = 0; $i < 3; $i++) {
            if(!array_key_exists($i, $label) || !$label[$i]) $label[$i] = "";
            if(strlen($label[$i]) > 50) return false;
        }

        if(!$label[0] || sizeof($label) > 3) return false;

        if($value === null) {
            if($query = mysqli()->prepare("SELECT value FROM stats WHERE label = ? AND sublabel = ? AND subsublabel = ?")) {
                $query->bind_param("sss", ...$label);
                $query->execute();

                if($result = $query->get_result()->fetch_object()) {
                    return $result->value;
                }
            }
        }
        else {
            if($query = mysqli()->prepare("INSERT INTO stats (label, sublabel, subsublabel, value) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE value = ?")) {
                $query->bind_param("sssss", $label[0], $label[1], $label[2], $value, $value);

                if($query->execute()) return true;
            }
        }

        return false;
    }

    static function stat_step($label) {
        return \mySa\stats($label, \mySa\stats($label) + 1);
    }

    static function stat_step_daily($label) {
        return \mySa\stat_step($label . ":" . date("d-m-Y"));
    }

    /* ========== Timers ========== */

    static $call_time = null;
    /*
    static private function timer(string $type) {
        $last_hourly = self::stats("mySa_checkup_hourly");
        $last_daily = self::stats("mySa_checkup_daily");
        $last_weekly = self::stats("mySa_checkup_weekly");

        if($type == "hourly" && self::$call_time - $last_hourly > 60 * 60) {
            self::stats("mySa_checkup_hourly", self::$call_time);
            return true;
        }

        if($type == "daily" && self::$call_time - $last_daily > 60 * 60 * 24) {
            self::stats("mySa_checkup_daily", self::$call_time);
            return true;
        }

        if($type == "weekly" && self::$call_time - $last_weekly > 60 * 60 * 24 * 7) {
            self::stats("mySa_checkup_weekly", self::$call_time);
            return true;
        }

        return false;
    }
    */

    static $quick = null;
    static private function quick() {
        if(self::$quick === null) {
            $last_full_checkup = microtime(true) - self::stats("last_db_checkup_full");
            if($last_full_checkup > 60 * 60 * 24 * 7) self::$quick = false;
            else self::$quick = true;
        }
        return self::$quick;
    }

    /* ========== Database Management ========== */

    static private function duplicate_records($field) {
        $results = query(
            "SELECT $field FROM records WHERE ($field != '' AND $field IS NOT NULL) GROUP BY $field HAVING count(*) > 1");
        if($results->num_rows != 0) {
            $dup = Array();
            while($result = $results->fetch_object()) $dup[] = $result->$field;
            $dup = implode(", ", $dup);
            self::$error = "Found duplicate $field(s): $dup.";
            return true;
        }
        return false;
    }

    static private function check_duplicates() {
        foreach(["inspire","bibkey","arxiv"] as $field) {
            if(self::duplicate_records($field)) {
                self::log("Aborting database checkup! " . self::$error,5);
                return false;
            }
        }
        return true;
    }

    static private function database_select_chunks($query, $field = null) {
        $results = query($query);

        $records = [];
        while($result = $results->fetch_object()) {
            if(!$field) { // Pick the first result's field if not provided
                foreach(Array("inspire", "arxiv", "doi", "ads") as $f) {
                    if (property_exists($result, $f)) {
                        $field = $f;
                        break;
                    }
                }
            }
            $records[] = $result->$field;
        }
        return (object)Array("chunks" => array_chunk($records, 50), "field" => $field);
    }

    /**
     * Regenerate records in the database from INSPIRE or NASA-ADS server.
     * @param $filter - MySQL string for records to regenerate.
     * @param $source - "inspire" or "ads".
     * @return object
     */
    static private function regenerate($filter, $source = "inspire") {
        $results = self::database_select_chunks($filter);
        $chunks = $results->chunks;
        $field = $results->field;

        $found = [];
        $temp = [];
        $lost = [];
        foreach ($chunks as $chunk) {
            if(in_array($field, ["inspire", "arxiv", "doi", "ads"])) {
                $db_field = $field;
                if($source == "inspire") {
                    if($field == "inspire") $db_field = "recid";
                    if($field == "arxiv") $db_field = "eprint";

                    $query = implode(" OR ", array_map(function($val) use($db_field) {
                        return $db_field.":".$val;
                    }, $chunk));
                }
                elseif($source == "ads") {
                    if($field == "ads") $db_field = "bibcode";

                    $query = implode(" OR ", array_map(function($val) use($db_field) {
                        return $db_field.":\"".$val."\"";
                    }, $chunk));
                }
                else return null;

                $response = null;
                if($source == "inspire")
                    $response = new RefRecords($query, (object)Array("size" => sizeof($chunk)), $source);
                elseif($source == "ads")
                    $response = new RefRecords($query, (object)Array("rows" => sizeof($chunk)), $source);

                if(!$response) continue;
                foreach ($response->records as $record) {
                    $record = new \mySpires\Record($record);
                    if ($record->$field && in_array($record->$field, $chunk)) {
                        $found[] = $record->$field;
                        if($record->temp == 1) $temp[] = $record->inspire;
                        $record->sync();
                    }
                }
            }

            $lost = array_merge($lost, array_diff($chunk, $found));
        }
        // self::delete_bibtex(array_diff($found,$temp), $field);

        return (object)Array("found" => $found, "lost" => $lost, "temp" => $temp);
    }

    /**
     * Links naked arXiv IDs and DOIs to INSPIRE or NASA-ADS.
     * The idea is that every record must have either an INSPIRE-ID or NASA-ADS ID.
     * Record will be assigned a NASA-ADS ID only if it doesn't have an INSPIRE ID.
     */
    static private function link_to_sources() {
        // if(self::quick()) return null;

        // Generate INSPIRE for arXiv IDs which do not have INSPIRE record.
        $stats = self::regenerate("SELECT arxiv FROM records WHERE (inspire = '' OR inspire IS NULL) AND (arxiv != '' AND arxiv IS NOT NULL) AND no_inspire = 0");
        if (sizeof($stats->found) > 0)
            self::log(sizeof($stats->found) . " arXiv ids were associated with INSPIRE: " . implode(", ", $stats->found) . ".");
        if (sizeof($stats->lost) > 0) {
            $lost_list = implode(",", array_map(function($val) {
                return "'".$val."'";
            }, $stats->lost));
            // Set no_inspire for lost records older than 1 month ago
            query("UPDATE records SET no_inspire = 1 
                               WHERE (arxiv IN ($lost_list) AND (published < NOW() - INTERVAL 1 MONTH))");
            self::log(sizeof($stats->lost) . " arXiv ids not found on INSPIRE: " . implode(", ", $stats->lost) . ".");
        }

        // Generate INSPIRE for DOIs which do not have INSPIRE record.
        $stats = self::regenerate("SELECT doi FROM records WHERE (inspire = '' OR inspire IS NULL) AND (doi != '' AND doi IS NOT NULL) AND no_inspire = 0");
        if (sizeof($stats->found) > 0)
            self::log(sizeof($stats->found) . " DOIs were associated with INSPIRE: " . implode(", ", $stats->found) . ".");
        if (sizeof($stats->lost) > 0) {
            $lost_list = implode(",", array_map(function($val) {
                return "'".$val."'";
            }, $stats->lost));
            // Set no_inspire for lost records older than 1 month ago
            query("UPDATE records SET no_inspire = 1 
                               WHERE (doi IN ($lost_list) AND (published < NOW() - INTERVAL 1 MONTH))");
            self::log(sizeof($stats->lost) . " DOIs set to \"no_inspire\": " . implode(", ", $stats->lost) . ".");
        }

        // Generate ADS for arXiv IDs which do not have ADS record.
        $stats = self::regenerate("SELECT arxiv FROM records WHERE (ads = '' OR ads IS NULL) AND (arxiv != '' AND arxiv IS NOT NULL)", "ads");
        if (sizeof($stats->found) > 0)
            self::log(sizeof($stats->found) . " arXiv ids were associated with ADS: " . implode(", ", $stats->found) . ".");

        // Generate ADS for DOIs which do not have INSPIRE or ADS record.
        $stats = self::regenerate("SELECT doi FROM records WHERE (ads = '' OR ads IS NULL) AND (doi != '' AND doi IS NOT NULL)", "ads");
        if (sizeof($stats->found) > 0)
            self::log(sizeof($stats->found) . " DOIs were associated with ADS: " . implode(", ", $stats->found) . ".");
    }

    /**
     * Refreshes temporary entries from INSPIRE.
     * Should be followed by fetch_missing_bibtex().
     */
    static private function refresh_temp_records() {
        if(self::quick()) return null;

        // Refresh temporary records with an INSPIRE ID
        $stats = self::regenerate("SELECT inspire FROM records where (inspire != '' AND inspire IS NOT NULL) AND temp = 1");
        $perm = array_diff($stats->found, $stats->temp);
        if (sizeof($perm) > 0)
            self::log(sizeof($perm) . " temporary INSPIRE records were updated: " . implode(", ", $perm) . ".");

        // Refresh temporary records with an NASA-ADS ID but no INSPIRE ID
        $stats = self::regenerate("SELECT ads FROM records where (ads != '' AND ads IS NOT NULL) AND (inspire = '' OR inspire IS NULL) AND temp = 1", "ads");
        $perm = array_diff($stats->found, $stats->temp);
        if (sizeof($perm) > 0)
            self::log(sizeof($perm) . " temporary NASA-ADS records were updated: " . implode(", ", $perm) . ".");
    }

    /*
    static private function delete_bibtex($query, $field) {
        if(is_string($query)) $query = explode(",", $query);
        if(sizeof($query) == 0) return;
        $field_list = implode(",", array_map(function ($q) {
            return "'". trim($q) . "'";
        }, $query));
        mySpires::db_query("UPDATE records SET bibtex = '' WHERE $field IN ({$field_list})");
    }
    */

    /*
    static private function fetch_missing_bibtex()
    {
        $results = self::database_select_chunks("SELECT inspire FROM records WHERE (inspire != '' AND inspire IS NOT NULL) AND (bibtex = '' OR bibtex IS NULL)")->chunks;

        $found = [];
        $total_count = 0;
        foreach ($results as $inspireArray) {
            $query = implode(" OR ", $inspireArray);

            $url = "https://inspirehep.net/search?p=find+recid+" . urlencode($query) . "&of=hx&rg=150";
            $results = file_get_contents($url);

            $e1 = explode("</pre>", $results);
            foreach ($e1 as $e2) {
                $e2 = explode("<pre>", $e2);
                if (isset($e2[1])) {
                    $bibtex = $e2[1];
                    $e3 = explode("@", $bibtex, 2);
                    $e3 = explode("{", $e3[1], 2);
                    $e3 = explode(",", $e3[1], 2);
                    $bibkey = $e3[0];

                    $bibtex = htmlspecialchars_decode($bibtex);

                    if ($bibkey) {
                        $query = sprintf("UPDATE records SET bibtex = '%s' WHERE bibkey = '{$bibkey}'",
                            mySpires::db()->real_escape_string($bibtex));
                        mySpires::db_query($query);

                        if(mySpires::db()->affected_rows)
                            $found[] = $bibkey;
                    }
                }
            }

            $total_count += sizeof($inspireArray);
        }

        $lost_count = $total_count - sizeof($found);

        if(sizeof($found) > 0)
            self::log("Synced BibTeX for " . sizeof($found) . " records from INSPIRE: " . implode(", ", $found) . ".");
        if($lost_count > 0)
            self::log($lost_count . " records did not have a BibTeX record on INSPIRE.");

        $results = self::database_select_chunks("SELECT arxiv FROM records WHERE (inspire = '' OR inspire IS NULL) AND (arxiv != '' AND arxiv IS NOT NULL) AND (bibtex = '' OR bibtex IS NULL)")->chunks;

        $found_nasa = [];
        $total_count_nasa = 0;
        foreach ($results as $arxivArray) {
            $query = implode(" OR ", $arxivArray);

            $response = mySpires::nasa_ads("search/query",
                ["q" => "arXiv:" . $query, "fl" => "bibcode,identifier", "rows" => 150]);

            $results = $response->response->docs;
            $nasa_ids = [];
            foreach($results as $result) {
                $arxiv = null;
                foreach($result->identifier as $i) {
                    $e = explode("arXiv:", $i);
                    if(sizeof($e) == 2 && $e[1]) {
                        $arxiv = $e[1];
                        break;
                    }
                    elseif($i && in_array($i, $arxivArray)) {
                        $arxiv = $i;
                        break;
                    }
                }
                if($arxiv)
                    $nasa_ids[$result->bibcode] = $arxiv;
            }

            $response = mySpires::nasa_ads("export/bibtex", ["bibcode" => array_keys($nasa_ids)]);
            $results = explode("\n\n",  $response->export);

            foreach($results as $bibtex) {
                $e = explode("{", $bibtex);
                $e = explode(",", $e[1]);

                if($bibtex && array_key_exists($e[0], $nasa_ids)) {
                    $arxiv = $nasa_ids[$e[0]];
                    $query = sprintf("UPDATE records SET bibtex = '%s' WHERE arxiv = '{$arxiv}'",
                        mySpires::db()->real_escape_string($bibtex));
                    mySpires::db_query($query);
                    array_push($found_nasa, $arxiv);
                }
            }

            $total_count_nasa += sizeof($arxivArray);
        }

        $lost_count_nasa = $total_count_nasa - sizeof($found_nasa);

        if(sizeof($found_nasa) > 0)
            self::log("Synced BibTeX for " . sizeof($found_nasa) . " records: " . implode(", ", $found_nasa) . " from NASA-ADS.");
        if($lost_count_nasa > 0)
            self::log($lost_count_nasa . " records did not have a BibTeX record on NASA-ADS.");


        return (object)Array("found" => array_merge($found, $found_nasa), "lost_count" => ($lost_count + $lost_count_nasa));
    }
    */

    static private $logs =[];
    static private $logs_priority = 0;
    static private function log($message, $priority = 0) {
        self::$logs[] = Date("h:i:s: ") . $message;
        if(self::$logs_priority < $priority) self::$logs_priority = $priority;
    }
    static function dump_logs() {
        echo "Logs priority: " . self::$logs_priority . "\r\n";
        foreach(self::$logs as $log) {
            echo $log . "\r\n";
        }

        if(self::$logs_priority > 0)
            self::message(implode("\r\n", self::$logs), self::$logs_priority);
    }

    /**
     * Sends a message to the API for debugging purposes.
     * @param string $message Message to be sent.
     * @param int $priority Priority of the message.
     */
    static function message($message, $priority = 0)
    {
        $db = mysqli();

        $backtrace = debug_backtrace();
        $config = include(__DIR__ . "/../../../.myspires_config.php");
        $fileSegments = explode($config->host->home, $backtrace[0]["file"]);
        if (sizeof($backtrace) > 1) $sender = $backtrace[1]["function"] . "() @ " . $fileSegments[1] . ":" . $backtrace[0]["line"];
        else $sender = $fileSegments[1] . ":" . $backtrace[0]["line"];

        $username = username();
        if(!$username) $username = "anonymous";

        $sql = sprintf("INSERT INTO messages (username, sender, message, priority) VALUES ('%s', '%s', '%s', '%s')",
            $db->real_escape_string($username),
            $db->real_escape_string($sender),
            $db->real_escape_string($message),
            $db->real_escape_string($priority));

        $db->query($sql);

        return;
    }

    /**
     * Creates database backups.
     * Keeps daily backups for the past 7 days and weekly backups within the last 6 months.
     * Backup is only performed once a day, based on if the backup file is available.
     */
    static private function database_backup() {
        // set the backup directory and filename
        $config = include(__DIR__ . "/../../../.myspires_config.php");
        $server = $config->server;

        $dir = $server->content_root . "/database_backups/";
        $file = $dir . "ajainphysics_" . date('Y-m-d') . ".sql";

        // If backup was already done, return
        if(file_exists($file) && self::quick()) return;

        // perform the backup
        // Set .my.cnf in the home folder
        $opts = include(__DIR__ . "/../../../.mysqldb.php");
        $query = "HOME=/home/kz0qr7otxolj/ mysqldump --single-transaction {$opts->dbname} > {$file}";
        exec($query,$output, $error);

        if($error) {
            self::message($error, 5);
            self::log("Database backup was interrupted!", 5);
            return;
        }

        $backups = scandir($dir);
        foreach($backups as $backup) {
            $e = explode("ajainphysics_", $backup);
            if(key_exists("1", $e) && $e[1]) {
                $e = explode(".sql", $e[1]);
                $backup_date = strtotime($e[0]);
                $days_past = floor((time() - $backup_date)/(60*60*24));

                if($days_past > 180 || ($days_past > 7 && date("w", $backup_date) != 0))
                    unlink($dir.$backup);
            }
        }

        self::log("I backed up your database.");
    }

    /* ========== Thumbnails ========== */

    /**
     * Syncs thumbnails for saved records.
     */
    static private function sync_thumbnails()
    {
        $config = include(__DIR__ . "/../../../.myspires_config.php");
        $server = $config->server;

        $thumbnails = scandir($server->content_root . "/thumbnails/");
        $db = mysqli();

        $results = $db->query("SELECT id, inspire, arxiv, no_thumbnail FROM records");


        $found = [];
        $lost = [];
        while ($record = $results->fetch_object()) {
            if (!in_array($record->id . ".jpg", $thumbnails) && !$record->no_thumbnail) {
                if($record->arxiv) $url = "https://arxiv.org/pdf/$record->arxiv.pdf";
                else continue;

                $tmp = __DIR__ . "/../../.cache/thumb.pdf";
                $filename = $server->content_root . "/thumbnails/$record->id.jpg";

                upload_file($url, $tmp);

                // break;

                try {
                    $im = new imagick();
                    $im->setResolution(150,150);
                    $im->readImage($tmp."[0]");
                    $im = $im->flattenImages();
                    $im->setImageFormat('jpg');
                    $im->scaleImage(600,1200,true);
                    $im->writeImage($filename);
                    $found[] = $record->arxiv;
                } catch(Exception $e) {
                    self::log("Imagick responded for record {$record->id}:" . $e->getMessage(), 1);
                    $lost[] = $record->arxiv;
                }
            }
        }

        if (sizeof($found) > 0)
            self::log("Thumbnails for " . sizeof($found) . " records were generated: " . implode(", ", $found) . ".");
        if (sizeof($lost) > 0)
            self::log("Thumbnails for " . sizeof($lost) . " records were not found: " . implode(", ", $lost) . ".");
    }

    static function sync_bibtex() {

        // Users
        $users = user_list();
        $synced = [];
        foreach($users as $username) {
            $res = bib($username);
            if($res && $res->dropbox) {
                array_push($synced, $username);
            }
        }

        if(sizeof($synced) > 0)
            self::log("BibTeX uploaded to Dropbox for " . sizeof($synced) . " users: " . implode(", ", $synced) . ".");

        // Collaborations

        $results = query("SELECT cid, name FROM collaborations");
        $synced = [];
        while($result = $results->fetch_object()) {
            $res = bib($result->cid, "collaboration");
            if($res && $res->dropbox) {
                array_push($synced, $result->name);
            }
        }

        if(sizeof($synced) > 0)
            self::log("BibTeX synced for " . sizeof($synced) . " collaborations: " . implode(", ", $synced) . ".");
    }

    /**
     * The wake up call for mySa.
     */
    static function wake() {
        self::$call_time = microtime(true);
        self::stat_step_daily("mySa_calls");

        // Check if mySa is busy
        if(self::stats("mySa_busy")) {
            self::log("mySa is busy.");
            self::dump_logs();
            return;
        }

        // Reserve mySa
        // self::stats("mySa_busy", 1);

        // Welcome
        self::greeting();

        // Backup database. Done once a day based on if the backup file is available.
        self::database_backup();

        if(self::check_duplicates()) {
            // Link naked arXiv IDs.
            self::link_to_sources();

            // Refresh temporary records. Skipped in quick mode.
            self::refresh_temp_records();

            // Fetch missing bibtex entries.
            // self::fetch_missing_bibtex();

            // Generate bibtex files and upload them to Dropbox
            self::sync_bibtex();
        }

        // Sync thumbnails
        self::sync_thumbnails();

        self::wrapup();

        // Release mySa
        self::stats("mySa_busy", 0);
    }

    /**
     * Regenerates the entire database.
     * Should not be used in an automated script.
     */
    static function regenerate_database()
    {
        self::$quick = false; // Disable quick mode.

        self::greeting();
        self::database_backup(); // Backup database, just in case.

        if(self::check_duplicates()) {
            self::link_to_sources(); // Link naked arXiv IDs.

            self::log("Initiating database regeneration from INSPIRE.");
            $stats = self::regenerate(
                "SELECT inspire FROM records 
                       WHERE (inspire != '' AND inspire IS NOT NULL AND updated<DATE_SUB(NOW(), INTERVAL 60 MINUTE))");
            if (sizeof($stats->found) > 0)
                self::log(sizeof($stats->found) . " records were regenerated from INSPIRE.");
            if (sizeof($stats->lost) > 0)
                self::log(sizeof($stats->lost) . " records were not found on INSPIRE: " . implode(", ", $stats->lost) . ".");

            $stats = self::regenerate(
                "SELECT ads FROM records where (ads != '' AND ads IS NOT NULL) AND (inspire = '' OR inspire IS NULL) AND updated<DATE_SUB(NOW(), INTERVAL 60 MINUTE)", "ads");
            if (sizeof($stats->found) > 0)
                self::log(sizeof($stats->found) . " records were regenerated from NASA-ADS.");
            if (sizeof($stats->lost) > 0)
                self::log(sizeof($stats->lost) . " records were not found on NASA-ADS: " . implode(", ", $stats->lost) . ".");

            // self::fetch_missing_bibtex();
        }

        self::wrapup();
    }

    static private function greeting() {
        self::log("Hello there! I am mySa (mySpires assistant).");
    }

    static private function wrapup() {
        if(!self::quick()) self::stats("last_db_checkup_full", microtime(true));
        self::stats("last_db_checkup", microtime(true));

        self::log("All done! See you later.");

        self::dump_logs();
    }

    static function test() {
        self::greeting();

        self::regenerate_database();

        self::wrapup();
    }

}