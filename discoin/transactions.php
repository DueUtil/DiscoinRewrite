<?php
namespace Discoin\Transactions;

require_once __DIR__."/../scripts/dbconn.php";
require_once __DIR__."/../scripts/util.php";
require_once __DIR__."/discoin.php";
require_once __DIR__."/bots.php";

use function \MacDue\Util\send_json as send_json;
use function \MacDue\Util\send_json_error as send_json_error;


class Transaction extends \Discoin\Object
{
    public $user;
    public $timestamp;
    public $source;
    public $target;
    public $amount;
    public $receipt;
    public $type;
    
    
    function __construct($user, $source, $target, $amount, $type)
    {
        $this->user = $user->id;
        $this->source = $source;
        $this->target = $target;
        $this->amount = $amount;
        $this->type = $type;
        
        if (!(is_float($amount) && $amount > 0))
        {
            send_json_error("invalid amount");
        }
        
        $source_bot = \Discoin\Bots\get_bot(["currency_code" => $source]);
        $target_bot = \Discoin\Bots\get_bot(["currency_code" => $target]);
        
        if (is_null($target_bot))
        {
            send_json_error("invalid destination currency");
        }
        
        $this->amount_discoin = $amount * $source_bot->to_discoin;
        
        if ($user->exceeds_daily_limit($source_bot, $target_bot, $this->amount_discoin))
        {
            decline("per-user limit exceeded", $target_bot->limit_user); 
        } else if ($user->exceeds_global_limit($source_bot, $target_bot, $this->amount_discoin)) 
        {
            decline("total limit exceeded", $target_bot->limit_global); 
        }
                
        // If we get here we're okay!
        $this->receipt = get_receipt();
        $user->log_transaction($this);
        approve($target_bot->limit_user - $user->daily_exchanges[$target]);
    }
    
    private function decline($reason, $limit)
    {
        http_response_code(400);
        send_json(["status" => "declined", "reason" => $reason, "limit" => $limit]);
        die();
    }
    
    private function approve($limit_now)
    {
        send_json(["status" => "approved",
                   "receipt" => $this->receipt,
                   "limitNow" => $limit_now,
                   "resultAmount" => $this->amount_discoin]);
    }
    
    private function get_receipt()
    {
        return sha1(uniqid(time().$this->user, True));
    }
    
    public function save()
    {
        \MacDue\DB\upsert("transactions", $this->receipt, $this);
    }
    
    
    public function jsonSerialize() {
        $vars = get_object_vars($this);
        unset($vars["_id"]);
        return $vars;
    }
    
}


function create_transaction($user, $source, $target, $amount, $type="normal")
{
    $transaction = new Transaction($user, $source, $target, $amount, $type);
    $transaction->save();
    return $transaction;
}

?>
