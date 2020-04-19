<?php
namespace mySpires\records;

use mySpires\Record, mySpires\Records;
use refSpires\RefRecords;

use function mySpires\mysqli;
use function
    mySpires\query,
    mySpires\users\username,
    mySpires\users\verify as verify_username;

require_once __DIR__ . "/../library/refSpires.php";

const UNIQUE_FIELDS = ["id", "inspire", "arxiv", "doi", "bibkey", "ads"];

/**
 * This function fetches data online. The syntax is same as find.
 * @param object $data
 * @return Records
 */
function fetch($data) {
    $RefRecords = new RefRecords();
    $RefRecords->fetch($data);
    return new Records($RefRecords->records);
}

/**
 * The core function to find records in the database.
 * It expects an object with keys as unique fields and values either arrays or comma separated strings.
 * @param object $data
 * @param string $username
 * @return Records
 */
function find($data = null, $username = null) {
    if(gettype($data) == "array") $data = (object)$data;

    $queries = [];
    foreach(UNIQUE_FIELDS as $field) {
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

    if(!$results) return null;

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

    verify_username($username, true);

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

    return new Records($records);
}

/**
 * Returns the binned entries within a range.
 * @param string $range Expects range in the format "1-10".
 * @return Records
 */
function bin($range) {
    $username = username();
    if(!$username) return null;

    $x = explode("-",$range);
    $offset = $x[0] - 1;
    $chunk = $x[1] - $offset;

    if($chunk <= 0) return null;

    $results = query("SELECT id FROM entries WHERE username = '$username' AND bin=1 ORDER BY updated DESC LIMIT $offset,$chunk");

    $records = [];
    while ($entry = $results->fetch_object())
        $records[] = $entry->id;

    return find(["id" => implode(",", $records)]);
}

/**
 * Returns history within a range.
 * @param string $range Expects range in the format "1-10".
 * @return Records
 */
function history($range) {
    $username = username();
    if (!$username) return null;

    $x = explode("-",$range);
    $offset = $x[0] - 1;
    $chunk = $x[1] - $offset;

    if($chunk <= 0) return null;

    $results = query("SELECT id, updated FROM history WHERE username = '{$username}' ORDER BY updated DESC LIMIT {$offset},{$chunk}");

    $idArray = [];
    $historyDates = [];
    while ($history = $results->fetch_object()) {
        $historyDates[$history->id] = $history->updated;
        $idArray[] = $history->id;
    }

    $response = find(["id" => implode(",", $idArray)]);

    foreach ($response->records() as $record) {
        if ($historyDates[$record->id]) $record->updated = $historyDates[$record->id];
    }

    return $response;
}

function tag($tag)
{
    $e = explode(":", $tag);
    $username = "";
    if(sizeof($e) == 2) {
        $tag = $e[1];
        $username = $e[0];
    }

    if(!$username) $username = username();
    if(!$username) return null;

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

    return find(["id" => implode(",", $idArray)]);
}

/**
 * Lookup search in the database.
 * @param string $query An arbitrary search string. Each word is treated as an independent search parameter.
 * @return Records
 */
function lookup($query) {
    $username = username();
    if (!$username) return null;

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

    return find(["id" => implode(",", $results)]);
}


function queryentries($filters = "", $username = "") {
    if(!$username) $username = username();
    if(!$username) return null;

    $db = mysqli(); // Load the database
    if($filters)
        $results = $db->query(sprintf("SELECT * FROM entries WHERE (%s) AND username = '%s'", $filters, $username));
    else
        $results = $db->query(sprintf("SELECT * FROM entries WHERE username = '%s'", $username));

    // Now we need to prepare a list of IDs for the records table.
    // When searching for a tag "Fluids" mySQL will also return "Holographic Fluids". We need to eliminate these.
    $entries = Array();
    $recordIDString = "";
    while ($entry = $results->fetch_object()) {
        $entries[$entry->id] = $entry;
        $recordIDString = $recordIDString . "OR id = '" . $entry->id . "' ";
    }

    $return = Array(); // Declare the array to be returned
    if ($recordIDString != "") {
        $recordIDString = substr($recordIDString, 3);
        $results = $db->query("SELECT * FROM records where $recordIDString");

        while ($record = $results->fetch_object()) {
            $return[$record->id] = (object)Array(
                "record" => $record,
                "entry" => $entries[$record->id]
            );
        }
    }

    return $return;
}