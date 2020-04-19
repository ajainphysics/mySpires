<?php
namespace mySpires\tags;

function cleanup($tag) {
    return preg_replace("/[,]+/", "", cleanup_list($tag));
}

function cleanup_list($tag_list) {
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