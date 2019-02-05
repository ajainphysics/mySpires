<?php

/**
 * This class handles actions on multiple mySpires_Record objects.
 */
class mySpires_Records
{
    /**
     * mySpires_Records constructor.
     * @param mySpires_Record[]|string $query
     * @param string $field
     */
    function __construct($query, $field = "id")
    {
        $rtype = gettype($query);
        if ($rtype == "array") {
            foreach ($query as $key => $value) {
                $this->$key = $value;
            }
        } elseif ($rtype == "string") {
            if(!$field) $field = "id";
            if($field == "tag") $this->load_tag($query);
            elseif($field == "history") $this->load_history($query);
            elseif($field == "bin") $this->load_bin($query);
            elseif($field == "search") {
                $response = new InspireRecords($query);
                foreach($response->records as $raw) $this->{$raw->inspire} = new mySpires_Record($raw);
            }
            else {
                if (sizeof(explode(",", $query)) == 1) {
                    $record = new mySpires_Record($query,$field);
                    $this->{$record->$field} = $record;
                }
                else {
                    $response = mySpires::find_records([$field => $query]);
                    foreach ($response as $raw) {
                        $this->{$raw->$field} = new mySpires_Record($raw);
                    }
                }
            }
        }
    }

    /**
     * Returns an iterable array of mySpires records.
     * @return mySpires_Record[]
     */
    function records() {
        $records = Array();
        foreach (get_object_vars($this) as $key => $record)
            if ($record instanceof mySpires_Record) $records[$key] = $record;
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

        if(!$username) $username = mySpires::username();
        if(!$username) return;

        // We first get all entries corresponding to a tag
        if ($tag == "Recent") {
            $results = mySpires::db_query("SELECT * FROM entries WHERE username = '{$username}' AND bin=0
                                                ORDER BY TIMESTAMP(updated) DESC LIMIT 60");
        } elseif ($tag == "Untagged" || $tag == "") {
            $results = mySpires::db_query("SELECT * FROM entries WHERE username = '{$username}' AND bin=0 AND tags = ''");
        } else {
            $results = mySpires::db_query("SELECT * FROM entries WHERE username = '{$username}' AND bin=0 AND tags LIKE '%%{$tag}%%'");
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
        foreach($response as $raw) $this->{$raw->id} = new mySpires_Record($raw);
    }

    private function load_history($range) {
        $username = mySpires::username();
        if (!$username) return;

        $x = explode("-",$range);
        $offset = $x[0] - 1;
        $chunk = $x[1] - $offset;

        if($chunk <= 0) return;

        $results = mySpires::db_query("SELECT id, updated FROM history WHERE username = '{$username}' ORDER BY updated DESC LIMIT {$offset},{$chunk}");

        $idArray = [];
        $historyDates = [];
        while ($history = $results->fetch_object()) {
            $historyDates[$history->id] = $history->updated;
            $idArray[] = $history->id;
        }

        $response = mySpires::find_records(["id" => implode(",", $idArray)]);
        foreach($response as $raw) {
            if ($historyDates[$raw->id]) $raw->updated = $historyDates[$raw->id];
            $this->{$raw->id} = new mySpires_Record($raw);
        }

    }

    private function load_bin($range) {
        if (!mySpires::user()) return;

        $x = explode("-",$range);
        $offset = $x[0] - 1;
        $chunk = $x[1] - $offset;

        if($chunk <= 0) return;

        $response = mySpires::find_entries(["bin" => true, "offset" => $offset, "count" => $chunk]);
        foreach($response as $raw) {
            $this->{$raw->id} = new mySpires_Record($raw);
        }
    }
}