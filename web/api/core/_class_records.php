<?php
namespace mySpires;

use refSpires\RefRecords;
use function mySpires\records\find;
use function mySpires\users\verify;
use const mySpires\records\UNIQUE_FIELDS;

/**
 * This class handles actions on multiple mySpires_Record objects.
 */
class Records
{
    private $username = "";

    /**
     * Sets username
     * @param string $username
     */
    function username($username = null)
    {
        verify($username, true);
        $this->username = $username;
    }

    /**
     * mySpires_Records constructor.
     * @param Record[]|string $query
     * @param string $field
     * @param string $source
     */
    function __construct($query = "", $field = "", $source = "inspire")
    {
        $this->username();

        if (!$query && !$field) return;

        if (!$field) $field = "id";

        $rtype = gettype($query);
        if ($rtype == "array") {
            foreach ($query as $record) {
                $record = new Record($record);
                $record->sync();
                $this->{$record->id} = $record;
            }
        } elseif ($rtype == "string") {
            if (!$field) $field = "id";
            if (!$source) $source = "inspire";

            if ($field == "search") {
                $response = new RefRecords($query, null, $source);
                foreach ($response->records as $raw)
                    $this->{$raw->inspire} = new Record($raw, ["username" => $this->username]);
            } else {
                if (sizeof(explode(",", $query)) == 1) {
                    $record = new Record($query, $field, $this->username);
                    $this->{$record->$field} = $record;
                } else {
                    $response = find([$field => $query]);
                    foreach ($response as $raw) {
                        $this->{$raw->$field} = new Record($raw, ["username" => $this->username]);
                    }
                }
            }
        }
    }

    /**
     * Returns an iterable array of mySpires records.
     * @return Record[]
     */
    function records()
    {
        $records = array();
        foreach (get_object_vars($this) as $key => $record)
            if ($record instanceof Record) $records[$key] = $record;
        return $records;
    }

    function sort($field)
    {
        $records = $this->records();
        foreach (get_object_vars($this) as $key => $record)
            if ($record instanceof Record) unset($this->$key);

        if (!in_array($field, UNIQUE_FIELDS)) return $this;

        foreach ($records as $record) {
            if ($record->$field) $this->{$record->$field} = $record;
        }

        return $this;
    }

    function first()
    {
        $records = $this->records();
        return reset($records);
    }

    /**
     * Syncs all the records.
     * @return bool Returns true if successfully synced, false otherwise.
     */
    function sync()
    {
        foreach ($this->records() as $record) $record->sync();
        return true;
    }

    /** Records history for all the records. Use with caution. */
    function history()
    {
        foreach ($this->records() as $record) $record->history();
    }

    /** Updates all the records. */
    function update()
    {
        foreach ($this->records() as $record) $record->update();
    }

}
