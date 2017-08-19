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


/*
 * A Discoin transaction
 * 
 * @param \Discoin\Users\User $user A user that is the sender
 * @param string $source The souce bot currency
 * @param string $target $user The target bot currency
 * @param float $amount The amount the transaction (in the source currency)
 * @param string $type Transaction type ("normal" or "refund")
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
    public $type;
    public $amount_source;
    public $amount_discoin;
    public $amount_target;
    public $processed = False;
    public $process_time = 0;
    
    
    function __construct($user, $source, $target, $amount, $type="normal")
    {
        $this->user = $user->id;
        $this->source = $source;
        $this->target = $target;
        $this->type = $type;
        
        // Round to 2dp
        $amount = round($amount, 2);
        if ($amount <= 0)
            send_json_error("invalid amount");
        
        $source_bot = \Discoin\Bots\get_bot(["currency_code" => $source]);
        $target_bot = \Discoin\Bots\get_bot(["currency_code" => $target]);
        
        if (is_null($target_bot))
            send_json_error("invalid destination currency");
        $this->amount_source = $amount;
        // These are also rounded to 2dp.
        $this->amount_discoin = round($amount * $source_bot->to_discoin, 2);
        // Fix so the rates work as expected.
        // to_discoin also acts as how much a discoin is worth to a bot.
        $this->amount_target = round($this->amount_discoin / $target_bot->to_discoin * $target_bot->from_discoin, 2);
        
        // Limit checks
        if ($user->exceeds_user_daily_limit($target_bot, $this->amount_discoin))
            // Daily limit
            Transaction::decline("per-user limit exceeded",
                                 ["limit" => $target_bot->limit_user,
                                  "limit_now"=>$user->current_limit_for_bot($target_bot)]);
        else if ($user->exceeds_bot_global_limit($target_bot, $this->amount_discoin))
            // Global limit
            Transaction::decline("total limit exceeded", ["limit" => $target_bot->limit_global]);
                
        // If we get here we're okay!
        $this->timestamp = time();
        $this->receipt = $this->get_receipt();
        $target_bot->log_transaction($this);
        $user->log_transaction($this);
        $this->approve($target_bot->limit_user - $user->daily_exchanges[$target]);
        $this->save();
        
        // Send a nice little webhook!
        $this->new_transaction_webhook();
    }
    
    private function get_receipt()
    {
        return sha1(uniqid(time().$this->user, True));
    }
    
    private static function decline($reason, $limits=null)
    {
        http_response_code(400);
        $declined = ["status" => "declined", "reason" => $reason];
        // Limits
        if (!is_null($limits))
        {
            // Must define a limit
            $declined["limit"] = $limits["limit"];
            // Secondary limit now
            if (array_key_exists("limit_now", $limits))
                $declined["limitNow"] =  $limits["limit_now"];
        }
        send_json($declined, 400);
        die();
    }
    
    private function approve($limit_now)
    {
        send_json(["status" => "approved",
                   "receipt" => $this->receipt,
                   "limitNow" => $limit_now,
                   "resultAmount" => $this->amount_target]);
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
        
        \Discord\send_webhook(TRANSACTION_WEBHOOK, ["embeds" => [$transaction_embed]]);
    }
    
    /*
     * A factory? for making transactions.
     * 
     * @param \Discoin\Bots\Bot $source_bot The source bot
     * @param stdClass $transaction_info The parsed JSON transaction info (see API docs).
     * 
     * returns Transaction The Discoin transaction
     */
    public static function create_transaction($source_bot, $transaction_info)
    {
        if (isset($transaction_info->user, $transaction_info->amount, $transaction_info->exchangeTo))
        {
            $user = \Discoin\Users\get_user($transaction_info->user);
            if (is_null($user))
                Transaction::decline("verify required");
            else if (!is_numeric($transaction_info->amount)) 
                Transaction::decline("amount NaN");
            $amount = floatval($transaction_info->amount);
            
            $transaction = new Transaction($user, $source_bot->currency_code, strtoupper($transaction_info->exchangeTo), $amount);
            return $transaction;
        } 
        else
        {
            send_json_error("bad post");
        }
        return null;
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
    
    public function __toString()
    {
        
        $processed = $this->processed ? format_timestamp($this->process_time) : "UNPROCESSED";
        // Simple text formatting.
        $record_format = "||%s|| %s || %s || %.2f %s => %.2f Discoin => %.2f %s";
        return sprintf($record_format, 
                       $this->receipt,
                       format_timestamp($this->timestamp), 
                       str_pad($processed, 19),
                       $this->amount_source,
                       $this->source,
                       $this->amount_discoin,
                       $this->amount_target,
                       $this->target);
    }

    // Returns everything (for bot devs)
    // (so is not like the normal json serialize)
    public function full_details()
    {
        return get_object_vars($this);
    }
    
    public function save()
    {
        \MacDue\DB\upsert("transactions", $this->receipt, $this);
    }
    
}


function get_transaction($receipt)
{
    $transaction_data = \MacDue\DB\get_collection_data("transactions", ["receipt" => $receipt]);
    if (sizeof($transaction_data) == 1)
        return Transaction::load($transaction_data[0]);
    return null;
}


// Try out a php interface for fun
// (don't really need this but I wanted to test it out)
interface iHasTransactions
{
    public function log_transaction($transaction);
}

?>
