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



function strip($sting, $character=array("\r", "\n")) {
    return str_replace($character, '', $sting);
}
?>
