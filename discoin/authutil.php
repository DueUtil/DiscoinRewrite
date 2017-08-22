<?php
namespace Discoin\Util;

require_once __DIR__."/discoin.php";
require_once __DIR__."/bots.php";
require_once __DIR__."/../scripts/util.php";

use function \MacDue\Util\send_json_error as send_json_error;
use function \MacDue\Util\unauthorized as unauthorized;


function requires_discord_auth($page)
{
    $request = $page;
    return require_once __DIR__."/../discoin/discordauth.php";
}


function requires_discoin_auth()
{
    $headers = apache_request_headers();
    $auth_key = \MacDue\Util\get($headers["Authorization"]);
    if (!is_null($auth_key) && is_string($auth_key)) {
        $bot = \Discoin\Bots\get_bot(["auth_key" => $auth_key]);
        // If we found the bot they are authorized
        if (!is_null($bot)) return $bot;
    }
    // Die! They've not got valid auth!
    send_json_error("unauthorized", 401);
}


function requires_discoin_owner()
{
    global $discord_auth;
    // Assumes login has been attempted.
    if (!$discord_auth->logged_in()) {
        unauthorized();
    }
    $user_info = $discord_auth->get_user_details();
    if (!\Discoin\is_owner($user_info["id"])) {
        unauthorized();
    }
}

?>
