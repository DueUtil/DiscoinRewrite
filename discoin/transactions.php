<?php
/*
 * Stuff to handle Discoin transactions
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

use function \MacDue\Util\send_json as send_json;
use function \MacDue\Util\send_json_error as send_json_error;
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
    public $type = "normal";


    private function __construct()
    {
        // A very badic constructor
        // Everything is handled by create and reverse
        $this->timestamp = time();
        $this->generate_receipt();
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
        Transaction::send_status("declined", $reason, 400, $limits);
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
    
    /*
     * A factory? for making transactions.
     * 
     * @param \Discoin\Bots\Bot $source_bot The source bot
     * @param stdClass $transaction_info The parsed JSON transaction info (see API docs).
     * 
     * returns Transaction The Discoin transaction
     */
    public static function create($source_bot, $transaction_info)
    {
        if (isset($transaction_info->user,
                  $transaction_info->amount,
                  $transaction_info->exchangeTo)
        ) {
            $user = \Discoin\Users\get_user($transaction_info->user);
            if (is_null($user)) {
                // User does not exist
                Transaction::decline("verify required");
            } else if (!is_numeric($transaction_info->amount)) {
                // Your bot is sending me junk
                Transaction::decline("amount NaN");
            }
            $target = $transaction_info->exchangeTo;
            $amount = floatval($transaction_info->amount);
            
            $target_bot = \Discoin\Bots\get_bot(["currency_code" => $target]);
            // If target bot not found error and die
            if (is_null($target_bot)) send_json_error("invalid destination currency");
            
            // Round to 2dp
            $amount = round($amount, 2);
            // If amount is less too small error and die
            if ($amount <= 0) send_json_error("invalid amount");
            
            $transaction = new self();
            $transaction->user = $user->id;
            $transaction->source = $source_bot->currency_code;
            $transaction->target = $target_bot->currency_code;
            
            $transaction->amount_source = $amount;
            // These are also rounded to 2dp.
            $transaction->amount_discoin = round($amount * $source_bot->to_discoin, 2);
            // Fix so the rates work as expected.
            // to_discoin also acts as how much a discoin is worth to a bot.
            $transaction->amount_target = round($transaction->amount_discoin / $target_bot->to_discoin * $target_bot->from_discoin, 2);
            
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
            $transaction->approve($target_bot->limit_user - $user->daily_exchanges[$target]);
            $transaction->save();
            
            // Send a nice little webhook!
            $transaction->new_transaction_webhook();
        } else {
            send_json_error("bad post");
        }
    }
    
    public static function reverse($receipt) {
        // Get previous transaction
        $transaction = get_transaction($receipt);
        if (!is_null($transaction)) {
            if ($transaction->type !== "refund") {
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
                $refund->timestamp = time();
                $refund->generate_receipt();
                $refund->save();
                
                // Notify reversal
                send_webhook(TRANSACTION_WEBHOOK, ["content" => ":track_previous: Transaction ``$receipt`` has been reversed!"]);
            } else {
                // Refunding a refund could cause an infinite transaction loop.
                Transaction::send_status("failed", "cannot refund a refund", 400);
            }
        
        } else {
            Transaction::send_status("failed", "transaction not found", 404);
        }
    }

    private function generate_receipt()
    {
        $this->receipt = sha1(uniqid(time().$this->user, True));
    }
    
    private static function send_status($status, $reason, $http_status=200, $extras=[])
    {
        $transaction_status = ["status" => $status];
        if (!is_null($reason))
            $transaction_status["reason"] = $reason;
        send_json(array_merge($transaction_status, $extras), $http_status);
    }
    
    // JSON for GET /transactions
    public function jsonSerialize()
    {
        
          return ["user" => $this->user,
                  "timestamp" => $this->timestamp,
                  "source" => $this->source,
                  "amount" => $this->amount_target,
                  "receipt" => $this->receipt];
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


// Try out a php interface for fun
// (don't really need this but I wanted to test it out)
interface iHasTransactions
{
    public function log_transaction($transaction);
}

?>
