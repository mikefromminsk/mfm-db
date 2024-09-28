<?php
error_reporting(1);

header("Content-type: application/json;charset=utf-8");

require_once $_SERVER["DOCUMENT_ROOT"] . "/db/properties.php";

// move create mysqli into properties.php
if ($db_name == null || $db_user == null || $db_pass == null)
    die(json_encode(array("message" => "Create properties.php with database connection parameters")));

$mysql_conn = isset($GLOBALS["conn"]) ? $GLOBALS["conn"] : null;
if ($mysql_conn == null)
    $mysql_conn = new mysqli("localhost", $db_user, $db_pass, $db_name); // change localhost to $host_name

if ($mysql_conn->connect_error)
    die("Connection failed: " . $mysql_conn->connect_error . " check properties.php file");

$mysql_conn->set_charset("utf8");
$mysql_conn->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, true);
$GLOBALS["conn"] = $mysql_conn;

$host_name = $host_name ?: $_SERVER['HTTP_HOST'];

// TODO  is_numeric =>      is_numeric($result) && !is_string($result)

if (isset($_SERVER["CONTENT_TYPE"]) && $_SERVER["CONTENT_TYPE"] != 'application/x-www-form-urlencoded'
    && ($_SERVER['REQUEST_METHOD'] == 'POST' || $_SERVER['REQUEST_METHOD'] == 'PUT')) {
    $inputJSON = file_get_contents('php://input');
    $inputParams = json_decode($inputJSON, true);
    //file_put_contents("sef", $inputParams);
    foreach ($inputParams as $key => $value)
        $_POST[$key] = $value;
}

// delete all usages of query !!!! rename to query without delete
function query($sql, $show_query = false)
{
    if ($show_query)
        error($sql);
    $success = false;
    if (!isHelp()) {
        $success = $GLOBALS["conn"]->query($sql);
        if (!$success)
            error(mysqli_error($GLOBALS["conn"]));
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

function error($error_message, $data = null)
{
    $result["message"] = $error_message;
    if ($data != null)
        $result = array_merge($result, $data);
    if (DEBUG) {
        $stack = generateCallTrace();
        if ($stack != null)
            $result["stack"] = $stack;
    }
    http_response_code(500);
    die(json_encode_readable($result));
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
    return mysqli_real_escape_string($GLOBALS["conn"], $param_value);
}

function isHelp()
{
    return isset($_GET["help"]) || isset($_POST["help"]);
}

function get($param_name, $default, $description)
{
    if (isHelp()) {
        $GLOBALS["params"][$param_name]["name"] = $param_name;
        $GLOBALS["params"][$param_name]["type"] = "string";
        $GLOBALS["params"][$param_name]["required"] = false;
        $GLOBALS["params"][$param_name]["default"] = $default;
        $GLOBALS["params"][$param_name]["description"] = $description;
    }
    // TODO add demo on sql
    $param_value = null;
    if (isset($_GET[$param_name]))
        $param_value = $_GET[$param_name];
    if ($param_value === null && isset($_POST[$param_name]))
        $param_value = $_POST[$param_name];
    if ($param_value === null && isset($_SESSION[$param_name]))
        $param_value = $_SESSION[$param_name];
    if ($param_value === null && isset($GLOBALS[$param_name]))
        $param_value = $GLOBALS[$param_name];
    if ($param_value === null && isset($_COOKIE[$param_name]))
        $param_value = $_COOKIE[$param_name];
    if ($param_value === null && isset($_FILES[$param_name]))
        $param_value = $_FILES[$param_name];
    if ($param_value === null && isset(getallheaders()[$param_name]))
        $param_value = getallheaders()[$param_name];
    if ($param_value === null) {
        $inputJSON = file_get_contents('php://input');
        if ($inputJSON != null) {
            $inputParams = json_decode($inputJSON, true);
            if ($inputParams !== null) {
                $keys = explode('/', $param_name);
                $param_value = $inputParams;
                foreach ($keys as $key) {
                    if (isset($param_value[$key])) {
                        $param_value = $param_value[$key];
                    } else {
                        $param_value = null;
                        break;
                    }
                }
            }
        }
    }
    if ($param_value === null)
        return $default;
    return $param_value;
}

function get_string($param_name, $default = null, $description = null)
{
    $param_value = get($param_name, $default, $description);
    if (isHelp()) {
        $GLOBALS["params"][$param_name]["type"] = "string";
    }
    return $param_value;
}

function get_int($param_name, $default = null, $description = null)
{
    $param_value = get($param_name, $default, $description);
    if (isHelp()) {
        $GLOBALS["params"][$param_name]["type"] = "int";
        return null;
    } else {
        if ($param_value == null)
            return null;
        if (!is_numeric($param_value))
            error("$param_name must be int");
        return doubleval($param_value);
    }
}

function get_int_array($param_name, $default = null, $description = null)
{
    if (isHelp())
        $GLOBALS["params"][$param_name]["type"] = "int_array";
    $arr = get($param_name, $default, $description);
    return $arr != null ? explode(",", $arr) : null;
}

function get_required($param_name, $default = null, $description = null)
{
    $param_value = get($param_name, $default, $description);
    if (isHelp()) {
        $GLOBALS["params"][$param_name]["required"] = true;
        return null;
    } else {
        if ($param_value == null)
            error("$param_name is empty");
        return $param_value;
    }
}

function get_path($param_name, $default = null, $description = null)
{
    $path = get_string($param_name, $default, $description);
    if ($path != null)
        $path = trim($path, "/");
    return $path;
}

function get_path_required($param_name, $default = null, $description = null)
{
    $path = get_path($param_name, $default, $description);
    if ($path === null && !isHelp())
        error("$param_name is empty");
    return $path;
}

function get_required_uppercase($param_name, $default = null, $description = null)
{
    $param_value = get_required($param_name, $default, $description);
    if ($param_value != null)
        return strtoupper($param_value);
}

function get_int_required($param_name, $default = null, $description = null)
{
    $param_value = get_int($param_name, $default, $description);
    if (isHelp()) {
        $GLOBALS["params"][$param_name]["required"] = true;
        return null;
    } else {
        if ($param_value === null)
            error("$param_name is empty");
        return $param_value;
    }
}

function get_string_required($param_name, $default = null, $description = null)
{
    return get_required($param_name, $default, $description);
}

function insert($sql, $show_query = null)
{
    return query($sql, $show_query);
}

function get_last_insert_id()
{
    return mysqli_insert_id($GLOBALS["conn"]);
}

function update($sql, $show_query = null)
{
    query($sql, $show_query);
    return $GLOBALS["conn"]->affected_rows > 0;
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

function json_encode_readable(&$result)
{
    if (DEBUG) return json_encode($result);
    //object_properties_to_number($result);
    $json = json_encode($result, JSON_UNESCAPED_UNICODE);
    //$json = preg_replace('/"([a-zA-Z]+[a-zA-Z0-9_]*)":/', '$1:', $json);
    $tc = 0;        //tab count
    $r = '';        //result
    $q = false;     //quotes
    $t = "\t";      //tab
    $nl = "\n";     //new line
    for ($i = 0; $i < strlen($json); $i++) {
        $c = $json[$i];
        if ($c == '"' && $json[$i - 1] != '\\') $q = !$q;
        if ($q) {
            $r .= $c;
            continue;
        }
        switch ($c) {
            case '{':
            case '[':
                $r .= $c . $nl . str_repeat($t, ++$tc);
                break;
            case '}':
            case ']':
                $r .= $nl . str_repeat($t, --$tc) . $c;
                break;
            case ',':
                $r .= $c;
                if ($json[$i + 1] != '{' && $json[$i + 1] != '[') $r .= $nl . str_repeat($t, $tc);
                break;
            case ':':
                $r .= $c . ' ';
                break;
            default:
                $r .= $c;
        }
    }
    return $r;
}

function generateCallTrace()
{

    function getExceptionTraceAsString($exception)
    {
        $rtn = "";
        $count = 0;
        foreach ($exception->getTrace() as $frame) {
            $args = "";
            if (isset($frame['args'])) {
                $args = [];
                foreach ($frame['args'] as $arg) {
                    if (is_string($arg)) {
                        $args[] = "'" . $arg . "'";
                    } elseif (is_array($arg)) {
                        $args[] = "Array";
                    } elseif (is_null($arg)) {
                        $args[] = 'NULL';
                    } elseif (is_bool($arg)) {
                        $args[] = ($arg) ? "true" : "false";
                    } elseif (is_object($arg)) {
                        $args[] = get_class($arg);
                    } elseif (is_resource($arg)) {
                        $args[] = get_resource_type($arg);
                    } else {
                        $args[] = $arg;
                    }
                }
                $args = join(", ", $args);
            }
            $rtn .= sprintf("#%s %s(%s): %s(%s)\n",
                $count,
                $frame['file'],
                $frame['line'],
                $frame['function'],
                $args);
            $count++;
        }
        return $rtn;
    }

    $e = new Exception();
    $trace = explode("\n", getExceptionTraceAsString($e));
    array_shift($trace); //generateCallTrace
    array_shift($trace); //db_error
    array_pop($trace); // empty line
    $result = [];
    for ($i = 0; $i < count($trace); $i++)
        $result[] = $trace[$i];
    return $result;
}

function random_id($length = 11)
{
    //max mysqk bigint = 20 chars
    //max js int = 16 chars
    //max php double without E = 12 chars
    $random_long = mt_rand(1, 9);
    for ($i = 0; $i < $length; $i++)
        $random_long .= mt_rand(0, 9);
    return doubleval($random_long);
}

function random_key($table_name, $key_name, $length = 11)
{
    do {
        $random_key_id = random_id($length);
        $key_exist = scalar("select count(*) from `$table_name` where $key_name = $random_key_id");
    } while ($key_exist != 0);
    return $random_key_id;
}

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

function description($description)
{
    if (isHelp()) {
        $response = [
            "description" => $description,
            "params" => $GLOBALS[params]
        ];
        die(json_encode_readable($response));
    }
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

    return $response;
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