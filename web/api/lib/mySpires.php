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

    static $server_root = "/home/kz0qr7otxolj";
    static $document_root = "/home/kz0qr7otxolj/myspires";
    static $content_root = "/home/kz0qr7otxolj/cdn/myspires_content";

    static $content_url = "https://cdn.ajainphysics.com/myspires_content/";

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

        if(!$username) $username = mySpires::username();
        else $username = (new mySpires_User($username, "username"))->username;

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

        $username = mySpires::username();
        if(!$username) return $records;

        if(!property_exists($filters,"bin")) $filters->bin = false;
        if(!property_exists($filters,"offset")) $filters->offset = 0;

        $limit = "";
        if(property_exists($filters,"count")) $limit = " LIMIT {$filters->offset},{$filters->count}";

        $results = self::db_query("SELECT id FROM entries WHERE username = '{$username}' AND bin=" . (int)$filters->bin . " ORDER BY updated DESC" . $limit);

        $records = [];
        while ($entry = $results->fetch_object())
            $records[] = $entry->id;

        return self::find_records(["id" => implode(",", $records)]);
    }

    private static function queryentries($filters = "", $username = "") {
        if(!$username) $username = mySpires::username();
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
        if(!$username) $username = mySpires::username();
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
        if(!$username) $username = mySpires::username();
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

    static function bib($query, $type = "user") {
        if($type == "collaboration") {
            $collaboration = new mySpires_Collaboration($query);
            $user_list = $collaboration->collaborators;

            $label = "mySpiresCollaboration/" . $collaboration->name;

            $bib_header = "%%% " . $label . "\n";
            $bib_header .= "%%% Generated by mySpires for collaboration " . $collaboration->name . "\n";
        }
        elseif ($type = "user") {
            $user_list = [$query];
            $e = explode(":", $query);
            $user = new mySpires_User($e[0]);

            if(!$user) return null;

            if(sizeof($e) == 1 || !$e[1])
                $label = "mySpires/" . $user->username;
            else
                $label = "mySpires/" . $query;

            $bib_header = "%%% " . $label . "\n";
            $bib_header .= "%%% Generated by mySpires for " . $user->name . " (username: " . $user->username . ")\n";
        }
        else
            return null;

        $bib_array = [];

        foreach($user_list as $user) {
            $e = explode(":", trim($user));
            $username = $e[0];

            if(array_key_exists("1", $e) && $tag = $e[1]) {
                $results = self::queryentries("tags LIKE '%%" . $tag . "%%'", $username);
                $results = array_filter($results, function($r) use($tag) {
                    $tagArray = explode(",", $r->entry->tags);
                    foreach ($tagArray as $db_tag) {
                        $db_tag = trim($db_tag);
                        if (strpos($db_tag, $tag . "/") === 0 || $db_tag == $tag) return true;
                    }
                    return false;
                });
            } else {
                $results = self::queryentries("", $username);
            }

            foreach($results as $result) {
                $bib_array[$result->record->id] = trim($result->record->bibtex);
            }
        }

        if(sizeof($bib_array) == 0) return null;

        $bib = implode("\n\n", $bib_array);

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

        $bib = strtr($bib, $normalizeChars);

        $bib_header .= "%%% Last updated: " . date('Y-m-d H:i:s') . "\n\n";

        $full_bib = $bib_header . $bib;

        $label_ = str_replace(":", "_", $label);
        $label_ = str_replace("/", "_", $label_);
        $label_ = str_replace(" ", "-", $label_);
        $filename = $label_ . ".bib";

        $saved_file_path = mySpires::$content_root . "/bibtex/" . $filename;
        if(file_exists($saved_file_path))
            $saved_bib = file_get_contents($saved_file_path);
        else
            $saved_bib = "";

        $dropbox_upload = false;
        if($bib != $saved_bib) {
            foreach($user_list as $user) {
                $e = explode(":", trim($user));
                $user = new mySpires_User($e[0]);

                if($user->dropbox()->upload($full_bib, "/bib/" . $filename)) {
                    file_put_contents($saved_file_path, $bib);
                    $dropbox_upload = true;
                }
            }
        }

        return (object)["filename" => $filename, "contents" => $full_bib, "dropbox" => $dropbox_upload];
    }

    static function bibtex($username = null, $tag = null) {
        if($username) $user = new mySpires_User($username);
        else $user = $user = mySpires::user();

        if(!$user) return null;
        $username = $user->username;

        $tag = trim($tag);

        return self::bib($username . ":" . $tag, "user");
    }

    static function purge_history() {
        $user = mySpires::user();
        if(!$user) return false;

        mySpires::db_query("DELETE FROM history WHERE username = '{$user->username}'");

        return true;
    }

    // User functions

    private static $user;

    /**
     * Checks if a user is logged in and returns the username.
     * @return mySpires_User User object if user is logged in, null otherwise
     */
    static function user() {
        if(self::$user) return self::$user;

        if (
            !isset($_SESSION['user_id'], $_SESSION['login_string'])
            && isset($_COOKIE['user_id'], $_COOKIE['login_string'])
        ) {
            $_SESSION["user_id"] = $_COOKIE["user_id"];
            $_SESSION["login_string"] = $_COOKIE["login_string"];
        }

        // Check if all session variables are set
        if (isset($_SESSION['user_id'], $_SESSION['login_string'])) {
            $user = new mySpires_User($_SESSION['user_id'], "uid");

            if ($user->info && hash_equals(hash('sha512', $user->info->hash), $_SESSION['login_string'])) {
                $_SESSION['username'] = $user->username;
                self::$user = $user; // logged in
            }
        }

        return self::$user;
    }

    static function username() {
        if(self::user())
            return self::user()->username;
        else
            return null;
    }

    static function verify_username(&$username, $default = false) {
        if($username) {
            $username = (new mySpires_User($username))->username;
            if($username) return true;
        }
        elseif($default) {
            $username = self::username();
            if($username) return true;
        }
        return false;
    }

    static function admin() {
        $admins = json_decode(file_get_contents(__DIR__ . "/admins.json"));
        return boolval(self::username() && in_array(self::username(), $admins)); // if admin, return true.
    }

    static function login($username, $password, $remember = false, $dev_login = false) {
        $user = new mySpires_User($username);
        if(!$dev_login && !$user->check_password($password)) return false;

        if(!$user->info->enabled) return false;

        $login_string = hash('sha512', $user->info->hash);

        $_SESSION['user_id'] = $user->uid;
        $_SESSION['username'] = $username;
        $_SESSION['login_string'] = $login_string;

        if ($remember) {
            $expire_time = time() + 60 * 60 * 24 * 365;
            /* Set cookie to last 1 year */
            setcookie('user_id', $user->uid, $expire_time, self::$serverfolder, self::$serverdomain);
            setcookie('login_string', $login_string, $expire_time, self::$serverfolder, self::$serverdomain);

        } else {
            $expire_time = time() - 7000000;
            /* Cookie expires when browser closes */
            setcookie('user_id', "", $expire_time, self::$serverfolder, self::$serverdomain);
            setcookie('login_string', "", $expire_time, self::$serverfolder, self::$serverdomain);
        }

        return true;
    }

    static function logout()
    {
        session_start();
        session_unset();

        $expire_time = time() - 7000000;

        setcookie(session_name(), '', $expire_time);
        setcookie('user_id', '', $expire_time);
        setcookie('login_string', '', $expire_time);

        setcookie(session_name(), '', $expire_time, self::$serverfolder);
        setcookie('user_id', '', $expire_time, self::$serverfolder);
        setcookie('login_string', '', $expire_time, self::$serverfolder);

        setcookie(session_name(), '', $expire_time, self::$serverfolder, self::$serverdomain);
        setcookie('user_id', '', $expire_time, self::$serverfolder, self::$serverdomain);
        setcookie('login_string', '', $expire_time, self::$serverfolder, self::$serverdomain);

        session_destroy();

        return true;
    }

    static function register($data) {
        // transformations
        $data->fname = trim($data->fname);
        $data->lname = trim($data->lname);
        $data->email = strtolower(trim($data->email));
        $data->username = strtolower(preg_replace("/[^a-zA-Z0-9_\-]+/", "", trim($data->username)));

        // specification checks
        if(strlen($data->fname) == 0) return false;
        if(!filter_var($data->email, FILTER_VALIDATE_EMAIL)) return false;
        if(strlen($data->username) < 4) return false;
        if(strlen($data->password) < 6) return false;

        // already taken checks
        if((new mySpires_User($data->email, "email"))->info) return false;
        if((new mySpires_User($data->email, "username"))->info) return false;

        if ($query = mySpires::db()->prepare("INSERT INTO users (username, email, first_name, last_name, enabled) VALUES (?, ?, ?, ?, 1)")) {
            $query->bind_param('ssss', $data->username, $data->email, $data->fname, $data->lname);
            if($query->execute()) {
                $user = new mySpires_User($data->username);
                $user->set_password($data->password);
                mySa::message("New user registered: " . $data->username,1);
                return true;
            } else {
                echo $query->error;
            }
        }

        return false;
    }

    static function forgot_password($username) {
        $user = new mySpires_User($username);
        if(!$user->info) return false;

        $config = include(__DIR__ . "/../../../.myspires_config.php");
        $config = $config->server;

        $subject = "[mySpires Support] Password Reset Request";

        $msg = "Dear " . $user->display_name . ",\r\n\r\n";
        $msg .= "A password reset request was initiated for your mySpires account. If you initiated this request, please follow this link to reset your password:\r\n\r\n";
        $msg .= $config->path . "register.php?reset=1&username=". $user->username . "&code=" . $user->info->password . "\r\n\r\n";
        $msg .= "If you did not initiate this request, you can ignore this email.\r\n\r\n";
        $msg .= "Cheers,\r\n";
        $msg .= "mySpires";

        $headers = "From: mySpires <admin@ajainphysics.com>\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        mySa::message("Password reset request received for " . $user->username . ".", 1);

        return mail($user->email, $subject, $msg, $headers);
    }

    /**
     * Resets saved Dropbox token.
     * @param string $username Username
     */
    static function dropbox_reset($username) {
        $user = new mySpires_User($username);
        $user->update_info(["dbxtoken" => NULL]);
    }

    static function user_list() {
        if($query = mySpires::db()->prepare("SELECT username FROM users")) {
            $query->execute();
            $results = $query->get_result();

            $users = [];
            while($result = $results->fetch_object()) {
                array_push($users, $result->username);
            }

            return $users;
        }

        return null;
    }

    // ============================== Tools ============================== //

    static function surname($name) {
        $e = explode(" ", trim($name));
        return $e[sizeof($e) - 1];
    }

    static function tag_cleanup($tag) {
        return preg_replace("/[,]+/", "", self::tag_list_cleanup($tag));
    }

    static function tag_list_cleanup($tag_list) {
        $tag_list = preg_replace("/[^a-zA-Z0-9 ,\-\/]+/", "", $tag_list);

        $tags = explode(",", $tag_list);
        $tags = array_map(function($t) {
            $e = explode("/", $t);
            $e = array_map(function($tt) {
                return implode(" ", array_filter(explode(" ", $tt)));
            }, $e);
            return implode("/", array_filter($e));
        }, $tags);

        $tags = array_unique(array_filter($tags));
        sort($tags);

        return implode(",", $tags);
    }
}

include_once(__DIR__ . "/Dropbox.php");

include_once(__DIR__ . "/mySpires_Record.php");
include_once(__DIR__ . "/mySpires_Records.php");
include_once(__DIR__ . "/mySpires_Tag.php");
include_once(__DIR__ . "/mySpires_User.php");
include_once(__DIR__ . "/mySpires_Collaboration.php");