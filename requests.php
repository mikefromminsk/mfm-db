<?php
require_once $_SERVER["DOCUMENT_ROOT"] . "/mfm-db/params.php";

function to_utf8($mixed)
{
    if (is_array($mixed)) {
        foreach ($mixed as $key => $value)
            $mixed[$key] = to_utf8($value);
    } elseif (is_string($mixed)) {
        return mb_convert_encoding($mixed, 'UTF-8', 'ISO-8859-1');
    }
    return $mixed;
}

function getProtocol()
{
    if (isset($_SERVER['HTTPS']) &&
        ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1) ||
        isset($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
        $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
        return 'https';
    } else {
        return 'http';
    }
}


function http_post($url, $data)
{
    if (strpos($url, "://") === false)
        $url = "http://localhost$url";
    //$data = to_utf8($data);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, POST);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $result = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    if ($error != null)
        return $error;
    return is_string($result) ? json_decode($result, true) : $result;
}

function http_get($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, GET);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    if ($error != null)
        return $error;
    return is_string($result) ? json_decode($result, true) : $result;
}

function http_get_json($url)
{
    $result = http_get($url);
    return is_string($result) ? json_decode($result, true) : $result;
}

function requestEquals($url, $params = [], $value_path = success, $need = true)
{
    $response = http_post(":8014$url", $params);
    $val = $response;
    foreach (explode(".", $value_path) as $param)
        $val = $val[$param];
    if ($val != $need) {
        error("need [$value_path]==$need", [
            "url" => $url . "?" . implode("&", array_map(function ($key, $value) {
                    return "$key=$value";
                }, array_keys($params), $params)),
            "response" => $response
        ]);
    }
    return $response;
}
