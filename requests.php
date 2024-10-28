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

function http_post($url, $data, $headers = [])
{
    if (strpos($url, "://") === false)
        $url = "http://localhost$url";
    //$data = to_utf8($data);
    $data_string = json_encode($data);
    $headers_array = [];
    foreach (array_merge($headers, [
        'Content-Type' => 'application/json',
        'Content-Length' => strlen($data_string)])
             as $key => $value) {
        $headers_array[] = "$key: $value";
    }
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, POST);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers_array);
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


function assertEquals($message, $val, $need = 1)
{
    if ($val != $need)
        error("error $message need=$need val=" . json_encode($val));
}

function requestEquals($url, $params = [], $value_path = success, $need = 1)
{
    $response = http_post($url, $params);

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

    return true;
}

function requestCountEquals($url, $params, $value_path, $need)
{
    $response = http_post($url, $params);

    $val = $response;
    foreach (explode(".", $value_path) as $param)
        $val = $val[$param];

    $val = sizeof($val);

    if ($val !== $need)
        die("error $url $value_path=" . json_encode($val) . " need=$need\n" . json_encode($response));

    return $response;
}


function postWithGas($url, $params)
{
    $domain = get_required(gas_domain);
    $address = get_required(address);
    $password = get_required(password);
    $account = requestAccount($domain, $address);
    $key = tokenKey($domain, $address, $password, $account[prev_key]);
    $next_hash = tokenNextHash($domain, $address, $password, $key);
    requestEquals($url, array_merge($params, [
        gas_address => $GLOBALS[address],
        gas_pass => "$key:$next_hash",
    ]));
}