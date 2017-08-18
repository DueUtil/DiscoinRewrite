<?php
namespace Discoin\Users;

require_once __DIR__."/../scripts/dbconn.php";
require_once __DIR__."/../scripts/util.php";
require_once __DIR__."/discoin.php";

define("BURNER_EMAILS", "https://raw.githubusercontent.com/wesbos/burner-email-providers/master/emails.txt");

class User extends \Discoin\Object
{
    public $id;
    public $daily_exchanges = array();
    public $first_transaction_time = -1;
    private $transactions = array();
    
      
    public function __construct($id)
    {
        $this->id = $id;
        $this->save();
    }
    
    private function exceeds_limit($amount_discoin, $exchanged, $limit)
    {
        return $exchanged + $amount_discoin > $limit;
    }
    
    public function exceeds_daily_limit($from, $to, $amount_discoin)
    {
        if (time() - $first_transaction_time > TRANSACTION_LIMIT_RESET)
        {
            $this->daily_exchanges = array();
            $this->first_transaction_time = time();
            $this->save();
        }
        return exceeds_limit($amount_discoin, \MacDue\Util\get($daily_exchanges[$to->currency_code], 0), $to->limit_user);
    }
  
    public function exceeds_global_limit($from, $to, $amount_discoin)
    {
        if (time() - $to->first_transaction_time > TRANSACTION_LIMIT_RESET)
        {
            $to->exchanged_today = 0;
            $to->first_transaction_time = time();
            $to->save();
        }
        return exceeds_limit($amount_discoin, $to->exchanged_today, $to->limit_global);
    }
    
    public function log_transaction($transaction)
    {
        $this->transactions[] = $transaction->receipt;
        $target_currency = $transaction->target;
        if (!isset($this->daily_exchanges[$target_currency]))
        {
            $this->daily_exchanges[$target_currency] = $transaction->amount;
        }
        else
        {
            $this->daily_exchanges[$target_currency] += $transaction->amount;
        }
        $this->save();
    }
    
    public function save()
    {
        \MacDue\DB\upsert("users", $this->id, $this);
    }
}


function get_user($id)
{
    $user_data = \MacDue\DB\get_collection_data("bots", ["id" => $id]);
    if (sizeof($user_data) == 0)
        return null;
    return User::load($user_data);
}


function add_user($user)
{
    $user = get_user($user["id"]);
    if (!is_null($user))
    {
        $burner_emails = file_get_contents(BURNER_EMAILS);
        $email_domain = \MacDue\Util\strip(explode("@", $user["email"])[1]);
        if (\MacDue\Util\str_contains($burner_emails, $email_domain)) 
        {
            echo "Nope! Please use a real email to verify with Discoin!";
            return False;
        }
        else
        {
            echo "Verified! You can now use Discoin!";
            return new User($user["id"]);
        }
    }
    else 
    {
        echo "You're already verified! :D";
    }
}

?>
