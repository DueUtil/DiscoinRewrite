<?php
/*
 * General Discoin stuff.
 * 
 * @author MacDue
 */
 
namespace Discoin;

define("TRANSACTION_LIMIT_RESET", 86400);


/*
 * Base object for other Discoin objects
 * 
 * @author MacDue
 */
class Object
{
  
    public static function load($std_obj)
    {
        foreach ($std_obj as $attr_name => $value)
            if (is_object($value))
                $std_obj->$attr_name = (array) $value;
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
