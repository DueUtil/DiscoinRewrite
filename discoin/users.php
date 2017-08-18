<?php
/*
 * Stuff to handle Discoin users.
 * 
 * @author MacDue
 */

namespace Discoin\Users;

require_once __DIR__."/../scripts/dbconn.php";
require_once __DIR__."/../scripts/util.php";
require_once __DIR__."/discoin.php";

use \Discoin\Transactions\Transaction as Transaction;

define("BURNER_EMAILS", "https://raw.githubusercontent.com/wesbos/burner-email-providers/master/emails.txt");


/*
 * A Discoin user
 * 
 * @param string $id A Discord user ID
 * 
 * @author MacDue
 */
class User extends \Discoin\Object
{
    public $id;
    public $daily_exchanges = array();
    private $first_transaction_time = -1;
    
      
    public function __construct($id)
    {
        $this->id = $id;
        $this->save();
    }
    
    private function exceeds_limit($amount_discoin, $exchanged, $limit)
    {
        return $exchanged + $amount_discoin > $limit;
    }
    
    /*
     * If a amount exceeds a daily user limit for a bot
     * 
     * @param \Discoin\Bots\Bot $from The source bot
     * @param \Discoin\Bots\Bot $to The target bot
     * @parm float $amount_discoin The transaction amount in Discoin
     * 
     * return boolean If it exceeds the limit
     */
    public function exceeds_daily_limit($from, $to, $amount_discoin)
    {
        if (time() - $this->first_transaction_time > TRANSACTION_LIMIT_RESET)
        {
            $this->daily_exchanges = array();
            $this->first_transaction_time = time();
            $this->save();
        }
        return $this->exceeds_limit($amount_discoin, \MacDue\Util\get($daily_exchanges[$to->currency_code], 0), $to->limit_user);
    }
  
    /*
     * If a amount exceeds a bot global daily limit
     * 
     * @param \Discoin\Bots\Bot $from The source bot
     * @param \Discoin\Bots\Bot $to The target bot
     * @parm float $amount_discoin The transaction amount in Discoin
     * 
     * return boolean If it exceeds the limit
     */
    public function exceeds_global_limit($from, $to, $amount_discoin)
    {
        if (time() - $to->first_transaction_time > TRANSACTION_LIMIT_RESET)
        {
            $to->exchanged_today = 0;
            $to->first_transaction_time = time();
            $to->save();
        }
        return $this->exceeds_limit($amount_discoin, $to->exchanged_today, $to->limit_global);
    }
    
    /*
     * Log a transction
     * 
     * @param \Discoin\Transactions\Transaction $from The source bot
     */
    public function log_transaction($transaction)
    {
        $target_currency = $transaction->target;
        if (!isset($this->daily_exchanges[$target_currency]))
        {
            $this->daily_exchanges[$target_currency] = $transaction->amount_discoin;
        }
        else
        {
            $this->daily_exchanges[$target_currency] += $transaction->amount_discoin;
        }
        $this->save();
    }
    
    /*
     * Get all transactions made by a player
     *      
     * return array(\Discoin\Transaction\Transaction) The transactions.
     */
    public function get_transactions()
    {
        $raw_transactions = \MacDue\DB\get_collection_data("transactions", ["user" => $this->id]);
        $transactions = array();
        foreach ($raw_transactions as $transaction_data)
        {
            $transaction = Transaction::load($transaction_data);
            $transactions[] = $transaction;
        }
        return $transactions;
    }
    
    
    /*
     * Output a crappy text log of the users transactions
     */
    public function show_transactions()
    {
// Cannot indent heredoc /r/lolphp
$record = <<<EOT
Hello! Here's your transaction record.

--- LEGEND ---
* Request Time: When you started the exchange.
* Reception Time: When the target bot processed your exchange.
* From/To: Currency codes.
* Amount: In Discoin.
      
|| Receipt ID                             || Request Time        || Process Time        || From ||  To  || Amount

EOT;
        foreach ($this->get_transactions() as $transaction)
            $record .= "$transaction\n";
      
        echo $record;
    }
    
    public function save()
    {
        \MacDue\DB\upsert("users", $this->id, $this);
    }
}


function get_user($id)
{
    $user_data = \MacDue\DB\get_collection_data("users", ["id" => $id]);
    if (sizeof($user_data) == 0)
        return null;
    return User::load($user_data[0]);
}


function add_user($discord_user)
{
    $user = get_user($discord_user["id"]);
    if (is_null($user))
    {
        $burner_emails = file_get_contents(BURNER_EMAILS);
        $email_domain = \MacDue\Util\strip(explode("@", $discord_user["email"])[1]);
        if (\MacDue\Util\str_contains($burner_emails, $email_domain)) 
        {
            echo "Nope! Please use a real email to verify with Discoin!";
            return False;
        }
        else
        {
            echo "Verified! You can now use Discoin!";
            return new User($discord_user["id"]);
        }
    }
    else 
    {
        echo "You're already verified! :D";
    }
}

?>
