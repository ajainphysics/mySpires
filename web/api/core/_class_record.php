<?php
namespace mySpires;

use refSpires\RefRecord;
use function mySpires\records\find;
use function mySpires\tags\cleanup;
use function mySpires\tags\cleanup_list;
use function mySpires\users\user;
use function mySpires\users\verify;

/**
 * mySpires_Record class deals with individual records in an efficient manner.
 * It provides useful methods enabling to load, add, update or delete records from the database.
 * It works in a sync fashion. Local changes are not uploaded to the server unless explicitly synced.
 */
class Record
{
    private $loaded = false;
    private $fields = Array("inspire", "arxiv", "doi", "bibkey", "ads");
    private $meta_properties = Array(
        "id", "inspire", "arxiv", "arxiv_v", "bibkey", "ads", "doi",
        "title", "author", "author_id", "published", "bibtex");
    private $status_properties = Array("updated", "status");
    private $user_properties = Array("tags", "comments", "temp");

    public $id;
    public $inspire, $arxiv, $arxiv_v, $bibkey, $ads, $doi,
        $title, $author, $author_id, $published, $bibtex, $temp; // Properties to be copied from RefSpires
    public $tags = ""; // mySpires tags
    public $comments = ""; // mySpires comments
    public $updated; // Date of last update
    public $status = "unsaved"; // Current status: unsaved/saved/binned

    private $username = "";
    function username($username = null) {
        verify($username, true);
        $this->username = $username;
    }

    /**
     * mySpires_Record constructor.
     * @param string|object $query Either the query to be loaded or a MySQL records result
     * @param string|object|array $field Either field of the query, a MySQL entries result or a date for temporary entry.
     * @param string $username
     */
    function __construct($query = null, $field = null, $username = null) {
        $this->username($username);

        $qtype = gettype($query);
        if($qtype == "object") {
            if(gettype($field) == "array") $opts = (object)$field;
            elseif(gettype($field) == "object") $opts = $field;
            else $opts = (object)[];

            if(property_exists($opts,"username")) $this->username($opts->username);

            foreach($this->fields as $f)
                if(!property_exists($query, $f)) $query->$f = null;
            $this->populate($query);
        }
        elseif($qtype == "integer" || $qtype == "string") {
            if (!$field) $field = "id";

            $myS = find([$field=>$query], $username);

            $response = $myS->records();
            $response = reset($response);

            // $response = mySpires::find_records([$field=>$query], $this->username);
            // $response = reset($response);
            if($response) $this->populate($response);
            elseif(in_array($field, $this->fields)) {
                $this->fetch($query, $field);
                $this->sync();
            }
        }
    }

    /* ===== Loading Functions ===== */
    /* The following functions load the information into the mySpires_Record object.
    All of them will turn the $loaded flag to true. */

    /**
     * @param $source Object The source to populate data.
     */
    private function populate($source) {
        // If you provide id, the entry will not be synced with the database.
        if(!property_exists($source,"id") || !$source->id) {
            $myS = find([
                "inspire" => $source->inspire,
                "arxiv" => $source->arxiv,
                "bibkey" => $source->bibkey,
                "ads" => $source->ads,
                "doi" => $source->doi
            ], $this->username);
            $savedRecord = $myS->records();
            $savedRecord = reset($savedRecord);
            if($savedRecord) $this->populate($savedRecord);
        }

        // Overwrite if the source is non-empty
        foreach($this->meta_properties as $property) {
            // Overwrite if the existing properties are empty
            if((!property_exists($source, "source") || $source->source != "inspire") && $this->inspire) {
                if (property_exists($source, $property) && $source->$property && !$this->$property)
                    $this->$property = $source->$property;
            }
            // Overwrite even if the existing properties are non-empty
            else {
                if (property_exists($source, $property) && $source->$property)
                    $this->$property = $source->$property;
            }
        }

        // Overwrite if the source is non-empty even if the existing properties are non-empty
        foreach($this->status_properties as $property) {
            if (property_exists($source, $property) && $source->$property)
                $this->$property = $source->$property;
        }

        // Overwrite even if the source is empty and even if the existing properties are non-empty
        foreach($this->user_properties as $property)
            if(property_exists($source, $property))
                $this->$property = $source->$property;

        // preprint($this);

        $this->loaded = true;
    }

    /**
     * Fetches and loads a result from INSPIRE or arXiv servers.
     * @param string $query Query to be searched (e.g. "1394207", or "1509.05777")
     * @param string $field Field of query (e.g. "inspire" or "arxiv")
     * @return bool True if loaded, false otherwise
     */
    private function fetch($query, $field = "inspire")
    {
        if(!in_array($field, $this->fields))
            return false;

        $response = new RefRecord($query, $field);
        if($response->populated) {
            $this->populate($response);
        }

        return false; // Return false if not fetched.

        /*
        $response = new InspireRecord($q);
        if($response->inspire) {
            $this->populate($response);
            return true;
        }

        // Didn't find in INSPIRE? Try your luck on arXiv.
        if ($field == "arxiv") {
            $response = new ArxivRecord($query);
            if($response->arxiv) {
                $this->populate($response);
                return true;
            }
        }
        */


    }

    public function add_tag($tag) {
        $tag = cleanup($tag);
        if(!$tag) return false;

        $this->tags .= "," . $tag;
        $this->tags = cleanup_list($this->tags);

        return true;
    }

    public function remove_tag($tag) {
        $tag = cleanup($tag);
        if(!$tag) return false;

        $this->tags = cleanup($this->tags);

        $tags = explode(",", $this->tags);
        $tags = array_filter($tags, function($t) use($tag) {
            if($t == $tag) return false;
            else return true;
        });
        $this->tags = implode(",", $tags);

        return true;
    }

    /* ===== Execution Functions ===== */
    /* The following functions take care of the executions functions: to sync, update, save or delete data. Their action is only non-trivial if the $loaded flag is true. */

    /**
     * Updates the record using available servers.
     * @return bool Returns true if updated successfully, false otherwise.
     */
    function update()
    {
        if (!$this->loaded) return false;
        foreach($this->fields as $field) {
            if ($this->$field) {
                $this->fetch($this->$field, $field);
                $this->sync();
                return true;
            }
        }
        return false;
    }

    /**
     * Saves a record in the current user's library.
     * Not to be confused with sync() which saves a record into the common records table.
     */
    function save()
    {
        if (!$this->loaded || !$this->username) return;
        $this->status = "saved";
        $this->sync();
    }

    /**
     * Deletes a record from current user's library and moves it to bin.
     * There is no function to remove a record from records table, as it might cause complications in entries table.
     */
    function delete()
    {
        if (!$this->username || !$this->id || !$this->loaded) return;
        $this->status = "binned";
        $this->sync();
    }

    /**
     * Permanently deletes a record from current user's library.
     * There is no function to remove a record from records table, as it might cause complications in entries table.
     */
    function erase()
    {
        if (!$this->username || !$this->id || !$this->loaded) return;
        $this->status = "unsaved";
        $this->sync();
    }

    /**
     * Syncs current status of the object with the database.
     * This function does all the dirty work related to records and entries tables.
     * If the record does not exist in records table, calling sync() will add it.
     * @return bool True if synced, false otherwise
     */
    function sync()
    {
        if (!$this->loaded) return false;
        $db = mysqli();

        // First we need to process things that are going into the database.

        if($this->published) $published = "'" . $db->real_escape_string($this->published) . "'";
        else $published = "NULL";

        $inspire = "'" . $db->real_escape_string($this->inspire) . "'";
        $bibkey = "'" . $db->real_escape_string($this->bibkey) . "'";
        $ads = "'" . $db->real_escape_string($this->ads) . "'";
        $title = "'" . $db->real_escape_string($this->title) . "'";
        $author = "'" . $db->real_escape_string($this->author) . "'";
        $author_id = "'" . $db->real_escape_string($this->author_id) . "'";
        $bibtex = "'" . $db->real_escape_string($this->bibtex) . "'";
        $arxiv = "'" . $db->real_escape_string($this->arxiv) . "'";
        $arxiv_v = "'" . $db->real_escape_string($this->arxiv_v) . "'";
        $doi = "'" . $db->real_escape_string($this->doi) . "'";
        $temp = "'" . $db->real_escape_string($this->temp) . "'";

        if (!$this->id) {
            query(
                "INSERT INTO records (inspire, bibkey, ads, title, author, author_id, bibtex, arxiv, arxiv_v, published, doi, temp) 
                    VALUES ($inspire, $bibkey, $ads, $title, $author, $author_id, $bibtex, $arxiv, $arxiv_v, $published, $doi, $temp)");
            $this->id = mysqli()->insert_id;
        } else {
            // Update record with override not set to 1
            query(
                "UPDATE records SET inspire = $inspire, bibkey = $bibkey, ads = $ads, arxiv = $arxiv, arxiv_v = $arxiv_v, 
                   title = $title, author = $author, author_id = $author_id, bibtex = $bibtex, published = $published, 
                              doi = $doi, temp = $temp,
                              updated = now() WHERE (id = {$this->id} AND override = 0)");
        }

        if ($this->username) {
            $this->tags = cleanup_list($this->tags);
            $tags = "'" . $db->real_escape_string($this->tags) . "'";
            $comments = "'" . $db->real_escape_string($this->comments) . "'";

            // If saved, sync data with the entries table.
            if ($this->status === "saved" || $this->status === "binned") {
                query("INSERT INTO entries (username, id, tags, comments, bin) 
                    VALUES ('{$this->username}', {$this->id}, $tags, $comments, 0)
                    ON DUPLICATE KEY UPDATE tags = $tags, comments = $comments, bin = 0");
                $this->updated = date('Y-m-d H:i:s');
            }

            // If binned, mark as binned
            if ($this->status === "binned") {
                query("UPDATE entries SET bin=1 
                    WHERE id = {$this->id} AND username = '{$this->username}'");
            }

            // If unsaved, delete from the entries table
            if ($this->status === "unsaved") {
                $this->comments = "";
                $this->tags = "";
                query("DELETE FROM entries 
                    WHERE id = {$this->id} AND username = '{$this->username}'");
            }
        }

        return true;
    }

    /**
     * Records history for the record.
     */
    function history() {
        if(!$this->loaded || !$this->username) return; // Go back if not loaded or not logged in.

        if(!user()->info->history_enabled) return; // If history is not enabled, return.

        query("INSERT INTO history (username, id) VALUES ('{$this->username}', '{$this->id}') 
                                           ON DUPLICATE KEY UPDATE hits = hits + 1");
    }

    function author_lastnames() {
        if(!$this->loaded || !$this->username) return null; // Go back if not loaded or not logged in.

        $results = explode(",", $this->author);
        array_walk($results, function(&$a) {
            $e = explode(" ", trim($a));
            $a = end($e);
        });

        if(sizeof($results) > 6) return $results[0] . " et al.";
        else return implode(", ", $results);
    }
}
