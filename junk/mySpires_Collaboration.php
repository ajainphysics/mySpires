<?php

use function \mySpires\mysqli;

class mySpires_Collaboration {
    private $loaded = false;

    public $cid;
    public $admin;
    public $name;
    public $collaborators = [];
    public $pending_collaborators = [];
    public $suggested_collaborators = [];

    function __construct($cid) {
        if(gettype($cid) === "string" || gettype($cid) === "integer") {
            $this->cid = $cid;
            $this->load();
        } else {
            $data = $cid;
            if(gettype($data) === "array") $data = (object)$data;

            if(!$data->admin || !$data->name || !\mySpires\users\verify($data->admin))
                return;

            $this->name = preg_replace("/[^a-zA-Z0-9 \-]+/", "", $data->name);
            $this->admin = $data->admin;
            array_push($this->collaborators, $this->admin);

            $this->loaded = true;
        }
    }

    private function load() {
        $result = \mySpires\query("SELECT * FROM collaborations WHERE cid = '{$this->cid}'");
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

        if($this->cid) {
            \mySpires\query("UPDATE collaborations SET name = '{$this->name}', admin = '{$this->admin}', collaborators = '{$collaborators}', pending = '{$pending_collaborators}', suggested = '{$suggested_collaborators}' WHERE cid = {$this->cid}");
        } else {
            \mySpires\query("INSERT INTO collaborations (name, admin, collaborators, pending, suggested) 
                    VALUES ('{$this->name}', '{$this->admin}', '{$collaborators}', '{$pending_collaborators}', '{$suggested_collaborators}')");

            $this->cid = mysqli()->insert_id;
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
        if(!\mySpires\users\verify($username)) return false;

        if($this->collaborator_exists($username)) return true;

        array_push($this->suggested_collaborators, $username);

        return true;
    }

    function approve_collaborator($username) {
        if(!$this->loaded) return false;
        if(!\mySpires\users\verify($username)) return false;

        if(!$this->remove_from_list($username, $this->suggested_collaborators)) return false;
        array_push($this->pending_collaborators, $username);

        return true;
    }

    function accept_collaborator($username) {
        if(!$this->loaded) return false;
        if(!\mySpires\users\verify($username)) return false;

        if(!$this->remove_from_list($username, $this->pending_collaborators)) return false;
        array_push($this->collaborators, $username);

        return true;
    }

    function remove_collaborator($username) {
        if(!$this->loaded) return false;
        $this->remove_from_list($username, $this->collaborators);
        $this->remove_from_list($username, $this->pending_collaborators);
        $this->remove_from_list($username, $this->suggested_collaborators);
        return true;
    }

    private function remove_from_list($item, &$list) {
        $list = array_unique($list);
        if(!in_array($item, $list)) return false;

        unset($list[array_search($item, $list)]);
        return true;
    }
}