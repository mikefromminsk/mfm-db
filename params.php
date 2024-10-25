<?php

error_reporting(1);

header("Content-type: application/json;charset=utf-8");

// TODO  is_numeric =>      is_numeric($result) && !is_string($result)
if (isset($_SERVER["CONTENT_TYPE"]) && $_SERVER["CONTENT_TYPE"] != 'application/x-www-form-urlencoded'
    && ($_SERVER['REQUEST_METHOD'] == 'POST' || $_SERVER['REQUEST_METHOD'] == 'PUT')) {
    $inputJSON = file_get_contents('php://input');
    $inputParams = json_decode($inputJSON, true);
    //file_put_contents("sef", $inputParams);
    foreach ($inputParams as $key => $value)
        $_POST[$key] = $value;
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
    die(json_encode($result, JSON_PRETTY_PRINT));
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

function description($description)
{
    if (isHelp()) {
        $response = [
            "description" => $description,
            "params" => $GLOBALS[params]
        ];
        die(json_encode($response, JSON_PRETTY_PRINT));
    }
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

function onlyInDebug()
{
    if (!DEBUG)
        error("cannot use not in debug session");
}

function getScriptPath()
{
    $path = $_SERVER["SCRIPT_NAME"];
    $path = str_replace("\\", "/", $path);
    if ($path[0] == "/")
        $path = substr($path, 1);
    return $path;
}

function getScriptName()
{
    return basename($_SERVER["SCRIPT_NAME"], ".php");
}

function getDomain()
{
    return explode("/", getScriptPath())[0];
}

// TODO add config filename for separate config accesses
function get_config_required($config_param_name)
{
    if ($GLOBALS[$config_param_name] != null)
        return $GLOBALS[$config_param_name];

    $properties_path = $_SERVER["DOCUMENT_ROOT"] . "/../config.php";
    include_once $properties_path;

    $vars = get_defined_vars();

    foreach ($vars as $param => $value) {
        $GLOBALS[$param] = $value;
    }

    if ($vars[$config_param_name] == null) {
        onlyInDebug();
        $value = get_required($config_param_name);
        if (!file_exists($properties_path)) {
            $properties = "<?php";
        } else {
            $properties = file_get_contents($properties_path);
        }
        $properties .= "\n\$$config_param_name = \"$value\";";
        file_put_contents($properties_path, $properties);
        return $value;
    } else {
        return $vars[$config_param_name];
    }
}