<?php

require_once 'File/MARCXML.php'; // File_MARCXML package for INSPIRE queries.
require_once "simplepie-1.5/autoloader.php"; // SimplePie package for arXiv queries.

class InspireRecords {
    /** @var InspireRecord[] */
    public $records = [];

    public $tot_results;

    function __construct($query, $xopts = null) {
        $results = RefSpires::fetch($query, $xopts);
        $this->records = Array();
        foreach($results->records as $record) {
            $this->records[] = new InspireRecord($record);
        }
        $this->tot_results = $results->tot_results;
    }
}

class InspireRecord {
    private $data;
    public $inspire, $bibkey, $arxiv, $title, $author, $abstract, $published, $doi, $temp;

    function __construct($query, $xopts = null) {
        if(is_string($query)) {
            $results = RefSpires::fetch($query, $xopts);
            $this->data = $results->records[0];
        } else {
            $this->data = $query;
        }

        foreach($this->data as $key => $value) {
            $this->$key = $value;
        }
    }
}

class ArxivRecord {
    private $data;
    public $arxiv, $arxiv_v, $title, $author, $abstract, $published, $doi, $temp;

    function __construct($query, $xopts = null) {
        $results = RefSpires::fetch_arxiv($query, $xopts);
        $this->data = $results->records[0];

        foreach($this->data as $key => $value) {
            $this->$key = $value;
        }
    }
}

class RefSpires
{
    static function fetch($query, $xopts = null) {
        if(!$xopts) $xopts = (object) Array();

        // Prepare the options
        $opts = (object) Array(
            "p" => $query . " and ac 1->10",
            "sf" => "earliestdate",
            "of" => "xm"
        );

        // Override with user provided options.
        if($xopts->sf) $opts->sf = $xopts->sf;
        if($xopts->so) $opts->so = $xopts->so;
        if($xopts->rg) $opts->rg = $xopts->rg;
        if($xopts->jrec) $opts->jrec = $xopts->jrec;

        if(!$xopts->fields) $xopts->fields = "arxiv,bibkey,title,author,date,doi";
        if($xopts->addFields) $xopts->fields = $xopts->fields . "," . $xopts->addFields;

        $otArray = ["909,500,773,690"]; // Collection and Public Notes
        $fieldArray = explode(",", $xopts->fields);
        foreach($fieldArray as $field) {
            switch(trim($field)) {
                case "bibkey":
                case "arxiv":
                    $otArray[] = "035";
                    break;
                case "title":
                    $otArray[] = "245";
                    break;
                case "author":
                case "authors":
                    $otArray[] = "100,700";
                    break;
                case "date":
                    $otArray[] = "269,260,502";
                    break;
                case "doi":
                    $otArray[] = "024";
                    break;
                case "abstract":
                    $otArray[] = "520";
                    break;
            }
        }
        $opts->ot = implode(",", $otArray);

        $query_url = http_build_query($opts);
        $xml = file_get_contents("https://inspirehep.net/search?" . $query_url); // Get the XML results

        $tot_results = explode("<!-- Search-Engine-Total-Number-Of-Results:", $xml, 2);
        $tot_results = explode("-->", $tot_results[1], "2");
        $tot_results = trim($tot_results[0]);

        $results = new File_MARCXML($xml, File_MARC::SOURCE_STRING);

        $records = Array();
        while ($result = $results->next()) {
            $record = (object) Array();

            if(!in_array(RefSpires::MARC($result, "909", "p"), array("INSPIRE:HEP","CERN"))) continue;

            // INSPIRE ID
            $record->inspire = RefSpires::MARC($result, "001");

            // arXiv ID and INSPIRE bibkey
            $rawArxiv = false;
            $rawInspire = false;
            $rawSpires = false;
            foreach ($result->getFields("035") as $subfields) {
                if ($subfields->getSubfield(9)->getData() == "arXiv" && $subfields->getSubfield("a")) {
                    $rawArxiv = $subfields->getSubfield("a")->getData();
                }
                if ($subfields->getSubfield(9)->getData() == "INSPIRETeX" && $subfields->getSubfield("a")) {
                    $rawInspire = $subfields->getSubfield("a")->getData();
                }
                if ($subfields->getSubfield(9)->getData() == "SPIRESTeX" && $subfields->getSubfield("a")) {
                    $rawSpires = $subfields->getSubfield("a")->getData();
                }
            }
            if ($rawArxiv) {
                $explode = explode(":", $rawArxiv);
                $record->arxiv = $explode[sizeof($explode) - 1];
            }
            if ($rawInspire) $record->bibkey = $rawInspire;
            elseif ($rawSpires) $record->bibkey = $rawSpires;
            if(!$record->bibkey) continue;

            // Title
            $title = trim(utf8_encode(RefSpires::MARC($result, "245", "a")));
            if($title) $record->title = $title;

            // Authors
            $e = explode(",", $result->getField("100")->getSubfield("a")->getData());
            $author = array();
            $author[0] = trim($e["1"] . " " . $e["0"]);
            if ($secauthors = $result->getFields("700")) {
                $author_counter = 1;
                foreach ($secauthors as $subfields) {
                    $e = explode(",", $subfields->getSubfield("a")->getData());
                    $author[$author_counter] = trim($e["1"] . " " . $e["0"]);
                    $author_counter++;
                }
            }
            if(sizeof($author) > 0) $record->author = utf8_encode(implode(", ", $author));

            // Abstract
            $abstract = utf8_encode(RefSpires::MARC($result, "520", "a"));
            if($abstract) $record->abstract = $abstract;

            // Date
            $date = RefSpires::MARC($result, "269", "c");
            if(!$date) $date = RefSpires::MARC($result, "260", "c");
            if(!$date) $date = RefSpires::MARC($result, "502", "d");
            if($date) {
                switch (sizeof(explode("-", $date))) {
                    case 1:
                        $record->published = $date . "-01-01";
                        break;
                    case 2:
                        $record->published = $date . "-01";
                        break;
                    default:
                        $record->published = $date;
                }
            }

            // DOI
            $doi = RefSpires::MARC($result, "024", "a");
            if($doi) $record->doi = $doi;

            // Temporary
            $temp = 1;
            if($date && floor((time()-strtotime($date))/(60*60*24*365)) > 3) $temp = 0;

            if($doi) $temp = 0; // if doi is present, mark it as permanent.

            $x = RefSpires::MARC($result, "260", "t");
            if($x && $x=="published") $temp = 0;

            $x = RefSpires::MARC($result, "690", "a");
            if($x && in_array($x, Array("Thesis", "Conference Paper", "Book"))) $temp = 0;

            $x = RefSpires::MARC($result, "773", "p");
            if($x) $temp = 0;

            // if explicitly temporary, set it to temporary
            foreach ($result->getFields("500") as $subfields) {
                if ($subfields->getSubfield("a")->getData() == "* Temporary entry *") {
                    $temp = 1;
                    break;
                }
            }
            $record->temp = $temp;

            // $this->reference_count = sizeof($INS->getFields("999"));

            $records[] = $record;
        }


        return (object) Array("records" => $records, "tot_results" => $tot_results);
    }

    static private function MARC($INS, $field, $subfield = NULL)
    {
        if ($fieldResults = $INS->getField($field)) {
            if (!$subfield)
                return trim($fieldResults->getData());
            else {
                if ($subfieldResults = $fieldResults->getSubfield($subfield))
                    return trim($fieldResults->getSubfield($subfield)->getData());
            }
        }
        return false;
    }

    static function fetch_arxiv($query, $xopts = null) {
        $url = 'http://export.arxiv.org/api/query';
        $feed = new SimplePie();
        $feed->set_feed_url($url . '?id_list=' . $query);
        $feed->set_cache_location("../.cache");
        $feed->init();
        $feed->handle_content_type();

        $arxiv_ns = 'http://arxiv.org/schemas/atom';

        $results = $feed->get_items();

        $records = [];
        foreach($results as $result) {
            $record = (object)Array();

            preprint($result);

            // arXiv ID and version
            $return = explode('/abs/', $result->get_id());
            $return = explode("v", $return[1]);
            $record->arxiv = $return[0];
            $record->arxiv_v = $return[1];

            // Title
            $record->title = trim(utf8_encode($result->get_title()));

            // Authors
            $return = Array();
            foreach ($result->get_authors() as $key => $author) {
                $return[$key] = $author->name;
            }
            $record->author = utf8_encode(implode(", ", $return));

            // Abstract
            $record->abstract = trim(utf8_encode($result->get_description()));

            // Date
            $date = DateTime::createFromFormat('j F Y, g:i a', $result->get_date());
            $record->published = $date->format('Y-m-d');

            // DOI
            $record->doi = null;
            $doi_raw = $result->get_item_tags($arxiv_ns,'doi');
            if ($doi_raw) {
                $record->doi = $doi_raw[0]['data'];
            }

            // Temporary
            $record->temp = 1;
            if(floor((time()-strtotime($record->date))/(60*60*24*365)) > 3) $record->temp = 0;
            if($record->doi) $record->temp = 0;

            $records[] = $record;
        }

        return (object) Array("records" => $records, "tot_results" => sizeof($records));
    }
}