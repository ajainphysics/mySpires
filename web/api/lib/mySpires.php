<?php
/**
 * This is the main mySpires PHP library to communicate with the database.
 * It does not contain any HTML formatting information for mySpires website or plugin.
 * It basically provides a framework for other platform specific code to interact with the mySpires atmosphere.
 *
 * @author Akash Jain
 */

ini_set("include_path",
    '/home/kz0qr7otxolj/php:/home/kz0qr7otxolj/resources:/home/kz0qr7otxolj/cdn:'
    . ini_get("include_path") );

require_once "simplepie-1.5/autoloader.php"; // SimplePie package for arXiv queries.
require_once "refspires/refSpires.php";

$config = include(__DIR__ . "/../../../.myspires_config.php");

if (!function_exists('hash_equals')) {
    /**
     * Compares two hash strings.
     * @param string $str1 First hash string.
     * @param string $str2 Second hash string.
     * @return bool Returns true if the two strings match, false otherwise.
     */
    function hash_equals($str1, $str2)
    {
        if (strlen($str1) != strlen($str2)) {
            return false;
        } else {
            $res = $str1 ^ $str2;
            $ret = 0;
            for ($i = strlen($res) - 1; $i >= 0; $i--) $ret |= ord($res[$i]);
            return !$ret;
        }
    }
}

/**
 * This class assembles essential functioning of mySpires.
 */
class mySpires
{
    /** @var string $server Server address on which mySpires is hosted. */
    static $server = "https://myspires.ajainphysics.com/";

    /** @var string $serverdomain The domain name of the server where mySpires is hosted. */
    static $serverdomain = "myspires.ajainphysics.com";

    /** @var string $serverfolder The folder on the server where mySpires is hosted. */
    static $serverfolder = "/";

    /** @var mysqli $db Contains the active instance of the database. */
    static $db;

    /**
     * Access mySpires MySQL database.
     * @return mysqli Returns an instance of the mySpires MySQL database object.
     */
    static function db()
    {
        if(self::$db) return self::$db; // If already logged into the database, return the saved instance.

        $opts = include(__DIR__ . "/../../../.myspires_config.php");
        $opts = $opts->mysql;
        $db = new mysqli($opts->host, $opts->username, $opts->passwd, $opts->dbname);

        if ($db->connect_errno) {
            printf("Connection to the database failed: %s\n", $db->connect_error);
            exit();
        }
        else {
            self::$db = $db;
            return $db;
        }
    }

    /**
     * Runs a query on the database.
     * @param $query - mysql query
     * @return bool|mysqli_result
     */
    static function db_query($query) {
        $db = self::db();
        $result = $db->query($query);

        if ($db->errno) {
            echo "Error during database query: $query. Dumping current status:";
            echo "<pre>";
            var_dump($db);
            echo "</pre>";
            exit();
        }
        else return $result;
    }

    /**
     * Finds records in the database.
     * @param array $params
     * @param string $username
     * @return array
     */
    static function find_records(array $params = [], $username = null)
    {
        $params = (object)$params;

        $resultsArray = [];
        foreach(["inspire","arxiv","id"] as $field) {
            if(property_exists($params, $field) && $params->$field) {
                $field_list = implode(",", array_map(function ($id) {
                    return "'". trim($id) . "'";
                }, explode(",", $params->$field)));
                $resultsArray[] = self::db_query("SELECT * FROM records WHERE $field IN ({$field_list})");
            }
        }
        if(sizeof((array) $params) == 0) {
            $resultsArray[] = self::db_query("SELECT * FROM records");
        }

        $records = [];
        $idArray = [];
        foreach($resultsArray as $results) {
            while($result = $results->fetch_object()) {
                $record = (object)[];
                $properties = Array("id","inspire","bibkey","title","author","arxiv","arxiv_v","published","doi","temp");
                foreach($properties as $property) {
                    $record->$property = $result->$property;
                }
                $record->status = "unsaved";
                $records[$record->id] = $record;
                $idArray[] = $record->id;
            }
        }

        if(!$username) $username = mySpiresUser::current_username();
        if(!mySpiresUser::username_exists($username)) $username = null;
        // $username = mySpiresUser::current_username();
        if ($username && sizeof($records) > 0) {
            $id_list = implode(",", array_map(function ($id) {
                return "'". trim($id) . "'";
            }, $idArray));
            $results = self::db_query("SELECT * FROM entries WHERE id IN ({$id_list}) AND username = '{$username}'");
            while($result = $results->fetch_object()) {
                $records[$result->id]->tags = $result->tags;
                $records[$result->id]->comments = $result->comments;
                $records[$result->id]->updated = $result->updated;

                if($result->bin) $records[$result->id]->status = "binned";
                else $records[$result->id]->status = "saved";
            }
        }

        return $records;
    }

    static function find_entries(array $filters = []) {
        $filters = (object)$filters;

        $records = [];

        $username = mySpiresUser::current_username();
        if(!$username) return $records;

        if(!property_exists($filters,"bin")) $filters->bin = false;
        if(!property_exists($filters,"offset")) $filters->offset = 0;

        $limit = "";
        if(property_exists($filters,"count")) $limit = " LIMIT {$filters->offset},{$filters->count}";

        $results = self::db_query("SELECT id FROM entries WHERE username = '{$username}' AND bin=" . (int)$filters->bin . $limit);

        $records = [];
        while ($entry = $results->fetch_object())
            $records[] = $entry->id;

        return self::find_records(["id" => implode(",", $records)]);
    }

    private static function queryentries($filters = "", $username = "") {
        if(!$username) $username = mySpiresUser::current_username();
        if(!$username) return null;

        $db = self::db(); // Load the database
        if($filters)
            $results = $db->query(sprintf("SELECT * FROM entries WHERE (%s) AND username = '%s'", $filters, $username));
        else
            $results = $db->query(sprintf("SELECT * FROM entries WHERE username = '%s'", $username));

        // Now we need to prepare a list of IDs for the records table.
        // When searching for a tag "Fluids" mySQL will also return "Holographic Fluids". We need to eliminate these.
        $entries = Array();
        $recordIDString = "";
        while ($entry = $results->fetch_object()) {
            $entries[$entry->id] = $entry;
            $recordIDString = $recordIDString . "OR id = '" . $entry->id . "' ";
        }

        $return = Array(); // Declare the array to be returned
        if ($recordIDString != "") {
            $recordIDString = substr($recordIDString, 3);
            $results = $db->query("SELECT * FROM records where $recordIDString");

            while ($record = $results->fetch_object()) {
                $return[$record->id] = (object)Array(
                    "record" => $record,
                    "entry" => $entries[$record->id]
                );
            }
        }

        return $return;
    }


    /**
     * @param bool $tagsOnly
     * @param string $username - Username
     * @return array|bool
     */
    static function taglist($tagsOnly = false, $username = "")
    {
        if(!$username) $username = mySpiresUser::current_username();
        if(!$username) return false;

        $db = self::db();

        $resultArray = Array();
        $tagArray = Array();

        $results = $db->query(sprintf("SELECT tags, id FROM entries WHERE username = '%s' AND bin=0", $username));

        $onlyTagsArray = Array();
        $recordIDString = "";
        while ($entry = $results->fetch_object()) {
            $tagArray[$entry->id] = explode(",", $entry->tags);

            if ($tagsOnly == true) {
                foreach ($tagArray[$entry->id] as $tag) {
                    if ($tag) $onlyTagsArray[] = trim($tag);
                }
            } else {
                $recordIDString = $recordIDString . "OR id = '" . $entry->id . "' ";
            }
        }

        if ($tagsOnly == true) {
            $onlyTagsArray = array_unique($onlyTagsArray);
            sort($onlyTagsArray);
            return $onlyTagsArray;
        }

        $recordIDString = substr($recordIDString, 3);

        if (trim($recordIDString)) {

            $results = $db->query("SELECT id, author FROM records where $recordIDString");

            while ($record = $results->fetch_object()) {
                $authorArray = explode(",", $record->author);

                foreach ($tagArray[$record->id] as $tag) {
                    $tag = trim($tag);
                    if (!isset($resultArray[$tag])) $resultArray[$tag] = Array();

                    foreach ($authorArray as $author) {
                        $arr = explode(" ", trim($author));
                        $resultArray[$tag][] = $arr[sizeof($arr) - 1];
                    }
                }
            }

            foreach ($resultArray as $tag => $authors) {
                $resultArray[$tag] = array_unique($authors);
                sort($resultArray[$tag]);
            }
            ksort($resultArray);

            return $resultArray;
        } else {
            return Array();
        }
    }

    static function tagopts($username = "") {
        if(!$username) $username = mySpiresUser::current_username();
        if(!$username) return false;

        $db = self::db();

        $results = $db->query(sprintf("SELECT * FROM tags WHERE username = '%s'", $username));

        $resultArray = Array();
        while($result = $results->fetch_object()) {
            $resultArray[$result->tag] = (object)Array(
                "type" => $result->type
            );
        }

        return $resultArray;
    }

    static function shareinfo($query)
    {
        $db = self::db(); // Load the database

        $e = explode('/', $query, 2);
        $username = $e[0];
        $tag = $e[1];

        $results = $db->query(sprintf("SELECT * FROM tags WHERE username = '%s' AND tag = '%s' AND shared = 1",
            $username, $tag));

        return $results->fetch_object();
    }

    static function sharedtag($tag, $username)
    {
        $db = self::db(); // Load the database

        $shareinfo = $db->query(sprintf("SELECT * FROM tags WHERE username = '%s' AND tag = '%s' AND shared = 1",
            $username, $tag))->fetch_object();

        $return = (object)Array(
            "opts" => $shareinfo,
            "subtags" => false,
            "records" => false
        );

        if ($shareinfo) {
            if ($shareinfo->subtags) {
                $taglist = self::taglist(true, $username);
                $subtags = Array();
                foreach ($taglist as $t) {
                    if (strpos($t, $tag . "/") === 0) {
                        $e = explode("/", substr($t, strlen($tag) + 1));
                        $subtags[] = $tag . "/" . $e[0];
                    }
                }
                $return->subtags = array_unique($subtags);
            }

            $return->records = new mySpires_Records($username.":".$tag, "tag");
        }

        return $return;
    }

    static function nasa_ads($path, $payload) {
        $opts = include(__DIR__ . "/../../../.myspires_config.php");
        $opts = $opts->nasa_ads;

        $agent = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_3)";
        $url = $opts->url . $path;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_USERAGENT, $agent);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $headers = ['Authorization: Bearer ' . $opts->token];

        if($path === "export/bibtex") {
            array_push($headers, 'Content-Type: application/json');

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }
        else if($path === "search/query") {
            curl_setopt($ch, CURLOPT_URL, $url . "?" . http_build_query($payload));
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        // curl_setopt($ch, CURLOPT_VERBOSE, true);

        return json_decode(curl_exec($ch));
    }

    static function bibtex($username = null, $tag = null) {
        if($username) $user = mySpiresUser::info($username);
        else $user = $user = mySpiresUser::info();

        if(!$user) return null;
        $username = $user->username;

        $tag = trim($tag);

        if($tag) {
            $results = self::queryentries("tags LIKE '%%" . $tag . "%%'", $username);
            $results = array_filter($results, function($r) use($tag) {
                $tagArray = explode(",", $r->entry->tags);
                foreach ($tagArray as $dbtag) {
                    $dbtag = trim($dbtag);
                    if (strpos($dbtag, $tag . "/") === 0 || $dbtag == $tag) return true;
                }
                return false;
            });
        } else {
            $results = self::queryentries("", $username);
        }

        $bibtex = "";
        foreach($results as $result) {
            $bib = trim($result->record->bibtex);
            if ($bib) $bibtex .= $bib . "\n\n";
        }

        $normalizeChars = array(
            'Š'=>'S', 'š'=>'s', 'Ð'=>'Dj','Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A',
            'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E', 'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I',
            'Ï'=>'I', 'Ñ'=>'N', 'Ń'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U',
            'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss','à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a',
            'å'=>'a', 'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i',
            'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ń'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ù'=>'u',
            'ú'=>'u', 'û'=>'u', 'ü'=>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y', 'ƒ'=>'f',
            'ă'=>'a', 'ș'=>'s', 'ț'=>'t', 'Ă'=>'A', 'Ș'=>'S', 'Ț'=>'T',
        );

        $bibtex = strtr($bibtex, $normalizeChars);

        $label = "mySpires/" . $username;
        if($tag) $label .= "/" . $tag;
        $label_ = str_replace("/", "_", $label);
        $label_ = str_replace(" ", "-", $label_);
        $filename = $label_ . ".bib";

        $decorated_bibtex = "%%% " . $label . "\n";
        $decorated_bibtex .= "%%% Generated by mySpires for " . $user->name . " (username: " . $username . ")\n";
        $decorated_bibtex .= "%%% Last updated: " . date('Y-m-d H:i:s') . "\n\n";
        $decorated_bibtex .= $bibtex;

        $saved_file_path = __DIR__ . "/../../bibtex/" . $filename;
        $saved_bibtex = file_get_contents($saved_file_path);

        $dropbox_upload = false;
        if($bibtex != $saved_bibtex) {
            if(mySpiresUser::dropbox($username)->upload($decorated_bibtex, "/bib/" . $filename)) {
                file_put_contents($saved_file_path, $bibtex);
                $dropbox_upload = true;
            }
        }

        return (object)["filename" => $filename, "contents" => $decorated_bibtex, "dropbox" => $dropbox_upload];
    }
}

include_once(__DIR__ . "/Dropbox.php");
include_once(__DIR__ . "/mySpires_Record.php");
include_once(__DIR__ . "/mySpires_Records.php");
include_once(__DIR__ . "/mySpires_Tag.php");
include_once(__DIR__ . "/mySpiresUser.php");
include_once(__DIR__ . "/mySpiresTags.php");