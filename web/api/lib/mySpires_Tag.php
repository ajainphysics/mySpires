<?php

/**
 * Class mySpires_Tag
 *
 * @property [mySpires_Record] $records
 */
class mySpires_Tag {
    private $username;
    private $tag;
    private $records = [];
    private $children = [];
    private $immediate_children = [];
    private $parents = [];
    private $subtags = [];

    public function __construct($tag, $username = null) {
        if(!mySpires::verify_username($username, true)) return;
        $this->username = $username;

        $tag = mySpires::tag_cleanup($tag);
        $this->tag = $tag;
    }

    private function prepare() {
        $this->records = [];
        $this->children = [];
        $this->immediate_children = [];
        $this->parents = [];
        $this->subtags = [];

        $tags_info = (new mySpires_User($this->username))->tags();
        $tag_list = array_keys($tags_info);

        if(array_key_exists($this->tag, $tags_info)) {
            foreach ($tags_info[$this->tag]->records as $id) {
                $this->records[$id] = new mySpires_Record($id, "id", $this->username);
            }
        }

        foreach($tag_list as $t) {
            $e = explode($this->tag . "/", $t);
            if($e[0] == "") {
                $subtag_name = substr($t, strlen($this->tag) + 1);

                if($subtag_name) {
                    array_push($this->children, $t);
                    $f = explode("/", $subtag_name);
                    $child = $this->tag . "/" . $f[0];

                    if(!in_array($child, $this->immediate_children))
                        array_push($this->immediate_children, $child);

                }
            }
        }

        $e = explode("/", $this->tag);

        $parents = [$e[0]];
        while($t = next($e)) {
            array_push($parents, end($parents) . "/" . $t);
        }

        $this->parents = array_reverse($parents);

        foreach($this->immediate_children as $subtag) {
            $this->subtags[$subtag] = new mySpires_Tag($subtag, $this->username);
        }
    }

    public function __get($property) {
        if (!in_array($property, []))
            trigger_error("Property '$property' was not found in class 'mySpires_Tag' or is private.", E_USER_ERROR);

        $this->prepare();
        return $this->$property;
    }

    public function rename($new_tag) {
        $this->prepare();

        $new_tag = mySpires::tag_cleanup($new_tag);

        if(array_key_exists($new_tag, (new mySpires_User($this->username))->tags()))
            return false;

        foreach($this->records as $record) {
            $record->remove_tag($this->tag);
            $record->add_tag($new_tag);
            $record->sync();
        }

        foreach($this->subtags as $subtag) {
            $subtag->rename($new_tag . "/" . substr($subtag->tag, strlen($this->tag) + 1));
        }

        $props = $this->properties();
        $this->delete_properties();

        $this->tag = $new_tag;

        $this->set_properties($props);

        return $new_tag;
    }

    public function delete() {
        $this->prepare();

        foreach($this->records as $record) {
            $record->remove_tag($this->tag);
            $record->sync();
        }

        foreach($this->subtags as $subtag) {
            $subtag->delete();
        }

        $this->delete_properties();
    }

    private function properties() {
        if($query = mySpires::db()->prepare("SELECT * FROM tags WHERE username = ? AND tag = ?")) {
            $query->bind_param("ss", $this->username, $this->tag);
            $query->execute();

            if($return = $query->get_result()->fetch_object()) {
                return $return;
            }
        }

        return null;
    }

    private function delete_properties() {
        if($query = mySpires::db()->prepare("DELETE FROM tags WHERE username = ? AND tag = ?")) {
            $query->bind_param("ss", $this->username, $this->tag);
            if($query->execute()) return true;
        }

        return false;
    }

    private function set_properties(stdClass $props = null) {
        $original_props = $this->properties();
        if(!$props) $props = (object)[];

        if(!$original_props) {
            if($q = mySpires::db()->prepare("INSERT INTO tags (username, tag) VALUES (?,?)")) {
                $q->bind_param("ss", $this->username, $this->tag);
                if($q->execute()) {
                    return $this->set_properties($props);
                }
            }
            return false;
        }

        foreach(["description", "starred", "shared", "visited"] as $key) {
            if(!property_exists($props, $key)) {
                $props->$key = $original_props->$key;
            }
        }
        $props->description = mySpires::db()->real_escape_string($props->description);

        if($query = mySpires::db()->prepare("UPDATE tags SET description = ?, starred = ?, shared = ?, visited  = ? WHERE username = ? AND tag = ?")) {
            $query->bind_param("ssssss",
                $props->description, $props->starred, $props->shared, $props->visited,
                $this->username, $this->tag);
            if($query->execute()) return true;
        }

        return false;
    }

    public function description($text) {
        $this->set_properties((object) ["description" => $text]);
    }

    public function star(bool $val) {
        $this->set_properties((object) ["starred" => (int)$val]);
    }

    public function share(bool $val) {
        $this->set_properties((object) ["shared" => (int)$val]);
    }
}