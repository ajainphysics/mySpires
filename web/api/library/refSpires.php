<?php
namespace refSpires;

// require_once 'File/MARCXML.php'; // File_MARCXML package for INSPIRE queries.

use function mySpires\config;

require_once "simplepie-1.5/autoloader.php"; // SimplePie package for arXiv queries.

class RefRecords {
    /** @var RefRecord[] */
    public $records = [];

    public $tot_results;

    private $unique_fields = ["arxiv", "doi", "inspire", "bibkey", "ads"];

    function __construct($query = "", $xopts = null, $source = "inspire") {
        if(!$query) return null;

        if($source == "inspire")
            $results = RefSpires::fetch_inspire($query, $xopts);
        elseif($source == "ads")
            $results = RefSpires::fetch_ads($query, $xopts);
        else return null;

        if($results) {
            $this->add_records($results->records);
            $this->tot_results = $results->tot_results;
        }
    }

    function fetch($data) {
        if(gettype($data) == "array") $data = (object)$data;

        $map = [
            "inspire" => "recid",
            "bibkey"  => "texkey",
            "arxiv"   => "eprint",
            "doi"     => "doi"
        ];

        $queries = [];
        foreach ($map as $field => $db_field) {
            if(property_exists($data, $field) && $data->$field) {
                if(gettype($data->$field) == "string")
                    $data->$field = array_map('trim', explode(",", $data->$field));

                $queries[] = "(" . implode(" OR ", array_map(function ($val) use ($db_field) {
                        return $db_field . ":" . $val;
                    }, $data->$field)) . ")";
            }
        }

        if(sizeof($queries)) {
            $query = implode(" OR ", $queries);
            $results = RefSpires::fetch_inspire($query);

            $this->add_records($results->records);
        }

        $map = [
            "ads"     => "bibcode",
            "arxiv"   => "arXiv",
            "doi"     => "doi"
        ];

        $queries = [];
        foreach ($map as $field => $db_field) {
            if(property_exists($data, $field) && $data->$field) {
                if(gettype($data->$field) == "string")
                    $data->$field = array_map('trim', explode(",", $data->$field));

                $queries[] = "(" . implode(" OR ", array_map(function ($val) use ($db_field) {
                        return $db_field . ":\"" . $val . "\"";
                    }, $data->$field)) . ")";
            }
        }
        if(sizeof($queries)) {
            $query = implode(" OR ", $queries);
            $results = RefSpires::fetch_ads($query);

            $this->add_records($results->records);
        }

        return true;
    }

    private function add_records($new_records) {
        $old_records = $this->records;

        foreach ($new_records as $new_record) {
            $new = true;
            foreach ($this->unique_fields as $field) {
                if(!$new_record->$field) continue;
                foreach ($old_records as $old_record) {
                    if ($old_record->$field == $new_record->$field) {
                        $new = false;
                        break 2;
                    }
                }
            }
            if ($new) $this->records[] = $new_record;
        }
    }
}

class RefRecord {
    private $properties = [
        "inspire", "bibkey", "ads", "arxiv", "arxiv_v", "doi",
        "title", "author", "author_id", "abstract", "published", "bibtex", "temp", "source"];

    public $inspire, $bibkey, $ads, $arxiv, $arxiv_v, $doi,
        $title, $author, $author_id, $abstract, $published, $bibtex, $temp;

    public $populated; // Flag for populated

    function __construct($q, $field = null) {
        $results = null;

        if(gettype($q) == "array") $q = (object)$q;

        if(gettype($q) == "object") {
            $results = (object)["records" => [$q]];
        }
        elseif($field == "inspire") {
            $results = RefSpires::fetch_inspire("recid:" . $q, (object)["size" => 1]);
        }
        elseif($field == "ads") {
            $results = RefSpires::fetch_ads("bibcode:" . $q, (object)["rows" => 1]);
        }
        elseif($field == "arxiv") {
            $results = RefSpires::fetch_inspire("arxiv:" . $q, (object)["size" => 1]);
            if(!$results)
                $results = RefSpires::fetch_ads("arXiv:" . $q, (object)["rows" => 1]);
            if(!$results)
                $results = RefSpires::fetch_arxiv($q);
        }
        elseif($field == "doi") {
            $results = RefSpires::fetch_inspire("doi:" . $q, (object)["size" => 1]);
            if(!$results)
                $results = RefSpires::fetch_ads("doi:" . $q, (object)["rows" => 1]);
        }
        elseif($field == "bibkey") {
            $results = RefSpires::fetch_inspire("texkey:" . $q, (object)["size" => 1]);
        }

        if($results) {
            $data = $results->records[0];
            foreach ($this->properties as $property) {
                if(property_exists($data, $property))
                    $this->$property = $data->$property;
            }
            $this->populated = true;
        }
    }
}

class RefSpires {
    static function fetch_inspire($query, $xopts = null) {
        if (!$xopts) $xopts = (object)Array();

        $opts = (object) Array(
            "q" => $query,
            // "author_count"=> "10 authors or less",
            "sort" => "mostrecent",
            "size" => "500",
            "page" => "1"
        );

        // Override with user provided options.
        if(property_exists($xopts,"sort")) $opts->sort = $xopts->sort;
        if(property_exists($xopts,"size")) $opts->size = $xopts->size;
        if(property_exists($xopts,"page")) $opts->page = $xopts->page;
        if(property_exists($xopts,"doc_type")) $opts->doc_type = $xopts->doc_type;

        $query_url = "https://inspirehep.net/api/literature?" . http_build_query($opts);
        $agent = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_3)";

        // cURL requests
        $mh = curl_multi_init(); // Create a multi-cURL

        $jsonReq = curl_init(); // JSON request
        curl_setopt($jsonReq, CURLOPT_URL,$query_url);
        curl_setopt($jsonReq, CURLOPT_RETURNTRANSFER,1);
        curl_setopt($jsonReq, CURLOPT_USERAGENT, $agent);
        curl_multi_add_handle($mh, $jsonReq);

        $bibReq = curl_init(); // Bib request
        curl_setopt($bibReq, CURLOPT_URL,$query_url);
        curl_setopt($bibReq, CURLOPT_RETURNTRANSFER,1);
        curl_setopt($bibReq, CURLOPT_USERAGENT, $agent);
        curl_setopt($bibReq, CURLOPT_HTTPHEADER, array(
            'Accept: application/x-bibtex'
        ));
        curl_multi_add_handle($mh, $bibReq);

        $index=null; // Execute requests
        do {
            curl_multi_exec($mh,$index);
        } while($index > 0);
        $json = json_decode(curl_multi_getcontent($jsonReq));
        curl_multi_remove_handle($mh, $jsonReq);
        $bibRaw = curl_multi_getcontent($bibReq);
        curl_multi_remove_handle($mh, $bibReq);

        curl_multi_close($mh); // close

        $return = (object)Array("records" => [], "tot_results" => 0);

        // Handle JSON
        if(!$json) return $return;
        $return->tot_results = $json->hits->total;
        $results = $json->hits->hits;

        if($return->tot_results == 0) return $return;

        // Create BibTeX array
        $e = explode("}\n\n@", $bibRaw);
        $bibArray = array();
        foreach($e as $i => $bib) {
            if($i>0) $bib = "@".$bib;
            if($i<sizeof($e)-1) $bib = $bib."}";
            $e2 = explode("{", $bib);
            $e2 = explode(",", $e2[1]);
            $key = $e2[0];
            $bibArray[$key] = $bib;
        }

        // Handle JSON
        foreach($results as $result) {
            $record = (object) Array();
            $record->temp = 1;

            // INSPIRE
            $record->inspire = $result->id;

            $data = $result->metadata;

            // BibKey
            $record->bibkey = $data->texkeys[0];
            if(!$record->bibkey) continue;

            // ArXiv
            if(property_exists($data,"arxiv_eprints"))
                $record->arxiv = $data->arxiv_eprints[0]->value;

            // Title
            $record->title = $data->titles[0]->title;

            // Authors
            $author = Array();
            $author_id = Array();
            $author_counter = 0;
            foreach($data->authors as $a) {
                $e = explode(",", $a->full_name);
                $author_name = utf8_encode(trim($e["1"] . " " . $e["0"]));
                $author[$author_counter] = $author_name;
                foreach($a->ids as $id) {
                    if($id->schema == "INSPIRE BAI")
                        $author_id[$author_counter] = $id->value;
                }
                $author_counter++;
            }
            if(sizeof($author) > 0) {
                $record->author = implode(", ", $author);
                $record->author_id = implode(", ", $author_id);
            }

            // Date
            $date = $data->earliest_date;
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
            if(property_exists($data,"dois")) {
                $record->doi = $data->dois[0]->value;
                $record->temp = 0;
            }

            // Abstract
            if(property_exists($data, "abstracts"))
                $record->abstract = $data->abstracts[0]->value;

            // BibTeX
            $record->bibtex = $bibArray[$record->bibkey];

            // Temporary
            if($date && floor((time()-strtotime($date))/(60*60*24*365)) > 3) $record->temp = 0;

            $record->source = "inspire";

            $return->records[] = new RefRecord($record);
        }

        return $return;
    }

    static function fetch_ads($q, $xopts = null) {
        if (!$xopts) $xopts = (object)Array();

        $opts = (object) Array(
            // "q" => $q . " author_count:[1 TO 10]",
            "q" => $q,
            "rows" => "500",
            "start" => "0",
            "fl" => "title,author,bibcode,identifier,pubdate,doi,abstract"
        );

        // Override with user provided options.
        foreach (["rows","start","fl","sort","author_count"] as $i) {
            if(property_exists($xopts,$i)) $opts->$i = $xopts->$i;
        }

        $return = (object) Array("records" => [], "tot_results" => 0);

        // Get API configuration
        $config = config("nasa_ads");

        $agent = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_3)";
        $url = $config->url;

        // First cURL request for records
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_USERAGENT, $agent);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $config->token
        ]);
        curl_setopt($ch, CURLOPT_URL,
            $url . "search/query" . "?" . http_build_query($opts));
        $out = json_decode(curl_exec($ch));
        if(!$out) return $return;

        $results = $out->response->docs;
        $return->tot_results = $out->response->numFound;
        if($return->tot_results == 0) return $return;

        // Compile all the ads bibcodes
        $ads_keys = Array();
        foreach($results as $result) $ads_keys[] = $result->bibcode;

        // Second cURL request for BibTeX
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_USERAGENT, $agent);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $config->token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_URL,$url . "export/bibtex");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["bibcode" => array_values($ads_keys)]));
        $out = json_decode(curl_exec($ch));
        $bib_array_raw = explode("\n\n",  $out->export);

        $bib_array = Array();
        foreach($bib_array_raw as $bibtex) {
            $e = explode("{", $bibtex);
            if(sizeof($e)>1) {
                $e = explode(",", $e[1]);
                if ($bibtex && in_array($e[0], $ads_keys)) {
                    $bib_array[$e[0]] = $bibtex;
                }
            }
        }

        foreach($results as $result) {
            $record = (object) Array();
            $record->temp = 1;

            // ADS
            $record->ads = $result->bibcode;
            if(!$record->ads) continue;

            // ArXiv
            $arxiv = null;
            foreach($result->identifier as $i) {
                $e = explode("arXiv:", $i);
                if (sizeof($e) == 2 && $e[1]) {
                    $arxiv = $e[1];
                    break;
                }
            }
            if($arxiv) $record->arxiv = $arxiv;

            // Title
            $record->title = $result->title[0];

            // Authors
            $author = Array();
            $author_counter = 0;
            foreach($result->author as $a) {
                $e = explode(",", $a);
                if(sizeof($e)>1)
                    $author_name = utf8_encode(trim($e["1"] . " " . $e["0"]));
                else
                    $author_name = utf8_encode(trim($e["0"]));
                $author[$author_counter] = $author_name;
                $author_counter++;
            }
            if(sizeof($author) > 0) {
                $record->author = implode(", ", $author);
            }

            // Date
            if($result->pubdate) {
                $e = explode("-", $result->pubdate);
                if($e[0] == "0000") $e[0] = "0001";
                if($e[1] == "00") $e[1] = "01";
                if($e[2] == "00") $e[2] = "01";
                $record->published = implode("-",$e);
            }


            // DOI
            if(property_exists($result,"doi")) {
                $record->doi = $result->doi[0];
                $record->temp = 0;
            }

            // Abstract
            if(property_exists($result, "abstract"))
                $record->abstract = $result->abstract;

            // BibTeX
            $record->bibtex = $bib_array[$record->ads];

            // Temporary
            if($record->published && floor((time()-strtotime($record->published))/(60*60*24*365)) > 3)
                $record->temp = 0;

            $record->source = "ads";

            $return->records[] = new RefRecord($record);
        }

        return $return;
    }

    /*
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
        $xml = file_get_contents("https://old.inspirehep.net/search?" . $query_url); // Get the XML results

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
    */

    /*
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
    */

    static function fetch_arxiv($query) {
        $url = 'http://export.arxiv.org/api/query';
        $feed = new \SimplePie();
        $feed->set_feed_url($url . '?id_list=' . $query);
        $feed->set_cache_location("../.cache");
        $feed->init();
        $feed->handle_content_type();

        $arxiv_ns = 'http://arxiv.org/schemas/atom';

        $results = $feed->get_items();

        $return = (object)Array("records" => [], "tot_results" => 0);

        foreach($results as $result) {
            $record = (object)Array();

            // arXiv ID and version
            $return_id = explode('/abs/', $result->get_id());
            $return_id = explode("v", $return_id[1]);
            $record->arxiv = $return_id[0];
            $record->arxiv_v = $return_id[1];

            // Title
            $record->title = trim(utf8_encode($result->get_title()));

            // Authors
            $return_author = Array();
            foreach ($result->get_authors() as $key => $author) {
                $return_author[$key] = $author->name;
            }
            $record->author = utf8_encode(implode(", ", $return_author));

            // Abstract
            $record->abstract = trim(utf8_encode($result->get_description()));

            // Date
            $date = \DateTime::createFromFormat('j F Y, g:i a', $result->get_date());
            $record->published = $date->format('Y-m-d');

            // DOI
            $record->doi = null;
            $doi_raw = $result->get_item_tags($arxiv_ns,'doi');
            if ($doi_raw) {
                $record->doi = $doi_raw[0]['data'];
            }

            // Temporary
            $record->temp = 1;
            if(floor((time()-strtotime($record->published))/(60*60*24*365)) > 3) $record->temp = 0;
            if($record->doi) $record->temp = 0;

            $record->source = "arxiv";

            $return->records[] = new RefRecord($record);
        }

        return $return;
    }
}

/*
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
*/