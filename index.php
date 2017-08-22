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
* 
* Some notes:
*  Right now I don't use loads of getters and setters for stuff in classes.
*  I just kinda trust it will be used properly (and don't want to write)
*  loads of getters and setters (and I've been doing a bunch of Python).
* 
*/

require_once "discoin/discoin.php";
require_once "discoin/transactions.php";
require_once "discoin/bots.php";
require_once "discoin/users.php";
require_once "discoin/authutil.php";
require_once "scripts/util.php";

// Common functions
use function \MacDue\Util\startsWith as startsWith;
use function \MacDue\Util\send_json as send_json;
use function \MacDue\Util\send_json_error as send_json_error;
use function \Discoin\Util\requires_discord_auth as requires_discord_auth;
use function \Discoin\Util\requires_discoin_auth as requires_discoin_auth;

$config = Discoin\get_config();

// Get request type and page
$request = rtrim($_SERVER['REQUEST_URI'], "/");
$get_request = $_SERVER['REQUEST_METHOD'] === "GET";
header("Content-Type: text/plain");


if ($get_request) {
    /*
     * Handle all GET requests.
     * Most things (meant for bots will be JSON)
     */
     
    if ($request === "") {
        // Welcome page
        echo $config->welcomeMessage;

    } else if ($request === "/rates") {
        // Rates
        Discoin\Bots\show_rates();

    } else if ($request === "/transactions") {
        // Get transactions
        // needs a bot auth
        $bot = requires_discoin_auth();
        send_json($bot->get_transactions());

    } else if (startsWith($request, "/transaction/")) {
        // Get the full details of a transaction (for devs)
        requires_discoin_auth();
        $receipt = explode("/", $request)[2];
        $transaction = \Discoin\Transactions\get_transaction($receipt);
        if (!is_null($transaction)) {
            // Send the full dump of the transaction
            send_json($transaction->get_full_details());
        } else {
            send_json_error("transaction not found", 404);
        }

    } else if (startsWith($request, "/verify")) {
        // User verification
        $discord_auth = requires_discord_auth("/verify");
        if ($discord_auth->logged_in()) {
            // Adds the user (if their email is not a spam one)
            Discoin\Users\add_user($discord_auth->get_user_details());
        }

    } else if (startsWith($request, "/record")) {
        // User transaction record
        $discord_auth = requires_discord_auth("/record");
        if ($discord_auth->logged_in()) {
            $user = Discoin\Users\get_user($discord_auth->get_user_details()["id"]);
            if (!is_null($user)) {
                // Print the users transaction record
                $user->show_transactions();
            } else {
                // The user does not exist yet
                echo "You need to verify before you view your record.";
            }
        }

    } else {
        // Unknown GET (the fuck are you guys doing?)
        send_json_error("invalid get $request");
    }
} else {
    /*
     * Handle all POST requests.
     * Everything should be JSON (unless I've messed up)
     */
     
    $request_data = json_decode(file_get_contents("php://input"));
    if (is_null($request_data) && json_last_error() !== JSON_ERROR_NONE) {
        // Invalid json :(
        send_json_error("invalid json");

    } else if ($request === "/transaction") {
        // Creates a transaction (if the bot auth is valid)
        $source_bot = requires_discoin_auth();
        if (isset($request_data->user,
                  $request_data->amount,
                  $request_data->exchangeTo)
        ) {
            // Make the transaction
            $transaction_info = $request_data;
            ksort($transaction_info);
            Discoin\Transactions\make_transaction($source_bot, ...array_values($transaction_info));
        } else {
            send_json_error("bad post");
        }

    } else if ($request === "/transaction/reverse") {
        // Reverse a transaction given a receipt
        $bot = requires_discoin_auth();
        if (isset($request_data->receipt)) {
            // Reverse/refund
            Discoin\Transactions\reverse_transaction($bot, $request_data->receipt);
        } else {
            send_json_error("no transaction receipt");
        }

    } else {
        // >:(
        send_json_error("invalid post $request");
    }
}

?>
