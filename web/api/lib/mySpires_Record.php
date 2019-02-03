<?php

/**
 * mySpires_Record class deals with individual records in an efficient manner.
 * It provides useful methods enabling to load, add, update or delete records from the database.
 * It works in a sync fashion. Local changes are not uploaded to the server unless explicitly synced.
 */
class mySpires_Record
{
    private $loaded = false;
    private $username = "";

    public $id; // mySpires record id
    public $inspire;  // INSPIRE id
    public $arxiv; // arXiv id
    public $arxiv_v; // arXiv version
    public $bibkey; // INSPIRE BibTeX key
    public $title;  // Title
    public $author; // Author(s)
    public $published; // Date of publication/appearance
    public $doi; // DOI of publication
    public $temp; // Is entry temporary? Used to check if BibTeX record should be updated.
    public $tags = ""; // mySpires tags
    public $comments = ""; // mySpires comments
    public $updated; // Date of last update
    public $status = "unsaved"; // Current status: unsaved/saved/binned

    /**
     * mySpires_Record constructor.
     * @param string|object $query Either the query to be loaded or a MySQL records result
     * @param string $field Either field of the query, a MySQL entries result or a date for temporary entry.
     * @param string $username
     */
    function __construct($query = null, $field = null, $username = null) {
        if(!$username) $username = mySpiresUser::current_username();
        if(mySpiresUser::username_exists($username)) $this->username = $username;

        $qtype = gettype($query);
        if($qtype == "object") {
            $this->populate($query);
        }
        elseif($qtype == "integer" || $qtype == "string") {
            if (!$field) $field = "id";
            $response = mySpires::find_records([$field=>$query], $this->username);
            $response = reset($response);

            if($response) $this->populate($response);
            elseif(in_array($field, Array("inspire", "arxiv"))) {
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
        if(!$source->id) {
            $savedRecord = mySpires::find_records(["inspire"=>$source->inspire, "arxiv"=>$source->arxiv], $this->username);
            $savedRecord = reset($savedRecord);
            if($savedRecord) $this->populate($savedRecord);
        }

        $properties = Array("id","arxiv","inspire","arxiv_v","bibkey","title","author","published","doi","updated","status");
        foreach($properties as $property) {
            if(property_exists($source, $property) && $source->$property) $this->$property = $source->$property;
        }
        if(property_exists($source,"temp")) $this->temp = $source->temp;
        if(property_exists($source,"tags")) $this->tags = $source->tags;
        if(property_exists($source,"comments")) $this->comments = $source->comments;

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
        if(!in_array($field, ["inspire", "arxiv"])) return false;

        // Try loading from INSPIRE.
        $q = $query;
        if($field == "inspire") $q = "find recid " . $query; // Dodgy bit about "find" or not to "find".
        elseif($field == "arxiv") $q = "find eprint " . $query;

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

        // Return false if not fetched.
        return false;
    }

    public function add_tag($tag) {
        $tag = mySpiresTags::cleanup($tag);
        if(!$tag) return false;

        $this->tags .= "," . $tag;
        $this->tags = mySpiresTags::cleanup_list($this->tags);

        return true;
    }

    public function remove_tag($tag) {
        $tag = mySpiresTags::cleanup($tag);
        if(!$tag) return false;

        $this->tags = mySpiresTags::cleanup_list($this->tags);

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

        if ($this->inspire) {
            $this->fetch($this->inspire, "inspire");
            $this->sync();
            return true;
        } elseif ($this->arxiv) {
            $this->fetch($this->arxiv, "arxiv");
            $this->sync();
            return true;
        } else
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
        $db = mySpires::db();

        // First we need to process things that are going into the database.

        if($this->published) $published = "'" . $db->real_escape_string($this->published) . "'";
        else $published = "NULL";

        $inspire = "'" . $db->real_escape_string($this->inspire) . "'";
        $bibkey = "'" . $db->real_escape_string($this->bibkey) . "'";
        $title = "'" . $db->real_escape_string($this->title) . "'";
        $author = "'" . $db->real_escape_string($this->author) . "'";
        $arxiv = "'" . $db->real_escape_string($this->arxiv) . "'";
        $arxiv_v = "'" . $db->real_escape_string($this->arxiv_v) . "'";
        $doi = "'" . $db->real_escape_string($this->doi) . "'";
        $temp = "'" . $db->real_escape_string($this->temp) . "'";

        if (!$this->id) {
            mySpires::db_query("INSERT INTO records (inspire, bibkey, title, author, arxiv, arxiv_v, published, doi, temp) 
                    VALUES ($inspire, $bibkey, $title, $author, $arxiv, $arxiv_v, $published, $doi, $temp)");
            $this->id = mySpires::db()->insert_id;
        } else {
            mySpires::db_query("UPDATE records SET inspire = $inspire, bibkey = $bibkey, title = $title, 
                              author = $author, arxiv = $arxiv, arxiv_v = $arxiv_v, published = $published, 
                              doi = $doi, temp = $temp,
                              updated = now() WHERE id = {$this->id}");
        }

        if ($this->username) {
            $this->tags = mySpiresTags::cleanup_list($this->tags);
            $tags = "'" . $db->real_escape_string($this->tags) . "'";
            $comments = "'" . $db->real_escape_string($this->comments) . "'";

            // If saved, sync data with the entries table.
            if ($this->status === "saved" || $this->status === "binned") {
                mySpires::db_query("INSERT INTO entries (username, id, tags, comments, bin) 
                    VALUES ('{$this->username}', {$this->id}, $tags, $comments, 0)
                    ON DUPLICATE KEY UPDATE tags = $tags, comments = $comments, bin = 0");
                $this->updated = date('Y-m-d H:i:s');
            }

            // If binned, mark as binned
            if ($this->status === "binned") {
                mySpires::db_query("UPDATE entries SET bin=1 
                    WHERE id = {$this->id} AND username = '{$this->username}'");
            }

            // If unsaved, delete from the entries table
            if ($this->status === "unsaved") {
                $this->comments = "";
                $this->tags = "";
                mySpires::db_query("DELETE FROM entries 
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

        if(!mySpiresUser::info()->history_enabled) return; // If history is not enabled, return.

        mySpires::db_query("INSERT INTO history (username, id) VALUES ('{$this->username}', '{$this->id}') 
                                           ON DUPLICATE KEY UPDATE hits = hits + 1");
    }
}