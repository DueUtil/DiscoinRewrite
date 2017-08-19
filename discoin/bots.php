<?php
/*
 * Stuff to handle bots (that use Discoin)
 * 
 * @author MacDue
 */
 
namespace Discoin\Bots;

require_once __DIR__."/discoin.php";
require_once __DIR__."/transactions.php";
require_once __DIR__."/../scripts/dbconn.php";
require_once __DIR__."/../scripts/util.php";

use \Discoin\Transactions\Transaction as Transaction;

/*
 * A Bot account for Discoin
 * 
 * @param string $owner The bot owner's ID
 * @param string $name The bot's name
 * @param string $currency_code The bot's currency code
 * @param float $to_discoin Bots currency value in Discoin
 * @param float $from_discoin Discoin value in bot (<= to_discoin)
 * 
 * @author MacDue
 */
class Bot extends \Discoin\Object implements \Discoin\Transactions\iHasTransactions
{  
    public $owner;
    public $currency_code;
    public $to_discoin;
    public $from_discoin;
    public $auth_key;
    public $limit_user;
    public $limit_global;
    public $exchanged_today = 0;
    public $first_transaction_time = -1;
    
    
    // Kinda a long constructor
    function __construct($owner,
                         $name,
                         $currency_code,
                         $to_discoin,
                         $from_discoin,
                         $limit_user=2500,
                         $limit_global=1000000
    ) {
        $this->owner = $owner;
        $this->name = $name;
        $this->currency_code = $currency_code;
        $this->to_discoin = $to_discoin;
        $this->from_discoin = $from_discoin;
        $this->auth_key = $this->generate_api_key();
        $this->save();
    }
    
    // Generates the API key
    private function generate_api_key()
    {
        return hash('sha256',"DisnodeTeamSucks".time().$this->owner);
    }
    
    /*
     * Returns unprocessed transtions for a bot.
     * All returned transactions will be marked as processed.
     * 
     * @author MacDue
     */
    public function get_transactions()
    {
        $raw_transactions = \MacDue\DB\get_collection_data("transactions",
                                                          ["target" => $this->currency_code,
                                                           "processed" => False]);
        $transactions = array();
        
        foreach ($raw_transactions as $transaction_data)
        {
            $transaction = Transaction::load($transaction_data);
            $transaction->processed = True;
            $transaction->process_time = time();
            $transaction->save();
            $transactions[] = $transaction;
        }
        return $transactions;
    }
    
    public function log_transaction($transaction)
    {
        $this->exchanged_today += $transaction->amount_discoin;
        $this->save();
    }
        
    public function __toString()
    {
        return "$this->name: 1 $this->currency_code => $this->to_discoin Discoin => $this->from_discoin $this->currency_code";
    }
    
    public function save()
    {
        \MacDue\DB\upsert("bots", "$this->owner/$this->name", $this);
    }
    
}


function add_bot($owner, $name, $currency_code, $to_discoin, $from_discoin)
{
    global $discord_auth;
    
    require_once __DIR__."/discordauth.php";
    $user_info = $discord_auth->get_user_details();
    
    if (!\Discoin\is_owner($user_info["id"]))
        return False;
    
    return new Bot($owner, $name, $currency_code, $to_discoin, $from_discoin);
}


function get_bots()
{
    $bots = \MacDue\DB\get_collection_data("bots");
    foreach ($bots as $id => $bot_data)
        $bots[$id] = Bot::load($bot_data);
    return $bots;
}


function get_bot($query)
{
    $bot_data = \MacDue\DB\get_collection_data("bots", $query);
    if (sizeof($bot_data) == 0)
        return null;
    return Bot::load($bot_data[0]);
}


function show_rates()
{
    $rates = "Current exchange rates for Discoin follows:\n\n";
    foreach (get_bots() as $bot) 
        $rates .= "$bot\n";
    $rates .= "\n";
    $rates .= "Note that certain transaction limits may exist. Details will be displayed when a transaction is approved.";
    echo $rates;
}

?>
