<?php

$files = scandir(__DIR__);

$updates = [];
$latest_version = "0.0.0";
$latest_file = "";
foreach($files as $file) {
    $e = explode("myspires-", $file);
    if(sizeof($e) == 2) {
        $e = explode("-", $e[1]);
        $version = $e[0];

        array_push($updates, [
            "version" => $version,
            "update_link" => 'https://myspires.ajainphysics.com/bin/' . $file
        ]);

        if(version_compare($version, $latest_version) == 1) {
            $latest_version = $version;
            $latest_file = $file;
        }
    }
}

$updates = ["addons" => [
    "{0971bf29-05ec-47da-874e-b4c3c719bd4a}" => [
        "updates" => $updates
    ]
]];

file_put_contents(__DIR__ . "/updates.json",
    json_encode($updates, JSON_UNESCAPED_SLASHES));

header("Location: https://myspires.ajainphysics.com/bin/" . $latest_file);