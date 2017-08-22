<?php
/** 
* Random util functions
* 
* @author MacDue
*/

namespace MacDue\Util;

/** 
* Converts an object to an associative array (lazy)
* 
* @param object $object The title of the embed
* @author MacDue
*/
function object_to_array($object)
{
    return json_decode(json_encode($object), True);
}

/* VVV Some super basic string functions VVV */

function startsWith($haystack, $needle)
{
     $length = strlen($needle);
     return (substr($haystack, 0, $length) === $needle);
}


function endsWith($haystack, $needle)
{
    $length = strlen($needle);
    if ($length == 0) {
        return true;
    }
    return (substr($haystack, -$length) === $needle);
}


function str_contains($haystack, $needle)
{
    return strpos($haystack, $needle) !== false;
}


function format_timestamp($timestamp)
{
    return gmdate("d/m/Y \a\\t H:i", $timestamp);
}


function strip($sting, $character=array("\r", "\n", " "))
{
    return str_replace($character, '', $sting);
}


function unauthorized()
{
    http_response_code(401);
    echo "Unauthorized";
    die();
}


function send_json($data, $status=200)
{
    header('Content-Type: application/json');
    http_response_code($status);
    echo json_encode($data);
}


function send_json_error($message, $status=400)
{
    send_json_status("error", $message, $status);
}


function send_json_status($status, $reason, $http_status=200, $extras=[])
{
    $transaction_status = ["status" => $status];
    if (!is_null($reason))
        $transaction_status["reason"] = $reason;
    send_json(array_merge($transaction_status, $extras), $http_status);
    if ($status === "error" || $status === "failed") {
        // Assume if something goes wrong the script should end.
        die();
    }
}


function get(&$var, $default=null) {
    return isset($var) ? $var : $default;
}

/* VVV Random stuff */

/** 
* Converts a string, object, or array
* that uses snake_case to camelCase
* so I can use snake_case internally
* but output camelCase.
* 
* @param mixed $snake_case an array, string, or object.
* 
* @return mixed an array, string, or object now in camelCase.
*/
function toCamelCase($snake_case)
{
    if (is_string($snake_case)) {
        // Simple string
        return _toCamelCase($snake_case);
    } else {
        // Arrays or objects
        $snake_case_array = (array) $snake_case;
        foreach($snake_case_array as $key => $value) {
            unset($snake_case_array[$key]);
            $snake_case_array[_toCamelCase($key)] = $value;
        }
        return is_array($snake_case) ? $snake_case_array : (object) $snake_case_array;
    }
}

function _toCamelCase($snek_case_string)
{
    // converts a_string_like_this to aStringLikeThis
    return preg_replace('/_([a-z0-9])/e', 'strtoupper("$1")', strtolower($snek_case_string));
}
?>
