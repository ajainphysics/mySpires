<?php
namespace mySpires;

use \library\dropbox\Dropbox;
use function mySpires\tags\cleanup_list;
use function mySpires\users\admin;
use function mySpires\users\surname;
use function mySpires\users\username;

class User {
    private $loaded =  false;

    public $uid;
    public $username;
    public $name;
    public $display_name;
    public $email;

    public $info;
    private $sensitive_fields = ["password", "hash", "dbxtoken"];
    public $safe_info;

    function __construct($q = null, $field = null) {
        if(!$q) return;

        if($field) $this->load($q, $field);
        else {
            if(!$this->load($q, "username"))
                $this->load($q, "email");
        }
    }

    private function load($q, $field) {
        if($query = mysqli()->prepare("SELECT * FROM users WHERE {$field} = ?")) {
            $query->bind_param("s", $q);
            $query->execute();

            if($this->info = $query->get_result()->fetch_object()) {
                $this->username = $this->info->username;
                $this->email = $this->info->email;
                $this->uid = $this->info->uid;
                $this->name = trim($this->info->first_name . " " .  $this->info->last_name);
                if($this->info->first_name) {
                    $e = explode(" ", $this->info->first_name);
                    $this->display_name = $e[0];
                } else {
                    $this->display_name = $this->username;
                }

                $this->safe_info = clone $this->info;
                foreach($this->sensitive_fields as $key) {
                    unset($this->safe_info->$key);
                }

                $this->loaded = true;

                return true;
            }
        }
        return false;
    }

    function update_info(array $args) {
        if(!$this->loaded) return false;
        if(array_key_exists("username", $args)) return false;

        $db = mysqli();

        $types = "";
        $val_query = "";
        $values = [];
        foreach($args as $key => $value) {
            $val_query .= $key . " = ?, ";
            $types .= "s";
            if(is_string($value)) $value = $db->real_escape_string($value);
            array_push($values, $value);
        }
        $val_query .= "last_seen=now()";

        if($query = $db->prepare("UPDATE users SET " . $val_query . " WHERE username = ?")) {
            array_push($values, $this->username);
            $query->bind_param($types . "s", ...$values);
            if($query->execute()) {
                $this->load($this->username, "username");
                return true;
            }
        }

        return false;
    }

    function check_password($password) {
        if(!$this->loaded) return false;
        return boolval(crypt($password, $this->info->password) == $this->info->password);
    }

    function check_code($code) {
        if(!$this->loaded) return false;
        return boolval($this->info->password == $code);
    }

    function set_password($password) {
        if(!$this->loaded) return false;

        $password = crypt($password, "$2y$12$" . bin2hex(openssl_random_pseudo_bytes(22)));
        $hash = bin2hex(openssl_random_pseudo_bytes(32));

        return $this->update_info(["password" => $password, "hash" => $hash]);
    }

    function change_password($password, $new_password) {
        if(!$this->loaded) return false;
        if(!$this->check_password($password)) return false;
        return $this->set_password($new_password);
    }

    function reset_password($code, $password) {
        if(!$this->loaded) return false;
        if($this->info->password != $code) return false;
        return $this->set_password($password);
    }

    function dropbox($code = "") {
        $opts = config("dropbox");

        if(!$this->loaded) return false;
        if($code) {
            $dbx = new Dropbox($opts);
            $this->update_info(["dbxtoken" => $dbx->reauth($code)]);
        } else {
            $dbx = new Dropbox($opts, $this->info->dbxtoken);
        }

        $dbx->reset = '\mySpires\users\dropbox_reset';
        $dbx->reset_argument = $this->username;

        return $dbx;
    }

    function purge_history() {
        if(!$this->loaded) return;

        query("DELETE FROM history WHERE username = '{$this->username}'");
    }

    /**
     * Checks if the logged in user has permissions to this user.
     */
    function auth() {
        if(!$this->loaded) return false;
        return boolval(admin() || $this->username == username());
    }

    function tags() {
        if(!$this->loaded) return false;
        $username = $this->username;

        $tags = [];
        if($query = mysqli()->prepare("SELECT tags, id FROM entries WHERE username = ? AND bin=0 ORDER BY id")) {
            $query->bind_param("s", $username);
            $query->execute();
            $entries = $query->get_result();

            $tag_records = [];
            while($entry = $entries->fetch_object()) {
                $tags = explode(",", cleanup_list($entry->tags));

                foreach($tags as $tag) {
                    if(!array_key_exists($tag, $tag_records)) $tag_records[$tag] = [];
                    array_push($tag_records[$tag], $entry->id);
                }
            }

            $tags = $tag_records;
            foreach($tag_records as $tag => $ids) {
                $e = explode("/", $tag);
                $parent = $e[0];
                while($t = next($e)) {
                    if(!key_exists($parent, $tags)) $tags[$parent] = [];
                    $parent .= "/" . $t;
                }
            }

            ksort($tags);
        }

        $id_tag_array = [];
        foreach($tags as $tag => $ids) {
            foreach($ids as $id) {
                if(!key_exists($id, $id_tag_array)) $id_tag_array[$id] = [];
                array_push($id_tag_array[$id], $tag);
            }
        }
        $id_array = array_keys($id_tag_array);

        array_walk($tags, function(&$val) {
            $val = (object)[
                "records" => $val,
                "description" => null,
                "starred" => 0,
                "shared" => 0,
                "visited" => null,
                "authors" => [],
                "surnames" => []
            ];
        });

        if($q = mysqli()->prepare("SELECT * FROM tags WHERE username = ?")) {
            $q->bind_param("s", $username);
            $q->execute();
            $results = $q->get_result();

            while($result = $results->fetch_object()) {
                if(array_key_exists($result->tag, $tags)) {
                    $tags[$result->tag]->description = $result->description;
                    $tags[$result->tag]->starred = $result->starred;
                    $tags[$result->tag]->shared = $result->shared;
                    $tags[$result->tag]->visited = $result->visited;
                }
            }
        }

        $questions = implode(",", array_map(function(){return "?";}, $id_array));
        $s = implode("", array_map(function(){return "s";}, $id_array));

        if($q = mysqli()->prepare("SELECT id, author FROM records WHERE id IN ({$questions})")) {
            $q->bind_param($s, ...$id_array);
            $q->execute();
            $results = $q->get_result();

            while($result = $results->fetch_object()) {
                $authors = array_map(function($a){
                    return trim(utf8_decode($a));
                }, explode(",", $result->author));

                foreach($id_tag_array[$result->id] as $tag) {
                    $tags[$tag]->authors = array_merge($tags[$tag]->authors, $authors);
                }
            }

            foreach($tags as $tag => $props) {
                $tags[$tag]->authors = array_values(array_unique($tags[$tag]->authors));

                usort($tags[$tag]->authors, function($a, $b) {
                    return strcmp(surname($a), surname($b));
                });

                $tags[$tag]->surnames = array_map(function($a) {
                    return surname($a);
                }, $tags[$tag]->authors);

                $tags[$tag]->surnames = array_values(array_unique($tags[$tag]->surnames));
            }

        }

        return $tags;
    }
}
