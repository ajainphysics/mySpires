<?php

class mySpiresTags {

    static function tag_records($username = null) {
        if(!$username) $username = mySpiresUser::current_username();
        if(!$username) return false;

        if($query = mySpires::db()->prepare("SELECT tags, id FROM entries WHERE username = ? AND bin=0 ORDER BY id")) {
            $query->bind_param("s", $username);
            $query->execute();
            $entries = $query->get_result();

            $tag_records = [];
            while($entry = $entries->fetch_object()) {
                $tags = explode(",", self::cleanup_list($entry->tags));

                foreach($tags as $tag) {
                    if(!array_key_exists($tag, $tag_records)) $tag_records[$tag] = [];
                    array_push($tag_records[$tag], $entry->id);
                }
            }

            $return = $tag_records;
            foreach($tag_records as $tag => $ids) {
                $e = explode("/", $tag);
                $parent = $e[0];
                while($t = next($e)) {
                    if(!key_exists($parent, $return)) $return[$parent] = [];
                    $parent .= "/" . $t;
                }
            }

            ksort($return);

            return $return;
        }

        return [];
    }

    static function tag_list($username = null) {
        return array_keys(self::tag_records($username));
    }

    static function info($username = null) {
        if(!$username) $username = mySpiresUser::current_username();
        if(!$username) return [];

        $tags = self::tag_records($username);
        $id_tag_array = [];
        foreach($tags as $tag => $ids) {
            foreach($ids as $id) {
                if(!key_exists($id, $id_tag_array)) $id_tag_array[$id] = [];
                array_push($id_tag_array[$id], $tag);
            }
        }
        $id_array = array_keys($id_tag_array);

        array_walk($tags, function(&$val) {
            $val = (object)[
                "records" => $val,
                "description" => null,
                "starred" => 0,
                "shared" => 0,
                "visited" => null,
                "authors" => [],
                "surnames" => []
            ];
        });

        if($q = mySpires::db()->prepare("SELECT * FROM tags WHERE username = ?")) {
            $q->bind_param("s", $username);
            $q->execute();
            $results = $q->get_result();

            while($result = $results->fetch_object()) {
                if(array_key_exists($result->tag, $tags)) {
                    $tags[$result->tag]->description = $result->description;
                    $tags[$result->tag]->starred = $result->starred;
                    $tags[$result->tag]->shared = $result->shared;
                    $tags[$result->tag]->visited = $result->visited;
                }
            }
        }

        $questions = implode(",", array_map(function(){return "?";}, $id_array));
        $s = implode("", array_map(function(){return "s";}, $id_array));

        if($q = mySpires::db()->prepare("SELECT id, author FROM records WHERE id IN ({$questions})")) {
            $q->bind_param($s, ...$id_array);
            $q->execute();
            $results = $q->get_result();

            while($result = $results->fetch_object()) {
                $authors = array_map(function($a){
                    return trim(utf8_decode($a));
                }, explode(",", $result->author));

                foreach($id_tag_array[$result->id] as $tag) {
                    $tags[$tag]->authors = array_merge($tags[$tag]->authors, $authors);
                }
            }

            foreach($tags as $tag => $props) {
                $tags[$tag]->authors = array_values(array_unique($tags[$tag]->authors));

                usort($tags[$tag]->authors, function($a, $b) {
                    return strcmp(self::surname($a), self::surname($b));
                });

                $tags[$tag]->surnames = array_map(function($a) {
                    return self::surname($a);
                }, $tags[$tag]->authors);

                $tags[$tag]->surnames = array_values(array_unique($tags[$tag]->surnames));
            }

        }

        return $tags;
    }

    private static function surname($name) {
        $e = explode(" ", trim($name));
        return $e[sizeof($e) - 1];
    }

    static function cleanup($tag) {
        return preg_replace("/[,]+/", "", self::cleanup_list($tag));
    }

    static function cleanup_list($tag_list) {
        $tag_list = preg_replace("/[^a-zA-Z0-9 ,\-\/]+/", "", $tag_list);

        $tags = explode(",", $tag_list);
        $tags = array_map(function($t) {
            $e = explode("/", $t);
            $e = array_map(function($tt) {
                return implode(" ", array_filter(explode(" ", $tt)));
            }, $e);
            return implode("/", array_filter($e));
        }, $tags);

        $tags = array_unique(array_filter($tags));
        sort($tags);

        return implode(",", $tags);
    }

    static function exists($tag, $username = null) {
        $tag = self::cleanup($tag);

        $tag_list = self::tag_list($username);

        foreach($tag_list as $t) {
            if($t == $tag) return true;
            $e = explode($tag . "/", $t);
            if($e[0] == "" && $t != "") return true;
        }

        return false;
    }

}