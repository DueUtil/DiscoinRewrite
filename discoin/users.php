<?php
/*
 * Stuff to handle Discoin users.
 * 
 * @author MacDue
 */
namespace Discoin\Users;

require_once __DIR__."/discoin.php";
require_once __DIR__."/../scripts/dbconn.php";
require_once __DIR__."/../scripts/util.php";

use function \MacDue\Util\get as get;
use \Discoin\Transactions\Transaction as Transaction;

define("BURNER_EMAILS", "https://raw.githubusercontent.com/wesbos/burner-email-providers/master/emails.txt");


/*
 * A Discoin user
 * 
 * @param string $id A Discord user ID
 * 
 * @author MacDue
 */
class User extends \Discoin\Object implements \Discoin\Transactions\iHasTransactions
{
    public $id;
    public $daily_exchanges = array();
    // Temporarily public to avoid serialization issues.
    public $first_transaction_time = -1;
    
      
    public function __construct($id)
    {
        $this->id = $id;
        $this->save();
    }
    
    /*
     * If a amount exceeds a daily user limit for a bot
     * 
     * @param \Discoin\Bots\Bot $bot The target bot
     * @parm float $amount_discoin The transaction amount in Discoin
     * 
     * return boolean If it exceeds the limit
     */
    public function exceeds_user_daily_limit($bot, $amount_discoin)
    {
        if (time() - $this->first_transaction_time > TRANSACTION_LIMIT_RESET) {
            // Reset user limits
            $this->daily_exchanges = array();
            $this->first_transaction_time = time();
            $this->save();
        }
        return $amount_discoin > $this->current_limit_for_bot($bot);
    }
  
    /*
     * If a amount exceeds a bot global daily limit
     * 
     * @param \Discoin\Bots\Bot $bot The target bot
     * @parm float $amount_discoin The transaction amount in Discoin
     * 
     * return boolean If it exceeds the limit
     */
    public function exceeds_bot_global_limit($bot, $amount_discoin)
    {
        if (time() - $bot->first_transaction_time > TRANSACTION_LIMIT_RESET) {
            // Reset bot limits
            $bot->exchanged_today = 0;
            $bot->first_transaction_time = time();
            $bot->save();
        }
        return $amount_discoin > $this->current_limit_for_bot($bot, $global=True);
    }

    public function current_limit_for_bot($bot, $global=False)
    {
        // Get with default 0
        $exchanges_to_bot = get($this->daily_exchanges[$bot->currency_code], 0);
        if (!$global)
            return $bot->limit_user - $exchanges_to_bot;
        else
            return $bot->limit_global - $exchanges_to_bot;
    }
    
    /*
     * Log a transction
     * 
     * It will update the users current limits for daily exchanges
     * 
     * @param \Discoin\Transactions\Transaction $transaction A transaction
     */
    public function log_transaction($transaction)
    {
        // Get with default 0
        $exchanges_to_bot = get($this->daily_exchanges[$transaction->target], 0);
        $this->daily_exchanges[$transaction->target] = $exchanges_to_bot + $transaction->amount_discoin;
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
        foreach ($raw_transactions as $transaction_data) {
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
* Exchange: A simple description of the exchange and how the amounts changed.

|| Receipt ID                             || Request Time        || Process Time        || Exchange

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
    if (sizeof($user_data) == 1)
        return User::load($user_data[0]);
    // User not found
    return null;
}


function add_user($discord_user)
{
    $user = get_user($discord_user["id"]);
    // If user is not yet verified
    if (is_null($user)) {
        // Check their email agaist the burner emails
        $burner_emails = explode("\n", file_get_contents(BURNER_EMAILS));
        $email_domain = \MacDue\Util\strip(explode("@", $discord_user["email"])[1]);
        if (in_array($email_domain, $burner_emails)) {
            // Reject burner email
            echo "Nope! Please use a real email to verify with Discoin!";
            return False;
        } else {
            echo "Verified! You can now use Discoin!";
            return new User($discord_user["id"]);
        }
    } else {
        echo "You're already verified! :D";
    }
}

?>
