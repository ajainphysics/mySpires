<?php

class mySpires_Collaboration {
    private $loaded = false;

    public $collaboration;
    public $admin;
    public $name;
    public $collaborators = [];
    public $pending_collaborators = [];
    public $suggested_collaborators = [];

    function __construct($collaboration) {
        if(gettype($collaboration) === "string" || gettype($collaboration) === "integer") {
            $this->collaboration = $collaboration;
            $this->load();
        } else {
            if(gettype($collaboration) === "array") $collaboration = (object)$collaboration;

            if(!$collaboration->admin || !$collaboration->name || !mySpiresUser::username_exists($collaboration->admin))
                return;

            $this->name = preg_replace("/[^a-zA-Z0-9 \-]+/", "", $collaboration->name);
            $this->admin = $collaboration->admin;
            array_push($this->collaborators, $this->admin);

            $this->loaded = true;
        }
    }

    private function load() {
        $result = mySpires::db_query("SELECT * FROM collaborations WHERE collaboration = '{$this->collaboration}'");
        $data = $result->fetch_object();

        if(!$data) return false;

        $this->admin = $data->admin;
        $this->name = $data->name;
        if($data->collaborators)
            $this->collaborators = explode(",", $data->collaborators);
        if($data->pending)
            $this->pending_collaborators = explode(",", $data->pending);
        if($data->suggested)
            $this->suggested_collaborators = explode(",", $data->suggested);

        $this->loaded = true;

        return true;
    }

    public function people() {
        $people = array_merge($this->collaborators, $this->pending_collaborators, $this->suggested_collaborators);
        sort($people);

        return $people;
    }

    function save() {
        if(!$this->loaded) return false;
        $collaborators = implode(",", $this->collaborators);
        $pending_collaborators = implode(",", $this->pending_collaborators);
        $suggested_collaborators = implode(",", $this->suggested_collaborators);

        if($this->collaboration) {
            mySpires::db_query("UPDATE collaborations SET name = '{$this->name}', admin = '{$this->admin}', collaborators = '{$collaborators}', pending = '{$pending_collaborators}', suggested = '{$suggested_collaborators}' WHERE collaboration = {$this->collaboration}");
        } else {
            mySpires::db_query("INSERT INTO collaborations (name, admin, collaborators, pending, suggested) 
                    VALUES ('{$this->name}', '{$this->admin}', '{$collaborators}', '{$pending_collaborators}', '{$suggested_collaborators}')");

            $this->collaboration = mySpires::db()->insert_id;
        }

        return true;
    }

    function auth($username) {
        if(!$this->loaded) return false;
        if(in_array($username, $this->collaborators)) return true;
        return false;
    }

    private function collaborator_exists($username) {
        if(!$this->loaded) return false;
        if(in_array($username, $this->collaborators) || in_array($username, $this->pending_collaborators) || in_array($username, $this->suggested_collaborators)) return true;
        return false;
    }

    function add_collaborator($username) {
        if(!$this->loaded) return false;
        $username = mySpiresUser::email_to_username($username);
        if(!$username) return false;

        if($this->collaborator_exists($username)) return true;

        array_push($this->suggested_collaborators, $username);

        return true;
    }

    function approve_collaborator($username) {
        if(!$this->loaded) return false;
        $username = mySpiresUser::email_to_username($username);
        if(!$username) return false;

        if(!$this->remove_from_list($username, $this->suggested_collaborators)) return false;
        array_push($this->pending_collaborators, $username);

        return true;
    }

    function accept_collaborator($username) {
        if(!$this->loaded) return false;
        $username = mySpiresUser::email_to_username($username);
        if(!$username) return false;

        if(!$this->remove_from_list($username, $this->pending_collaborators)) return false;
        array_push($this->collaborators, $username);

        return true;
    }

    function remove_collaborator($username) {
        if(!$this->loaded) return false;
        $this->remove_from_list($username, $this->collaborators);
        $this->remove_from_list($username, $this->pending_collaborators);
        $this->remove_from_list($username, $this->suggested_collaborators);
    }

    private function remove_from_list($item, &$list) {
        $list = array_unique($list);
        if(!in_array($item, $list)) return false;

        unset($list[array_search($item, $list)]);
        return true;
    }
}