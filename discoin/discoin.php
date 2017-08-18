<?php
namespace Discoin;

define("TRANSACTION_LIMIT_RESET", 86400);


class Object
{
  
    public static function load($std_obj)
    {
        $temp = serialize($std_obj);
        $class_name = get_called_class();
        // This is a great hack!
        $temp = preg_replace("@^O:8:\"stdClass\":@","O:".strlen($class_name).":\"$class_name\":",$temp);
        return unserialize($temp);
    }
    
}

function get_config()
{
    return json_decode(file_get_contents(__DIR__.'/config.json'));
}


function is_owner($user)
{
    $config = get_config();
    
    foreach ($config->owners as $owner)
    {
        if ($owner->user === $user)
        {
            return True;
        }
        return False;
    }
}

?>
