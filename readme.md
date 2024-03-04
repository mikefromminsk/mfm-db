# db.php Documentation

## Overview
The `db.php` file is a PHP script that handles database operations such as connection, query execution, and data retrieval. It also includes utility functions for handling HTTP requests and responses.

## Database Connection
The script connects to a MySQL database using the `mysqli` extension. The connection parameters are retrieved from a `properties.php` file.

## HTTP Request Handling
The script handles HTTP POST and PUT requests with a content type other than `application/x-www-form-urlencoded`. The request body is parsed as JSON and the parameters are added to the `$_POST` superglobal.

## Database Query Functions
The script provides several functions for executing SQL queries and retrieving data:

- `query($sql, $show_query = false)`: Executes an SQL query and returns the result. If `$show_query` is `true`, the query is also outputted for debugging purposes.
- `select($sql, $show_query = false)`: Executes an SQL SELECT query and returns the result rows as an associative array.
- `scalar($sql, $show_query = false)`: Executes an SQL SELECT query and returns the first column of the first row.
- `selectMapList($sql, $column, $show_query = false)`: Executes an SQL SELECT query and returns the result rows as an associative array, using the specified column as the key.
- `selectList($sql, $show_query = false)`: Executes an SQL SELECT query and returns the first column of the result rows as an array.
- `selectRow($sql, $show_query = false)`: Executes an SQL SELECT query and returns the first row as an associative array.

## Error Handling
The script includes a `error($error_message)` function that sends an HTTP 500 response with a JSON body containing the error message. If the `DEBUG` constant is `true`, the function also includes a stack trace in the response.

## Utility Functions
The script includes several utility functions for handling arrays, strings, and HTTP requests. These include functions for encoding and decoding JSON, generating random IDs, and retrieving request parameters.

## Constants
The script uses several constants for configuration:

- `DEBUG`: If `true`, the script includes a stack trace in error responses.
- `MYSQLI_OPT_INT_AND_FLOAT_NATIVE`: This constant is used to configure the `mysqli` extension to return integer and float columns as native PHP types.

## Global Variables
The script uses several global variables:

- `$mysql_conn`: The `mysqli` connection object.
- `$host_name`: The host name for the database connection.
- `$GLOBALS["conn"]`: A reference to the `mysqli` connection object.
- `$GLOBALS["params"]`: An associative array of request parameters.