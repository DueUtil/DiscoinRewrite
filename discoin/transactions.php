<?php
/*
 * Stuff to handle Discoin transactions
 * 
 * NOTE: send_json_error, send_json_status, and decline
 * will stop the script. 
 * (send_json_status will only stop the script if the status
 * if "failed" or "error")
 * 
 * @author MacDue
 */
namespace Discoin\Transactions;

require_once __DIR__."/discoin.php";
require_once __DIR__."/bots.php";
require_once __DIR__."/users.php";
require_once __DIR__."/../scripts/dbconn.php";
require_once __DIR__."/../scripts/util.php";
require_once __DIR__."/../scripts/discordstuff.php";

use function \MacDue\Util\strip as strip;
use function \MacDue\Util\send_json as send_json;
use function \MacDue\Util\send_json_error as send_json_error;
use function \MacDue\Util\send_json_status as send_json_status;
use function \MacDue\Util\format_timestamp as format_timestamp;
use function \Discord\send_webhook as send_webhook;


/*
 * A Discoin transaction
 * 
 * You cannot construct a transaction without using
 *  Transaction::create or Transaction::reverse
 * 
 * @author MacDue
 */
class Transaction extends \Discoin\Object implements \JsonSerializable
{
    public $user;
    public $timestamp;
    public $source;
    public $target;
    public $receipt;
    public $amount_source;
    public $amount_discoin;
    public $amount_target;
    public $processed = False;
    public $process_time = 0;
    public $reversed = False;
    public $type = "normal";


    private function __construct()
    {
        // A very badic constructor
        // Everything is handled by create and reverse
        $this->timestamp = time();
        $this->generate_receipt();
    }

    /*
     * Create a new transaction.
     *
     * @param \Discoin\Users\User $user The user making the transaction
     * @param float $amount The amount being exchanged
     * @param \Discoin\Bots\Bot $source_bot The source bot of the transaction
     * @param \Discoin\Bots\Bot $target_bot The target bot of the transaction
     *
     * @return \Discoin\Transactions\Transaction The Discoin transaction
     */
    public static function create($user, $amount, $source_bot, $target_bot)
    {
        if (is_null($user)) {
            Transaction::decline("verify required");
        }
        $transaction = new self();
        $transaction->user = $user->id;
        $transaction->source = $source_bot->currency_code;
        $transaction->target = $target_bot->currency_code;
        $transaction->amount_source = $amount;
        // These are also rounded to 2dp.
        $transaction->amount_discoin = round($amount * $source_bot->to_discoin, 2);
        // Fix so the rates work as expected.
        // to_discoin also acts as how much a discoin is worth to a bot.
        $transaction->amount_target = round($transaction->amount_discoin
                                            / $target_bot->to_discoin * $target_bot->from_discoin, 2);
        // Limit checks
        if ($user->exceeds_user_daily_limit($target_bot, $transaction->amount_discoin)) {
            // Daily limit
            Transaction::decline("per-user limit exceeded",
                                 ["limit" => $target_bot->limit_user,
                                  "limitNow"=> $user->current_limit_for_bot($target_bot)]);
        } else if ($user->exceeds_bot_global_limit($target_bot, $transaction->amount_discoin)) {
            // Global limit
            Transaction::decline("total limit exceeded", ["limit" => $target_bot->limit_global]);
        }
        // If we get here we're okay!
        $target_bot->log_transaction($transaction);
        $user->log_transaction($transaction);
        $transaction->save();
        $transaction->approve($target_bot->limit_user - $user->daily_exchanges[$transaction->target]);
        // Send a nice little webhook!
        $transaction->new_transaction_webhook();
        return $transaction;
    }

    /*
     * Reverse a transaction.
     * This is only meant for if a bot cannot accept a transaction.
     * E.g. If the user is not a user of the bot.
     *
     * @param \Discoin\Transactions\Transaction $transaction The transaction to reverse
     *
     * @return \Discoin\Transactions\Transaction The reversed transaction
     */
    public static function reverse($transaction)
    {
        if ($transaction->type === "refund") {
            send_json_status("failed", "cannot refund a refund", 400);
        }
        if ($transaction->reversed) {
            send_json_status("failed", "transaction already revered", 400);
        }
        // Construct refund
        $refund = new self();
        $refund->type = "refund";
        $refund->source = $transaction->target;
        $refund->target = $transaction->source;
        $refund->user = $transaction->user;
        // A refund won't follow the normal rates.
        $refund->amount_source = $transaction->amount_target;
        $refund->amount_discoin = $transaction->amount_discoin;
        $refund->amount_target = $transaction->amount_source;
        $refund->save();
        $transaction->reversed = True;
        $transaction->save();
        // Okay!
        send_json_status("ok", null, 200, ["refundAmount" => $refund->amount_target]);
        // Notify reversal
        send_webhook(TRANSACTION_WEBHOOK,
                     ["content" => ":track_previous: Transaction ``$transaction->receipt`` has been reversed!"]);
        return $refund;
    }

    private function generate_receipt()
    {
        $this->receipt = sha1(uniqid(time().$this->user, True));
    }

    public function mark_as_processed()
    {
        $this->processed = True;
        $this->process_time = time();
        $this->save();
    }

    private function approve($limit_now)
    {
        send_json(["status" => "approved",
                   "receipt" => $this->receipt,
                   "limitNow" => $limit_now,
                   "resultAmount" => $this->amount_target]);
    }
    
    private static function decline($reason, $limits=[])
    {
        send_json_status("declined", $reason, 400, $limits);
        die();
    }
    
    private function new_transaction_webhook()
    {
        // Makes a nice little embed for the transaction
        $transaction_embed = new \Discord\Embed($title=":new: New transaction!", $colour=7506394);
        $transaction_embed->add_field($name="User", $value=$this->user, $inline=True);
        $transaction_embed->add_field($name="Exchange", 
                                      $value="$this->amount_source $this->source => $this->amount_target $this->target", 
                                      $inline=True);
        $transaction_embed->add_field($name="Receipt", $value=$this->receipt);
        $transaction_embed->set_footer($text="Sent on ".format_timestamp($this->timestamp));
        
        send_webhook(TRANSACTION_WEBHOOK, ["embeds" => [$transaction_embed]]);
    }
    
    // JSON for GET /transactions
    public function jsonSerialize()
    {
        
        $details = ["user" => $this->user,
                    "timestamp" => $this->timestamp,
                    "source" => $this->source,
                    "amount" => $this->amount_target,
                    "receipt" => $this->receipt];
        if ($this->type === "refund") {
            $details["type"] = "refund";
        }
        return $details;
    }

    // Returns everything (for bot devs)
    // (so is not like the normal json serialize)
    public function get_full_details()
    {
        // Return camel case version of object vars.
        // This is so it matches the rest of the API.
        return \MacDue\Util\toCamelCase(get_object_vars($this));
    }

    public function __toString()
    {
        $processed = $this->processed ? format_timestamp($this->process_time) : "UNPROCESSED";
        // Simple text formatting.
        $record_format = "||%s|| %s || %s || %.2f %s => %.2f Discoin => %.2f %s %s";
        return sprintf($record_format, 
                       $this->receipt,
                       format_timestamp($this->timestamp), 
                       str_pad($processed, 19),
                       $this->amount_source,
                       $this->source,
                       $this->amount_discoin,
                       $this->amount_target,
                       $this->target,
                       $this->type === "refund" ? "(REFUND)" : "");
    }

    public function save()
    {
        \MacDue\DB\upsert("transactions", $this->receipt, $this);
    }
    
}



function get_transaction($receipt)
{
    return \MacDue\DB\get_object("transactions", ["receipt" => $receipt]);
}

/*
 * Helper function to validate request before creating the transaction
 */
function make_transaction($source_bot, $amount, $exchange_to, $user_id)
{
    if (!(is_string($user_id) and is_string($exchange_to))) {
        send_json_error("invalid types");
    }
    if (!is_numeric($amount)) {
        send_json_error("amount NaN");
    }
    $amount = round(floatval($amount), 2);
    if ($amount <= 0) {
        send_json_error("invalid amount");
    }
    $target_currency = strtoupper(strip($exchange_to));
    $target_bot = \Discoin\Bots\get_bot(["currency_code" => $target_currency]);
    if (is_null($target_bot)) {
        send_json_error("invalid destination currency");
    }
    $user = \Discoin\Users\get_user($user_id);
    return Transaction::create($user, $amount, $source_bot, $target_bot);
}


/*
 * Helper function to validate reveral before starting it.
 */
function reverse_transaction($bot, $receipt)
{
    if (!is_string($receipt)) {
        send_json_error("invalid receipt");
    }
    $transaction = get_transaction($receipt);
    if (is_null($transaction)) {
        send_json_status("failed", "transaction not found", 404);
    }
    if ($transaction->target !== $bot->currency_code) {
        send_json_status("failed", "transaction must be to your bot", 400);
    }
    return Transaction::reverse($transaction);
}

// Try out a php interface for fun
// (don't really need this but I wanted to test it out)
interface iHasTransactions
{
    public function log_transaction($transaction);
}

?>
