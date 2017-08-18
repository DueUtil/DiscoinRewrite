<?php
/*
* Simple requests handler.
* This simple script just handles the requests (of course).
* Transactions and stuff will be handled in other files.
* 
* @author MacDue
*/
require_once("scripts/util.php");
require_once("discoin/transactions.php");
require_once("discoin/bots.php");
require_once("discoin/users.php");


use function \MacDue\Util\startsWith as startsWith;
use function \MacDue\Util\requires_auth as requires_auth;
use function \MacDue\Util\send_json_error as send_json_error;


$request = rtrim($_SERVER['REQUEST_URI'], "/");
$get_request = $_SERVER['REQUEST_METHOD'] === "GET";
$headers = apache_request_headers();
$auth_key = \MacDue\Util\get($headers["Authorization"]);
header("Content-Type: text/plain");


if ($get_request)
{
    // GET requests
    if ($request === "")
    { 
        // Welcome
        echo "Welcome to Discoin V2!";
    }
    else if ($request === "/rates")
    {
        // Rates
        Discoin\Bots\show_rates();
    } 
    else if ($request === "/transactions")
    {
        // TODO: Stuff
    } 
    else if (startsWith($request, "/verify"))
    {
        // User verification
        $discord_auth = requires_auth("/verify");
        if ($discord_auth->logged_in())
        {
            Discoin\Users\add_user($discord_auth->get_user_details());
        }
        else
        {
            echo "Not logged in?!";
        }
    } 
    else if ($request === "/record")
    {
        require_once("scripts/discordauth.php");
    } else 
    {
        // Unknown request.
        send_json_error("cannot get $request");
    }  
} else 
{
    // POST requests
    $request_data = json_decode(file_get_contents("php://input"));
    if (is_null($json) && json_last_error() !== JSON_ERROR_NONE)
    {
        // Invalid json.
        send_json_error("invalid json");
    } else if ($request === "/transaction")
    {
        // TODO: Stuff
    } else if ($request === "/transaction/reverse")
    {
        // TODO: Stuff
    } else
    {
        send_json_error("cannot post $request");
    }
}

?>
