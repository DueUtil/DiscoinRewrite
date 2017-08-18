<?php
/*
 * Stuff to handle Discoin transactions
 * 
 * @author MacDue
 */
 
namespace Discoin\Transactions;

require_once __DIR__."/../scripts/dbconn.php";
require_once __DIR__."/../scripts/util.php";
require_once __DIR__."/discoin.php";
require_once __DIR__."/bots.php";
require_once __DIR__."/users.php";


use function \MacDue\Util\send_json as send_json;
use function \MacDue\Util\send_json_error as send_json_error;


class Transaction extends \Discoin\Object implements \JsonSerializable
{
    public $user;
    public $timestamp;
    public $source;
    public $target;
    public $amount;
    public $receipt;
    public $type;
    public $processed = False;
    
    
    function __construct($user, $source, $target, $amount, $type="normal")
    {
        $this->user = $user->id;
        $this->source = $source;
        $this->target = $target;
        $this->type = $type;
        
        if ($amount <= 0)
        {
            send_json_error("invalid amount");
        }
        
        $source_bot = \Discoin\Bots\get_bot(["currency_code" => $source]);
        $target_bot = \Discoin\Bots\get_bot(["currency_code" => $target]);
        
        if (is_null($target_bot))
        {
            send_json_error("invalid destination currency");
        }
        
        $this->amount_source = $amount;
        $this->amount_target = $amount * $target_bot->from_discoin;
        $this->amount_discoin = $amount * $source_bot->to_discoin;
        
        if ($user->exceeds_daily_limit($source_bot, $target_bot, $this->amount_discoin))
        {
            Transaction::decline("per-user limit exceeded", $target_bot->limit_user);
        } else if ($user->exceeds_global_limit($source_bot, $target_bot, $this->amount_discoin)) 
        {
            Transaction::decline("total limit exceeded", $target_bot->limit_global);
        }
                
        // If we get here we're okay!
        $this->timestamp = time();
        $this->receipt = $this->get_receipt();
        var_dump($user);
        $user->log_transaction($this);
        $this->approve($target_bot->limit_user - $user->daily_exchanges[$target]);
        $this->save();
    }
    
    private static function decline($reason, $limit=null)
    {
        http_response_code(400);
        $declined = ["status" => "declined", "reason" => $reason];
        if (!is_null($limit))
            $declined["limit"] = $limit;
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
    
    private function get_receipt()
    {
        return sha1(uniqid(time().$this->user, True));
    }
    
    public function save()
    {
        \MacDue\DB\upsert("transactions", $this->receipt, $this);
    }
    
    
    public static function create_transaction($source, $transaction_info)
    {
        if (isset($transaction_info->user, $transaction_info->amount, $transaction_info->exchangeTo))
        {
            $user = \Discoin\Users\get_user($transaction_info->user);
            if (is_null($user))
            {
                Transaction::decline("verify required");
            } else if (!is_numeric($transaction_info->amount)) 
            {
                Transaction::decline("amount NaN");
            }
            $amount = floatval($transaction_info->amount);
            
            $transaction = new Transaction($user, $source->currency_code, strtoupper($transaction_info->exchangeTo), $amount);
            return $transaction;
        } 
        else
        {
            send_json_error("bad post");
        }
        return null;
    }
    
    
    public function jsonSerialize() {
        
          return ["user" => $this->user,
                  "timestamp" => $this->timestamp,
                  "source" => $this->source,
                  "amount" => $this->amount_target,
                  "receipt" => $this->receipt];
      
    }
    
}


function get_transactions_for_bot($bot)
{
    $raw_transactions = \MacDue\DB\get_collection_data("transactions", ["target" => $bot->currency_code, "processed" => False]);
    $transactions = array();
    
    foreach ($raw_transactions as $transaction_data)
    {
        $transaction = Transaction::load($transaction_data);
        $transaction->processed = True;
        $transaction->save();
        $transactions[] = $transaction;
    }
    return $transactions;
}

?>
