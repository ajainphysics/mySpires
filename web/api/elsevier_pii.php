<?php

use function mySpires\config;

$query = $_GET["query"];
if(!$query) exit;

$apiKey = config("elsevier")->key;

$agent = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_3)";
$data = ["query" => $query, "apiKey" => $apiKey];
$query_url = "https://api.elsevier.com/content/search/sciencedirect?" . http_build_query($data);

$ch = curl_init(); // Bib request
curl_setopt($ch, CURLOPT_URL,$query_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
curl_setopt($ch, CURLOPT_USERAGENT, $agent);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Accept: application/json'
));
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
$json = json_decode(curl_exec($ch));

echo $json->{"search-results"}->entry[0]->{"prism:doi"};
