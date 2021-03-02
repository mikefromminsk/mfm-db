<?php
error_reporting(1);

header("Content-type: application/json;charset=utf-8");

require_once $_SERVER["DOCUMENT_ROOT"] . "/db/properties.php";

if ($db_name == null || $db_user == null || $db_pass == null)
    die(json_encode(array("message" => "Create properties.php with database connection parameters")));

$mysql_conn = isset($GLOBALS["conn"]) ? $GLOBALS["conn"] : null;
if ($mysql_conn == null)
    $mysql_conn = new mysqli("localhost", $db_user, $db_pass, $db_name); // change localhost to $host_name

if ($mysql_conn->connect_error)
    die("Connection failed: " . $mysql_conn->connect_error . " check properties.php file");

$mysql_conn->set_charset("utf8");
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
    if (isset($_GET["help"]))
        error("cannot run sql in help mode");
    $success = $GLOBALS["conn"]->query($sql);
    if (!$success)
        error(mysqli_error($GLOBALS["conn"]));
    return $success;
}

function select($sql, $show_query = false)
{
    $result = query($sql, $show_query);
    if ($result->num_rows > 0) {
        $rows = array();
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
    $res = array();
    foreach ($table as $row)
        $res[$row[$column]][] = $row;
    return $res;
}

function selectList($sql, $show_query = false)
{
    $result = query($sql, $show_query);
    echo json_encode($result);
    if ($result->num_rows > 0) {
        $rows = array();
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
            ($param_name . (is_null($param_value) ? " is null" : " = " . (is_double($param_value) ? $param_value : "'" . uencode($param_value) . "'"))) . " and ";
    return rtrim($sql, " and ");
}

function scalarWhere($table, $field, $where, $show_query = false)
{
    return scalar("select $field from $table " . arrayToWhere($where), $show_query);
}

function selectWhere($table, $where, $show_query = false)
{
    return select("select * from $table " . arrayToWhere($where), $show_query);
}

function selectRowWhere($table, $where, $show_query = false)
{
    return selectRow("select * from $table " . arrayToWhere($where), $show_query);
}

function table_exist($table_name)
{
    return scalar("show tables like '$table_name'") != null;
}

function error($error_message)
{
    $result["error"] = $error_message;
    $stack = generateCallTrace();
    if ($stack != null)
        $result["stack"] = $stack;
    http_response_code(500); // INTERNAL SERVER ERROR
    die(json_encode_readable($result));
}

function array_to_map($array, $key)
{
    $map = array();
    foreach ($array as $item)
        $map[$item[$key]] = $item;
    return $map;
}

function uencode($param_value)
{
    return mysqli_real_escape_string($GLOBALS["conn"], $param_value);
}

function get($param_name, $default = null, $description = null)
{
    if (isset($_GET["help"])) {
        $GLOBALS["params"][$param_name]["name"] = $param_name;
        $GLOBALS["params"][$param_name]["type"] = "string";
        $GLOBALS["params"][$param_name]["required"] = false;
        $GLOBALS["params"][$param_name]["default"] = $default;
        $GLOBALS["params"][$param_name]["description"] = $description;
    }
    // TODO add test on sql
    $param_value = null;
    if (isset($_GET[$param_name]))
        $param_value = $_GET[$param_name];
    if ($param_value === null && isset($_POST[$param_name]))
        $param_value = $_POST[$param_name];
    if ($param_value === null && isset($_SESSION[$param_name]))
        $param_value = $_SESSION[$param_name];
    if ($param_value === null && isset($_COOKIE[$param_name]))
        $param_value = $_COOKIE[$param_name];
    if ($param_value === null && isset($_FILES[$param_name]))
        $param_value = $_FILES[$param_name];
    if ($param_value === null && isset(getallheaders()[$param_name]))
        $param_value = getallheaders()[$param_name];
    if ($param_value === null)
        return $default;
    return $param_value;
}

function get_string($param_name, $default = null, $description = null)
{
    return get($param_name, $default, $description);
}

function get_int($param_name, $default = null, $description = null)
{
    $param_value = get($param_name, $default, $description);
    if (isset($_GET["help"])) {
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
    if (isset($_GET["help"]))
        $GLOBALS["params"][$param_name]["type"] = "int_array";
    $arr = get($param_name, $default, $description);
    return $arr != null ? explode(",", $arr) : null;
}

function get_required($param_name, $default = null, $description = null)
{
    $param_value = get($param_name, $default, $description);
    if (isset($_GET["help"])) {
        $GLOBALS["params"][$param_name]["required"] = true;
        return null;
    } else {
        if ($param_value === null)
            error("$param_name is empty");
        return $param_value;
    }
}

function get_required_uppercase($param_name, $default = null, $description = null)
{
    return strtoupper(get_required($param_name, $default, $description));
}

function get_required_lowercase($param_name, $default = null, $description = null)
{
    return strtolower(get_required($param_name, $default, $description));
}

function get_int_required($param_name, $default = null, $description = null)
{
    $param_value = get_int($param_name, $default, $description);
    if (isset($_GET["help"])) {
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
    return insert("insert into $table_name (" . implode(",", array_keys($params)) . ") values ($insert_params)", $show_query);
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
        $set_params_string .= (is_double($param_name) ? $param_value : " $param_name = " . (is_numeric($param_value) ? $param_value : (is_null($param_value) ? "null" : "'" . uencode($param_value) . "'"))) . ", ";
    $set_params_string = rtrim($set_params_string, ", "); // !!! CHAR LSIT
    return update("update $table_name set $set_params_string " . arrayToWhere($where), $show_query);
}

function object_properties_to_number(&$object)
{
    if (is_object($object) || is_array($object))
        foreach ($object as &$property)
            object_properties_to_number($property);
    if (is_string($object) && is_doublee($object))
        $object = doubleval($object);
}

function json_encode_readable(&$result)
{
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

function getExceptionTraceAsString($exception)
{
    $rtn = "";
    $count = 0;
    foreach ($exception->getTrace() as $frame) {
        $args = "";
        if (isset($frame['args'])) {
            $args = array();
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

function generateCallTrace()
{
    $e = new Exception();
    $trace = explode("\n", getExceptionTraceAsString($e));
    array_shift($trace); //generateCallTrace
    array_shift($trace); //db_error
    array_pop($trace); // empty line
    $result = array();
    for ($i = 0; $i < count($trace); $i++)
        $result[] = $trace[$i];
    return $result;
}

function random_id()
{
    //max mysqk bigint = 20 chars
    //max js int = 16 chars
    //max php double without E = 12 chars
    $random_long = mt_rand(1, 9);
    for ($i = 0; $i < 11; $i++)
        $random_long .= mt_rand(0, 9);
    return doubleval($random_long);
}

function random_key($table_name, $key_name)
{
    do {
        $random_key_id = random_id();
        $key_exist = scalar("select count(*) from $table_name where $key_name = $random_key_id");
    } while ($key_exist != 0);
    return $random_key_id;
}

function array_extend(array $a, array $b)
{
    foreach ($b as $k => $v)
        $a[$k] = is_array($v) && isset($a[$k]) ?
            array_extend(is_array($a[$k]) ?
                $a[$k] : array(), $v) :
            $v;
    return $a;
}

function file_list_rec($dir, &$ignore_list, &$results = array())
{
    $files = scandir($dir);
    foreach ($files as $key => $value) {
        $path = realpath($dir . "/" . $value);
        $path = str_replace("\\", "/", $path);
        $ignore = false;
        foreach ($ignore_list as $ignore_item)
            $ignore = $ignore || (strpos($path, $ignore_item) !== false);
        if ($ignore)
            continue;
        if (!is_dir($path)) {
            $results[] = $path;
        } else if ($value != "." && $value != "..") {
            file_list_rec($path, $ignore_list, $results);
        }
    }
    return $results;
}

function description($title)
{
    if (isset($_GET["help"])) {
        $_GET["script_title"] = $title;
        include_once "help.php";
        die();
    }
}
