<?php

include_once $_SERVER["DOCUMENT_ROOT"] . "/db/db.php";

$scalar = get_string("scalar");
if ($scalar != null)
    die(json_encode(scalar($scalar)));

function assertEquals($message, $val, $need)
{
    if ($val !== $need)
        die("error $message need=$need val=" . json_encode($val));
    echo "good $message\n";
}

function assertNotEquals($message, $val, $need)
{
    if ($val == null)
        die("error $message need=$need val=" . json_encode($val));
    echo "good $message\n";
}

function requestEquals($url, $params, $value_path, $need)
{
    $response = http_post_json($url, $params);

    $val = $response;
    foreach (explode(".", $value_path) as $param)
        $val = $val[$param];

    if ($val !== $need)
        die("error $url $value_path=" . json_encode($val) . " need=$need\n" . json_encode($response));
    echo "good $url\n";

    return $response;
}


function requestNotEquals($url, $params, $value_path, $need)
{
    $response = http_post_json($url, $params);

    $val = $response;
    foreach (explode(".", $value_path) as $param)
        $val = $val[$param];

    if ($val === $need)
        die("error $url $value_path=" . json_encode($val) . " need=$need\n" . json_encode($response));
    echo "good $url\n";

    return $response;
}

function requestCountEquals($url, $params, $value_path, $need)
{
    $response = http_post_json($url, $params);

    $val = $response;
    foreach (explode(".", $value_path) as $param)
        $val = $val[$param];

    $val = sizeof($val);

    if ($val !== $need)
        die("error $url $value_path=" . json_encode($val) . " need=$need\n" . json_encode($response));
    echo "good $url\n";

    return $response;
}