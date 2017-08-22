<?php
/* 
 * Crappy form to delete bots 
 */
$request = "/discoin/deletebot.php";

require_once __DIR__."/discordauth.php";
require_once __DIR__."/authutil.php";
require_once __DIR__."/bots.php";
require_once __DIR__."/../scripts/util.php";
require_once __DIR__."/../scripts/dbconn.php";

use function \MacDue\Util\strip as strip;

\Discoin\Util\requires_discoin_owner();


if (!isset($_POST["currencyCode"])) {
    // show form
    header("Content-Type: text/html");
    echo file_get_contents("deletebot.html");
} else {
    // Attempt to delete bot.
    if (is_string($_POST["currencyCode"])) {
        $currency_code = strtoupper(strip($_POST["currencyCode"]));
        $bot_to_delete = \Discoin\Bots\get_bot(["currency_code" => $currency_code]);
        if (!is_null($bot_to_delete)) {
            \MacDue\DB\delete_document("bots", $bot_to_delete->get_id()); 
            echo "$bot_to_delete->name deleted!";
        } else {
            http_response_code(404);
            echo "Bot not found!";
        }

    } else {
        http_response_code(400);
        echo "You're trying something weird...";
    }
}
?>
