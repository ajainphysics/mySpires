<?php
namespace library\bibtex;
require_once(__DIR__ . "/../library/tools.php");

use function library\tools\brace_block;
use function library\tools\quote_block;

class BibTeX {
    public $records = [];
    public $strings = [];
    public $preambles = [];
    public $comments = [];
    public $loaded = false;

    function __construct($bibtex) {
        // Clean up comments and empty lines
        $lines = explode("\n", $bibtex);
        $lines = array_map("trim", array_filter($lines, function($line) {
            $line = trim($line);
            return !(!$line || $line[0] == "%");
        }));
        $bibtex = join(" ", $lines);

        // I am going to loop over blocks of @type{}
        $rbibtex = $bibtex;
        while($rbibtex) {
            // Find the block
            $seg = brace_block($rbibtex);
            if(!$seg || !$seg->pre) return false; // Return if error or no type found

            // Find type
            $e = explode('@', trim($seg->pre));
            if(trim($e[0]) != "" || sizeof($e) != 2 || trim($e[1]) == "")
                return false; // Return if no type found or no body found

            $type = strtolower(trim($e[1])); // Type
            if(in_array($type, ["string", "preamble", "comment"])) {
                $this->{$type . "s"}[] = $seg->block;
                continue; // Ignore special blocks
            }

            $bib = new bibtex_record($rbibtex);
            if(!$bib->loaded) return false;

            $this->records[] = $bib;

            $rbibtex = trim($seg->post); // Remaining bibtex
        }

        $this->loaded = true;
        return true;
    }
}

class BibTeX_Record {
    public $type;
    public $key;
    public $fields = [];
    public $loaded = false;

    private $req_fields = [
        "article"       => [["author"], ["title"], ["journal"], ["year"], ["volume"]],
        "book"          => [["author", "editor"], ["title"], ["publisher"], ["year"]],
        "booklet"       => [["title"]],
        "conference"    => [["author"], ["title"], ["booktitle"], ["year"]],
        "inbook"        => [["author", "editor"], ["title"], ["chapter", "pages"], ["publisher"], ["year"]],
        "incollection"  => [["author"], ["title"], ["booktitle"], ["publisher"], ["year"]],
        "inproceedings" => [["author"], ["title"], ["booktitle"], ["year"]],
        "manual"        => [["title"]],
        "mastersthesis" => [["author"], ["title"], ["school"], ["year"]],
        "phdthesis"     => [["author"], ["title"], ["school"], ["year"]],
        "proceedings"   => [["title"], ["year"]],
        "techreport"    => [["author"], ["title"], ["institution"], ["year"]],
        "unpublished"   => [["author"], ["title"], ["note"]]
    ];

    function __construct($record) {
        // Find the block
        $seg = brace_block($record);
        // Return if error or no type found
        if(!$seg || !trim($seg->pre)) return false;

        // Content outside the block is ignored
        // trim($seg->post)

        // Find type
        $e = explode('@', trim($seg->pre));
        if(trim($e[0]) != "" || sizeof($e) != 2 || trim($e[1]) == "")
            return false; // Return if no type found or no body found

        $this->type = strtolower(trim($e[1])); // Type
        if(in_array($this->type, ["string", "preamble", "comment"]))
            return false; // Ignore special blocks

        // Decompose to find key
        $e = explode(",", $seg->block, 2);
        if(trim($e[0]) == "" || sizeof($e) != 2)
            return false; // Return if no key found
        $this->key = trim($e[0]); // Key

        //  I am going to loop over all the data
        $rdata = trim($e[1],", \t\n\r\0\x0B"); // Trim away extra commas and spaces
        if(!$rdata) return false; // Return if no data found

        while($rdata) {
            // Decompose to find the field
            $e = explode("=", $rdata, 2);
            if(trim($e[0]) == "" || sizeof($e) != 2)
                return false; // Return if no property found

            $prop = trim($e[0]); // Property
            $rdata = trim($e[1]);
            if($rdata && $rdata[0] == "{") { // Brace mode
                $data_seg = brace_block($rdata);
                if(!$data_seg) return false;
                $this->fields[$prop] = "{" . trim($data_seg->block) . "}";

                // Send back the remaining data
                $rdata = trim($data_seg->post);
                if($rdata && $rdata[0] == ",")
                    $rdata = ltrim($rdata,", \t\n\r\0\x0B");
                elseif($rdata)
                    return false; // Return if data remains but not separated by a comma
            }
            else { // Quote or free mode
                $value = "";
                $eos = false; // Detected by comma or empty
                while($eos == false) {
                    $value_seg = quote_block($rdata);
                    $pre_e = explode(",", $value_seg->pre, 2);

                    $h = array_filter(array_map('trim', explode('#', $pre_e[0])));
                    $value.=  implode(" # ", $h);

                    if(sizeof($pre_e) != 1) { // If comma found in pre-quote
                        $eos = true;
                        $rdata = substr($rdata,strpos($rdata, ",")+1);
                    }
                    elseif($value_seg->pre == $rdata) { // If no quotation block
                        $eos = true;
                        $rdata = "";
                    }
                    else {
                        if(sizeof($h)) { // If pre-quote found
                            if(substr(trim($value_seg->pre), -1) != "#") return false; // Return if last character was not a hash
                            $value.= " # ";
                        }
                        $value.= "\"" . $value_seg->block . "\"";

                        $trimmed_post = ltrim($value_seg->post, "# \t\n\r\0\x0B"); // Strip away trailing hashes
                        if(!$trimmed_post || $trimmed_post[0] == ",") { // If after-stripped version finishes with comma
                            $eos = true;
                            $rdata = $trimmed_post;
                        }
                        elseif(trim($value_seg->post)[0] == "#") {
                            $value.= " # ";
                            $rdata = $trimmed_post;
                        }
                        else return false;
                    }
                }

                if(!$value) $value = "\"\"";
                $this->fields[$prop] = $value;
            }

            $rdata = ltrim($rdata,", \t\n\r\0\x0B");
        }

        if(array_key_exists($this->type, $this->req_fields)) {
            foreach($this->req_fields[$this->type] as $req) {
                $found = false;
                $fields = array_map('strtolower', array_keys($this->fields));
                foreach($req as $field) {
                    if(in_array($field, $fields)) $found = true;
                }
                if(!$found) $this->fields[$req[0]] = "\"\"";
            }
        }

        $this->loaded = true;
        return true;
    }

    function bibtex() {
        if(!$this->loaded) return null;

        $bib = "@" . $this->type . "{" . $this->key . ",\n";
        $bib.= implode(",\n", array_map(function($prop, $val) {
            return "    " . $prop . " = " . $val;
        }, array_keys($this->fields), $this->fields));
        $bib.= "\n}";

        return $bib;
    }

    function field($q) {
        foreach ($this->fields as $field => $value) {
            if(strtolower($q) == strtolower($field)) {
                return rtrim(ltrim($value,"{\" \t\n\r\0\x0B"), "}\" \t\n\r\0\x0B");
            }
        }
        return null;
    }
}