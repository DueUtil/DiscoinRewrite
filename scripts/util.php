<?php
/** 
* Random util functions
* 
* @author MacDue
*/


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



function strip($sting, $character=array("\r", "\n"))
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
    echo json_decode($data);
}


function send_json_error($message, $status=400)
{
    send_json(["error"=>$message, $status]);
}
?>
