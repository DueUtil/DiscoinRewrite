<?php


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
