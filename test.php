<?php

include_once $_SERVER["DOCUMENT_ROOT"] . "/db/db.php";

$scalar = get("scalar");
if ($scalar != null)
    die(json_encode(scalar($scalar)));

function assertEquals($message, $val, $need)
{
    if ($val != $need)
        die("error $message need=$need val=" . json_encode($val));
    echo "good $message\n";
}

function assertNotEquals($message, $val, $need)
{
    if ($val == null)
        die("error $message need=$need val=" . json_encode($val));
    echo "good $message\n";
}


