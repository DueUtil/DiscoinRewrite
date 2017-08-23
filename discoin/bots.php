<?php
/*
 * Stuff to handle bots (that use Discoin)
 */
namespace Discoin\Bots;

require_once __DIR__."/discoin.php";
require_once __DIR__."/transactions.php";
require_once __DIR__."/../scripts/dbconn.php";
require_once __DIR__."/../scripts/util.php";

use \Discoin\Transactions\Transaction as Transaction;
use function \MacDue\Util\send_json as send_json;


/*
 * A Bot account for Discoin
 * 
 * @param string $owner The bot owner's ID
 * @param string $name The bot's name
 * @param string $currency_code The bot's currency code
 * @param float $to_discoin Bots currency value in Discoin
 * @param float $from_discoin Discoin value in bot (<= to_discoin)
 */
class Bot extends \Discoin\Object implements \Discoin\Transactions\iHasTransactions, \JsonSerializable
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
        $this->limit_user = $limit_user;
        $this->limit_global = $limit_global;
        $this->generate_api_key();
        $this->save();
    }
    
    // Generates the API key
    private function generate_api_key()
    {
        $this->auth_key = hash('sha256',"DisnodeTeamSucks".time().$this->owner);
    }
    
    /*
     * Returns unprocessed transtions for a bot.
     * All returned transactions will be marked as processed.
     */
    public function get_transactions()
    {
        $transactions = \MacDue\DB\get_collection_data("transactions",
                                                       ["target" => $this->currency_code,
                                                        "processed" => False]);
        foreach ($transactions as $transaction)
            $transaction->mark_as_processed();
        return $transactions;
    }
    
    public function log_transaction($transaction)
    {
        $this->exchanged_today += $transaction->amount_discoin;
        $this->save();
    }
        
    public function __toString()
    {
        $rates_format =  '%s: 1.00 %s => %.2f Discoin => %.2f %2$s';
        return sprintf($rates_format, $this->name, $this->currency_code,
                       $this->to_discoin, $this->from_discoin);
    }

    public function jsonSerialize()
    {
        return [$this->name => ["toDiscoin" => $this->to_discoin,
                                "fromDiscoin" => $this->from_discoin]];
    }

    public function get_id() 
    {
        return strtolower("$this->owner/$this->name");
    }
    
    public function save()
    {
        \MacDue\DB\upsert("bots", $this->get_id(), $this);
    }
}


function add_bot($owner, $name, $currency_code, $to_discoin, $from_discoin)
{
    global $discord_auth;
    require_once __DIR__."/discordauth.php";
    $user_info = $discord_auth->get_user_details();
    // Not a Discoin owner. Don't add bot
    if (!\Discoin\is_owner($user_info["id"])) {
        return False;
    }
    return new Bot($owner, $name, $currency_code, $to_discoin, $from_discoin);
}


function get_bots()
{
    $bots = \MacDue\DB\get_collection_data("bots");
    return $bots;
}


function get_bot($query)
{
    return \MacDue\DB\get_object("bots", $query);
}


function show_rates()
{
    $rates = "Current exchange rates for Discoin follows:\n\n";
    foreach (get_bots() as $bot) 
        $rates .= "$bot\n";
    $rates .= "\n";
    $rates .= "Note that certain transaction limits may exist.\n";
    $rates .= "Details will be displayed when a transaction is approved.";
    echo $rates;
}


function show_rates_json()
{
    send_json(get_bots());
}

?>
