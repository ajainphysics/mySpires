<?php

use refSpires\RefRecords;
use function mySpires\mysqli;
use function mySpires\query;
use function mySpires\users\user;
use function mySpires\users\username;
use function mySpires\users\verify;

/**
 * This class handles actions on multiple mySpires_Record objects.
 */
class mySpires_Records
{
    private $username = "";
    /**
     * Sets username
     * @param string $username
     */
    function username($username = null) {
        verify($username, true);
        $this->username = $username;
    }

    /**
     * mySpires_Records constructor.
     * @param \mySpires\Record[]|string $query
     * @param string $field
     * @param string $source
     */
    function __construct($query = "", $field = "", $source = "inspire")
    {
        $this->username();

        if(!$query && !$field) return;

        if(!$field) $field = "id";

        $rtype = gettype($query);
        if ($rtype == "array") {
            foreach ($query as $key => $value) {
                $this->$key = $value;
            }
        } elseif ($rtype == "string") {
            if(!$field) $field = "id";
            if(!$source) $source = "inspire";

            if($field == "tag") $this->load_tag($query);
            elseif($field == "history") $this->load_history($query);
            elseif($field == "bin") $this->load_bin($query);
            elseif($field == "lookup") $this->load_lookup($query);
            elseif($field == "search") {
                $response = new RefRecords($query, null, $source);
                foreach($response->records as $raw)
                    $this->{$raw->inspire} = new \mySpires\Record($raw, ["username"=>$this->username]);
            }
            else {
                if (sizeof(explode(",", $query)) == 1) {
                    $record = new \mySpires\Record($query, $field, $this->username);
                    $this->{$record->$field} = $record;
                }
                else {
                    $response = mySpires::find_records([$field => $query]);
                    foreach ($response as $raw) {
                        $this->{$raw->$field} = new \mySpires\Record($raw, ["username"=>$this->username]);
                    }
                }
            }
        }
    }

    function find($data = null) {
        if(gettype($data) == "array") $data = (object)$data;

        $queries = [];
        foreach(["inspire", "arxiv", "id", "bibkey", "ads", "doi"] as $field) {
            if(property_exists($data, $field) && $data->$field) {
                if(gettype($data->$field) == "string")
                    $data->$field = array_map('trim', explode(",", $data->$field));

                $q = implode(",", array_map(function($val){
                    return "'". trim($val) . "'";
                }, $data->$field));

                $queries[] .= "($field IN ($q))";
            }
        }

        $results = null;
        if(sizeof($queries)) {
            $query = implode(" OR ", $queries);
            $results = query("SELECT * FROM records WHERE $query");
        }
        elseif($data === null) {
            $results = query("SELECT * FROM records");
        }

        if($results) {
            $records = [];
            $idArray = [];
            while($result = $results->fetch_object()) {
                $record = (object)[];
                $properties = Array("id", "inspire", "bibkey", "arxiv", "arxiv_v", "ads", "doi",
                    "title", "author", "author_id", "bibtex", "published", "temp");
                foreach($properties as $property)
                    $record->$property = $result->$property;

                $record->status = "unsaved";
                $records[$record->id] = $record;
                $idArray[] = $record->id;
            }

            $username = $this->username;

            if($username && sizeof($records) > 0) {
                $q = implode(",", array_map(function ($id) {
                    return "'". trim($id) . "'";
                }, $idArray));

                $results = query("SELECT * FROM entries WHERE id IN ($q) AND username = '$username'");
                while($result = $results->fetch_object()) {
                    $records[$result->id]->tags = $result->tags;
                    $records[$result->id]->comments = $result->comments;
                    $records[$result->id]->updated = $result->updated;

                    if($result->bin) $records[$result->id]->status = "binned";
                    else $records[$result->id]->status = "saved";
                }
            }

            foreach ($records as $id => $record)
                $this->$id = new \mySpires\Record($record, ["username"=>$this->username]);
        }
    }

    function fetch($data) {
        $RefRecords = new RefRecords();
        $RefRecords->fetch($data);

        $i = 0;
        foreach ($RefRecords->records as $RefRecord) {
            $this->{"result_" . $i} = new \mySpires\Record($RefRecord, ["username"=>$this->username]);
            $i++;
        }
    }

    /**
     * Returns an iterable array of mySpires records.
     * @return \mySpires\Record[]
     */
    function records() {
        $records = Array();
        foreach (get_object_vars($this) as $key => $record)
            if ($record instanceof \mySpires\Record) $records[$key] = $record;
        return $records;
    }

    /**
     * Syncs all the records.
     * @return bool Returns true if successfully synced, false otherwise.
     */
    function sync()
    {
        foreach($this->records() as $record) $record->sync();
        return true;
    }

    /** Records history for all the records. Use with caution. */
    function history()
    {
        foreach($this->records() as $record) $record->history();
    }

    /** Updates all the records. */
    function update()
    {
        foreach($this->records() as $record) $record->update();
    }

    /**
     * Loads record(s) from the database matching a tag.
     * @param string $tag Tag to be loaded.
     * @param string [$username]  Username
     */
    private function load_tag($tag)
    {
        $e = explode(":", $tag);
        $username = "";
        if(sizeof($e) == 2) {
            $tag = $e[1];
            $username = $e[0];
        }

        if(!$username) $username = username();
        if(!$username) return;

        // We first get all entries corresponding to a tag
        if ($tag == "Recent") {
            $results = query("SELECT * FROM entries WHERE username = '{$username}' AND bin=0
                                                ORDER BY TIMESTAMP(updated) DESC LIMIT 60");
        } elseif ($tag == "Untagged" || $tag == "") {
            $results = query("SELECT * FROM entries WHERE username = '{$username}' AND bin=0 AND tags = ''");
        } else {
            $results = query("SELECT * FROM entries WHERE username = '{$username}' AND bin=0 AND tags LIKE '%%{$tag}%%'");
        }

        // Now we need to prepare a list of IDs for the records table.
        // When searching for a tag "Fluids" mySQL will also return "Holographic Fluids". We need to eliminate these.
        // $entries = Array();
        $idArray = [];
        while ($entry = $results->fetch_object()) {
            $tagArray = explode(",", $entry->tags);

            if ($tag == "Untagged" || $tag == "Recent") $idArray[] =  $entry->id;
            else {
                foreach ($tagArray as $dbtag) {
                    if (trim($dbtag) == trim($tag)) {
                        $idArray[] =  $entry->id;
                        break;
                    }
                }
            }
        }

        $response = mySpires::find_records(["id" => implode(",", $idArray)]);
        foreach($response as $raw)
            $this->{$raw->id} = new \mySpires\Record($raw, ["username"=>$this->username]);
    }

    private function load_history($range) {
        $username = username();
        if (!$username) return;

        $x = explode("-",$range);
        $offset = $x[0] - 1;
        $chunk = $x[1] - $offset;

        if($chunk <= 0) return;

        $results = query("SELECT id, updated FROM history WHERE username = '{$username}' ORDER BY updated DESC LIMIT {$offset},{$chunk}");

        $idArray = [];
        $historyDates = [];
        while ($history = $results->fetch_object()) {
            $historyDates[$history->id] = $history->updated;
            $idArray[] = $history->id;
        }

        $response = mySpires::find_records(["id" => implode(",", $idArray)]);
        foreach($response as $raw) {
            if ($historyDates[$raw->id]) $raw->updated = $historyDates[$raw->id];
            $this->{$raw->id} = new \mySpires\Record($raw, ["username"=>$this->username]);
        }

    }

    private function load_bin($range) {
        $username = username();
        if(!$username) return;

        $x = explode("-",$range);
        $offset = $x[0] - 1;
        $chunk = $x[1] - $offset;

        if($chunk <= 0) return;

        $results = query("SELECT id FROM entries WHERE username = '$username' AND bin=1 ORDER BY updated DESC LIMIT $offset,$chunk");

        $records = [];
        while ($entry = $results->fetch_object())
            $records[] = $entry->id;

        $response = mySpires::find_records(["id" => implode(",", $records)]);

        foreach($response as $raw) {
            $this->{$raw->id} = new \mySpires\Record($raw, ["username"=>$this->username]);
        }
    }

    private function load_lookup($query) {
        if (!user()) return;

        $query = preg_replace('/[^A-Za-z0-9]/', ' ', $query);
        $terms = array_filter(explode(" ", $query));

        $result_arrays = array_map(function($q) {
            $pattern = "%" . $q . "%";
            $username = username();

            $matched_records = [];

            if ($query = mysqli()->prepare("SELECT id FROM records WHERE title LIKE ? OR author LIKE ?")) {
                $query->bind_param("ss", $pattern, $pattern);
                $query->execute();

                $records = $query->get_result();

                while ($record = $records->fetch_object()) {
                    array_push($matched_records, $record->id);
                }
            }

            $results = [];

            if ($query = mysqli()->prepare("SELECT id FROM entries WHERE username = ?")) {
                $query->bind_param("s", $username);
                $query->execute();

                $entries = $query->get_result();

                while ($entry = $entries->fetch_object()) {
                    if (in_array($entry->id, $matched_records))
                        array_push($results, $entry->id);
                }
            }

            if ($query = mysqli()->prepare("SELECT id FROM entries WHERE username = ? AND (comments LIKE ? OR tags LIKE ?)")) {
                $query->bind_param("sss", $username, $pattern, $pattern);
                $query->execute();

                $entries = $query->get_result();

                while ($entry = $entries->fetch_object()) {
                    array_push($results, $entry->id);
                }
            }

            $results = array_unique($results);
            sort($results);

            return $results;

        }, $terms);


        if(sizeof($result_arrays) == 0)
            $results = [];
        else if(sizeof($result_arrays) == 1)
            $results = $result_arrays[0];
        else
            $results = array_values(array_intersect(...$result_arrays));

        $response = mySpires::find_records(["id" => implode(",", $results)]);
        foreach($response as $raw)
            $this->{$raw->id} = new \mySpires\Record($raw, ["username"=>$this->username]);
    }
}