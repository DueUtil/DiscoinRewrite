<?php
/** 
* A crappy form to add new bots.
*/
$request = "/discoin/addbot.php";

require_once __DIR__."/discoin.php";
require_once __DIR__."/bots.php";
require_once __DIR__."/discordauth.php";
require_once __DIR__."/../scripts/util.php";

use function \MacDue\Util\unauthorized as unauthorized;
use function \MacDue\Util\strip as strip;

if (!$discord_auth->logged_in())
    unauthorized();

$user_info = $discord_auth->get_user_details();
if (!\Discoin\is_owner($user_info["id"])) 
    unauthorized();

if (!isset($_POST["owner"], $_POST["botName"], $_POST["currencyCode"],
           $_POST["toDiscoin"], $_POST["fromDiscoin"])) 
{
    // Add bot form
    header("Content-Type: text/html");
    echo file_get_contents("addbot.html");
} 
else 
{
    $owner = strip($_POST["owner"]);
    $bot_name = strip($_POST["botName"]);
    $currency_code = strtoupper(strip($_POST["currencyCode"]));
    $to_discoin = floatval($_POST["toDiscoin"]);
    $from_discoin = floatval($_POST["fromDiscoin"]);
    $limit_user = floatval($_POST["limitUser"]);
    $limit_global = floatval($_POST["limitGlobal"]);
    
    if (!(is_numeric($owner)
          && is_string($bot_name)
          && is_string($currency_code)
          && strlen($currency_code) == 3
          && $from_discoin > 0
          && $limit_user > 0
          && $limit_global > $limit_user
          && $from_discoin <= $to_discoin))
    {
        // You've broken the rules (I cba giving more details)
        http_response_code(400);
        echo "BAD REQUEST";
    }
    else 
    {
        header("Content-Type: text/plain");
        $existing_bot = \Discoin\Bots\get_bot(["_id" => "$owner/$bot_name"]);
        if (is_null($existing_bot))
        {
            // Add bot if there is no existing one
            $bot = \Discoin\Bots\add_bot($owner,
                                         $bot_name,
                                         $currency_code,
                                         $to_discoin,
                                         $from_discoin,
                                         $limit_user,
                                         $limit_global);
            echo $bot->auth_key;
        }
        else
        {
            // Update bot
            $existing_bot->currency_code = $currency_code;
            $existing_bot->to_discoin = $to_discoin;
            $existing_bot->from_discoin = $from_discoin;
            $existing_bot->limit_user = $limit_user;
            $existing_bot->limit_global = $limit_global;
            $existing_bot->save();
            echo "Updated bot!\n$existing_bot\n";
            echo "Daily limit user: $existing_bot->limit_user\n";
            echo "Daily limit global: $existing_bot->limit_global";
        }
    }
}
?>
