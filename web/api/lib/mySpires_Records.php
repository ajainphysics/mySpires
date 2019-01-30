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
            elseif($field == "timeframe") $this->load_timeframe($query);
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

        if(!$username) $username = mySpiresUser::current_username();
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
        $username = mySpiresUser::current_username();
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
        if (!mySpiresUser::current_username()) return;

        $x = explode("-",$range);
        $offset = $x[0] - 1;
        $chunk = $x[1] - $offset;

        if($chunk <= 0) return;

        $response = mySpires::find_entries(["bin" => true, "offset" => $offset, "count" => $chunk]);
        foreach($response as $raw) {
            $this->{$raw->id} = new mySpires_Record($raw);
        }
    }

    /**
     * Loads record(s) from the database matching a time-frame from history.
     * @param string $timeframe Time-frame to be loaded. Accepts "today", "yesterday", "this_week", "this_month",
     *                          "previous_month_i". Note that the time-frames are exclusive, so a publication in
     *                          "this_week" for example, will not load under "this_month".
     */
    private function load_timeframe($timeframe)
    {
        $username = mySpiresUser::current_username();
        if(!$username) return;

        // These are the MySQL filters for the dates of interest.
        if (substr($timeframe, 0, 15) == 'previous_month_') {
            $DateFilter = "DATE_FORMAT(DATE(updated), '%m-%Y') = DATE_FORMAT(DATE_SUB(CURRENT_DATE, INTERVAL "
                . substr($timeframe, 15) . " MONTH), '%m-%Y')"
                . " AND YEARWEEK(DATE(updated), 1) != YEARWEEK(CURRENT_DATE, 1)"
                . " AND DATE(updated) != DATE_SUB(CURRENT_DATE, INTERVAL 1 DAY)";
        } elseif ($timeframe == "this_month") {
            $DateFilter
                = "DATE_FORMAT(DATE(updated), '%m-%Y') = DATE_FORMAT(CURRENT_DATE, '%m-%Y')"
                . " AND YEARWEEK(DATE(updated), 1) != YEARWEEK(CURRENT_DATE, 1)"
                . " AND DATE(updated) != DATE_SUB(CURRENT_DATE, INTERVAL 1 DAY)";
        } elseif ($timeframe == "this_week") {
            $DateFilter
                = "YEARWEEK(DATE(updated), 1) = YEARWEEK(CURRENT_DATE, 1)"
                . " AND DATE(updated) != CURRENT_DATE"
                . " AND DATE(updated) != DATE_SUB(CURRENT_DATE, INTERVAL 1 DAY)";
        } elseif ($timeframe == "yesterday") {
            $DateFilter = "DATE(updated) = DATE_SUB(CURRENT_DATE, INTERVAL 1 DAY)";
        } else {
            $DateFilter = "DATE(updated) = CURRENT_DATE";
        }

        $results = mySpires::db_query("SELECT id, updated FROM history WHERE username = '{$username}' AND {$DateFilter}");

        $idArray = [];
        $historyDates = [];
        while ($history = $results->fetch_object()) {
            $historyDates[$history->id] = $history->updated;
            $idArray[] = $history->id;
        }

        $response = mySpires::find_records(["id" => implode(",", $idArray)]);
        foreach($response as $raw) {
            if (!$raw->updated) $raw->updated = $historyDates[$raw->id];
            $this->{$raw->id} = new mySpires_Record($raw);
        }
    }
}