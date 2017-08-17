<?php
/** 
* A crappy form to add new bots.
*/
$request = "/discoin/addbot.php";
require_once __DIR__."/../scripts/discordauth.php";
require_once __DIR__."/../scripts/util.php";
require_once __DIR__."/discoin.php";
require_once __DIR__."/bots.php";


if (!$discord_auth->get_auth()["login"]) {
    unauthorized();
}

$user_info = $discord_auth->get_user_details();
if (!is_owner($user_info["id"])) {
      unauthorized();
}

if (!isset($_POST["owner"], $_POST["botName"], $_POST["currencyCode"],
           $_POST["toDiscoin"], $_POST["fromDiscoin"])) {
    // Add bot form
    echo file_get_contents("addbot.html");
} else {
    $owner = $_POST["owner"];
    $bot_name = $_POST["botName"];
    $currency_code = $_POST["currencyCode"];
    $to_discoin = floatval($_POST["toDiscoin"]);
    $from_discoin = floatval($_POST["fromDiscoin"]);
    if (!(is_numeric($owner) && is_string($bot_name) && is_string($currency_code)
          && strlen($currency_code) == 3 && $from_discoin <= $to_discoin)){
        http_response_code(400);
        echo "BAD REQUEST";
    } else {
        http_response_code(200);
        $bot = add_bot($owner, $bot_name, $currency_code, $to_discoin, $from_discoin);
        echo $bot->auth_key;
    }
}
?>
