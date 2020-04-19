<?php
namespace library\tools;

function brace_block($str, $l = "{", $r = "}")
{
    $start_pos = strpos($str, $l);
    $end_pos = 0;
    $block = 0;
    $prev_char = null;
    foreach (str_split($str) as $pos => $char) {
        if ($char == $l && $prev_char != "\\") $block++;
        if ($char == $r && $prev_char != "\\") $block--;
        if ($block < 0) return false;
        if ($char == $r && $block == 0 && !$end_pos) {
            $end_pos = $pos;
            break;
        }
        $prev_char = $char;
    }
    if ($block != 0) return false;

    if ($start_pos === false) return (object)["pre" => $str, "block" => null, "post" => ""];

    return (object)[
        "pre" => substr($str, 0, $start_pos),
        "block" => substr($str, $start_pos + 1, $end_pos - $start_pos - 1),
        "post" => substr($str, $end_pos + 1)
    ];
}

function quote_block($str, $q = "\"")
{
    $start_pos = strpos($str, $q);
    $end_pos = 0;
    $prev_char = null;
    foreach (str_split($str) as $pos => $char) {
        if ($char == $q && $pos != $start_pos && $prev_char != "\\") {
            $end_pos = $pos;
            break;
        }
        $prev_char = $char;
    }

    if ($start_pos === false) return (object)["pre" => $str, "block" => null, "post" => ""];

    if ($start_pos == $end_pos) return false;

    return (object)[
        "pre" => substr($str, 0, $start_pos),
        "block" => substr($str, $start_pos + 1, $end_pos - $start_pos - 1),
        "post" => substr($str, $end_pos + 1)
    ];
}

function utf8_encode($d)
{
    if (is_array($d)) {
        foreach ($d as $k => $v) {
            $d[$k] = utf8_encode($v);
        }
    } else if (is_string($d)) {
        return utf8_encode($d);
    }
    return $d;
}

function safe_json_encode($d)
{
    return json_encode(utf8_encode($d));
}

function sanitize_filename($string)
{
    $strip = array("*", "\\", "|", ":", "\"", "&#8216;", "&#8217;", "&#8220;", "&#8221;", "&#8211;", "&#8212;",
        "â€”", "â€“", "<", ">", "/", "?");
    $clean = trim(str_replace($strip, "", strip_tags($string)));
    $clean = preg_replace('/\s+/', " ", $clean);
    return $clean;
}

/**
 * Uploads a file to the server.
 * @param string $url URL to download the file from.
 * @param string $destination Destination of the uploaded file on the server.
 */
function upload_file($url, $destination)
{

    $agent = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_3)";
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, $agent);
    curl_setopt($ch, CURLOPT_URL, $url);

    // return;

    $contents = curl_exec($ch);

    file_put_contents($destination, $contents);
    return;
}

function preprint($data)
{
    echo "<pre>";
    print_r($data);
    echo "</pre>";

    return true;
}

function null_populate(&$arr, $keys)
{
    foreach ($keys as $key) {
        if (!array_key_exists($key, $arr))
            $arr[$key] = null;
    }
}
