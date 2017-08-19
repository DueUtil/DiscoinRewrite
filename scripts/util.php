<?php
/** 
* Random util functions
* 
* @author MacDue
*/

namespace MacDue\Util;

require_once __DIR__."/../discoin/bots.php";


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


function requires_discord_auth($page)
{
    $request = $page;
    return require_once __DIR__."/../discoin/discordauth.php";
}

/* VVV General util stuff for logins and JSON VVV */

function requires_discoin_auth()
{
    $headers = apache_request_headers();
    $auth_key = \MacDue\Util\get($headers["Authorization"]);
    if (!is_null($auth_key))
    {
        $bot = \Discoin\Bots\get_bot(["auth_key" => $auth_key]);
        if (!is_null($bot))
            return $bot;
    }
    send_json_error("unauthorized", 401);
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
    send_json(["status" => "error", "reason" => $message], $status);
    die();
}


function get(&$var, $default=null) {
    return isset($var) ? $var : $default;
}
?>
