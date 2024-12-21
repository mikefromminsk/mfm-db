<?php
require_once $_SERVER["DOCUMENT_ROOT"] . "/mfm-db/params.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/mfm-db/requests";

$db_name = get_config_required(db_name);
$db_user = get_config_required(db_user);
$db_pass = get_config_required(db_pass);

$mysql_conn = $GLOBALS[conn];
if ($mysql_conn == null)
    $mysql_conn = new mysqli(localhost, $db_user, $db_pass, $db_name); // change localhost to $host_name

if ($mysql_conn->connect_error)
    error("Connection failed: " . $mysql_conn->connect_error);

unset($db_name);
unset($db_user);
unset($db_pass);

$mysql_conn->set_charset("utf8");
$mysql_conn->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, true);
$GLOBALS[conn] = $mysql_conn;

// delete all usages of query !!!! rename to query without delete
function query($sql, $show_query = false)
{
    if ($show_query)
        error($sql);
    $success = false;
    if (!isHelp()) {
        $success = $GLOBALS[conn]->query($sql);
        if (!$success)
            error(mysqli_error($GLOBALS[conn]));
    }
    return $success;
}

function select($sql, $show_query = false)
{
    $result = query($sql, $show_query);
    if ($result->num_rows > 0) {
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        return $rows;
    }
    return null;
}

function scalar($sql, $show_query = false)
{
    $rows = select($sql, $show_query);
    if (count($rows) > 0)
        return array_shift($rows[0]);
    else
        return null;
}

function selectMapList($sql, $column, $show_query = false)
{
    $table = select($sql, $show_query);
    $res = [];
    foreach ($table as $row)
        $res[$row[$column]][] = $row;
    return $res;
}

function selectList($sql, $show_query = false)
{
    $result = query($sql, $show_query);
    if ($result->num_rows > 0) {
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = array_shift($row);
        }
        return $rows;
    }
    return null;
}

function selectListWhere($table, $column, $where, $show_query = false)
{
    $select = selectWhere($table, $where, $show_query); //!!! TODO optimize
    $rows = [];
    foreach ($select as $row)
        $rows[] = $row[$column];
    return $rows;
}

function selectRow($sql, $show_query = false)
{
    $result = select($sql, $show_query);
    if ($result != null)
        return $result[0];
    return null;
}

function arrayToWhere($where)
{
    if ($where == null || sizeof($where) == 0) return "";
    $sql = " where ";
    foreach ($where as $param_name => $param_value)
        $sql .= is_double($param_name) ? $param_value :
            ("`$param_name`" . (is_null($param_value) ? " is null" : " = " . (is_double($param_value) ? $param_value : "'" . uencode($param_value) . "'"))) . " and ";
    return rtrim($sql, " and ");
}

function scalarWhere($table, $field, $where, $show_query = false)
{
    return scalar("select $field from `$table` " . arrayToWhere($where), $show_query);
}

function selectWhere($table, $where, $show_query = false)
{
    return select("select * from `$table` " . arrayToWhere($where), $show_query);
}

function selectRowWhere($table, $where, $show_query = false)
{
    return selectRow("select * from `$table` " . arrayToWhere($where), $show_query);
}

function table_exist($table_name)
{
    return scalar("show tables like '$table_name'") != null;
}

function array_to_map($array, $key)
{
    $map = [];
    foreach ($array as $item)
        $map[$item[$key]] = $item;
    return $map;
}

function array_to_map_array($array, $key)
{
    $map = [];
    foreach ($array as $item)
        $map[$item[$key]][] = $item;
    return $map;
}

function uencode($param_value)
{
    return mysqli_real_escape_string($GLOBALS[conn], $param_value);
}

function insert($sql, $show_query = null)
{
    return query($sql, $show_query);
}

function get_last_insert_id()
{
    return mysqli_insert_id($GLOBALS[conn]);
}

function update($sql, $show_query = null)
{
    query($sql, $show_query);
    return $GLOBALS[conn]->affected_rows > 0;
}

//rename to insertMap
function insertRow($table_name, $params, $show_query = false)
{
    $insert_params = "";
    foreach ($params as $param_name => $param_value)
        $insert_params .= (is_double($param_value) ? $param_value : (is_null($param_value) ? "null" : "'" . uencode($param_value) . "'")) . ", ";
    $insert_params = rtrim($insert_params, ", "); // !!! CHAR LSIT
    return insert("insert into `$table_name` (`" . implode("`,`", array_keys($params)) . "`) values ($insert_params)", $show_query);
}

function insertRowAndGetId($table_name, $params, $show_query = false)
{
    $success = insertRow($table_name, $params, $show_query);
    if ($success)
        return get_last_insert_id();
    return null;
}

function updateWhere($table_name, $set_params, $where, $show_query = false)
{
    $set_params_string = "";
    foreach ($set_params as $param_name => $param_value)
        $set_params_string .= (is_double($param_name) ? $param_value : " `$param_name` = " . (is_numeric($param_value) ? $param_value : (is_null($param_value) ? "null" : "'" . uencode($param_value) . "'"))) . ", ";
    $set_params_string = rtrim($set_params_string, ", "); // !!! CHAR LSIT
    return update("update `$table_name` set $set_params_string " . arrayToWhere($where), $show_query);
}

function object_properties_to_number(&$object)
{
    if (is_object($object) || is_array($object))
        foreach ($object as &$property)
            object_properties_to_number($property);
    if (is_string($object) && is_double($object))
        $object = doubleval($object);
}


function random_id($length = 9)
{
    //max mysql int = 20 chars
    //max mysql bigint = 20 chars
    //max js int = 16 chars
    //max php double without E = 12 chars
    $random_long = mt_rand(1, 9);
    for ($i = 0; $i < $length - 1; $i++)
        $random_long .= mt_rand(0, 9);
    return doubleval($random_long);
}

function random_key($table_name, $column_name, $length = 9)
{
    do {
        $random_key_id = random_id($length);
        $key_exist = scalar("select count(*) from `$table_name` where $column_name = $random_key_id");
    } while ($key_exist != 0);
    return $random_key_id;
}

function str_between($string, $start, $end)
{
    $string = ' ' . $string;
    $ini = strpos($string, $start);
    if ($ini == 0) return '';
    $ini += strlen($start);
    $len = strpos($string, $end, $ini) - $ini;
    return substr($string, $ini, $len);
}

function showResponse($response = [])
{
    if ($response == null)
        $response = [];
    if ($response[success] == null)
        $response[success] = true;
    echo json_encode($response, JSON_PRETTY_PRINT);
    die();
}

function commit($response = [])
{
    if (function_exists(commitData)) commitData();
    if (function_exists(commitTokens)) commitTokens();
    if (function_exists(commitAnalytics)) commitAnalytics();
    showResponse($response);
}

function str_starts_with($haystack, $needle)
{
    return substr($haystack, 0, strlen($needle)) === $needle;
}