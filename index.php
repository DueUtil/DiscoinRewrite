<?php
/*
* Discoin rewrite (by MacDue)
* A quick attempt at making Discoin again.
* 
* See the spec at: https://github.com/MacDue/DiscoinRewrite
* 
* Licence: Not chosen yet!
* 
* @author MacDue
*/

require_once("scripts/util.php");
require_once("discoin/transactions.php");
require_once("discoin/bots.php");
require_once("discoin/users.php");

// Common functions
use function \MacDue\Util\startsWith as startsWith;
use function \MacDue\Util\requires_discord_auth as requires_discord_auth;
use function \MacDue\Util\requires_discoin_auth as requires_discoin_auth;
use function \MacDue\Util\send_json as send_json;
use function \MacDue\Util\send_json_error as send_json_error;

// Get request type and page
$request = rtrim($_SERVER['REQUEST_URI'], "/");
$get_request = $_SERVER['REQUEST_METHOD'] === "GET";
header("Content-Type: text/plain");


if ($get_request)
{
    /*
     * Handle all GET requests.
     * Most things (meant for bots will be JSON)
     */
     
    if ($request === "")
    { 
        // Welcome page
        echo "Welcome to Discoin V2!";
    }
    else if ($request === "/rates")
    {
        // Rates
        Discoin\Bots\show_rates();
    } 
    else if ($request === "/transactions")
    {
        // Get get the bot using the auth token.
        // Outputs Unauthorized (in json) if the token is invalid.
        $bot = requires_discoin_auth();
        send_json(Discoin\Transactions\get_transactions_for_bot($bot));
    } 
    else if (startsWith($request, "/verify"))
    {
        // User verification
        $discord_auth = requires_discord_auth("/verify");
        if ($discord_auth->logged_in())
        {
            // Adds the user (if their email is not a spam one)
            Discoin\Users\add_user($discord_auth->get_user_details());
        }
        else
        {
            // They probably hit cancle.
            echo "Not logged in?!";
        }
    } 
    else if ($request === "/record")
    {
        requires_discord_auth("/record");
        // TODO: Show record.
    } else 
    {
        // Unknown GET (the fuck are you guys doing?)
        send_json_error("invalid get $request");
    }  
} else 
{
    /*
     * Handle all POST requests.
     * Everything should be JSON (unless I've messed up)
     */
     
    $request_data = json_decode(file_get_contents("php://input"));
    if (is_null($request_data) && json_last_error() !== JSON_ERROR_NONE)
    {
        // Invalid json :(
        send_json_error("invalid json");
    } else if ($request === "/transaction")
    {
        // Creates a transaction (if the bot auth is valid)
        $bot = requires_discoin_auth();
        Discoin\Transactions\Transaction::create_transaction($bot, $request_data);
    } else if ($request === "/transaction/reverse")
    {
        // TODO: Reverse transactions.
    } else
    {
        // >:(
        send_json_error("invalid post $request");
    }
}

?>
