<?php
namespace Discoin\Bots;

require_once __DIR__."/../scripts/dbconn.php";
require_once __DIR__."/../scripts/util.php";
require_once __DIR__."/discoin.php";


class Bot extends \Discoin\Object 
{
  
    public $owner;
    public $currency_code;
    public $to_discoin;
    public $from_discoin;
    public $auth_key;
    
    
    function __construct($owner, $name, $currency_code, $to_discoin, $from_discoin)
    {
        $this->owner = $owner;
        $this->name = $name;
        $this->currency_code = $currency_code;
        $this->to_discoin = $to_discoin;
        $this->from_discoin = $from_discoin;
        $this->auth_key = $this->generate_api_key();
        $this->save();
    }
    
    public function generate_api_key() {
        return hash('sha256',"DisnodeTeamSucks".time().$this->owner);
    }
    
    
    public function update_rates($to_discoin, $from_discoin)
    {
        $this->to_discoin = $to_discoin;
        $this->from_discoin = $from_discoin;
        $this->save();
    }
    
    public function get_id(){
        return $this->owner.'/'.$this->name;
    }
    
    public function __toString(){
        return "$this->name: 1 $this->currency_code => $this->to_discoin Discoin => $this->from_discoin";
    }
    
    public function save()
    {

        upsert("bots", $this->get_id(), $this);
    }
    
}


function add_bot($owner, $name, $currency_code, $to_discoin, $from_discoin)
{
    global $discord_auth;
    
    require_once("../scripts/discordauth.php");
    $user_info = $discord_auth->get_user_details();
    
    if (!is_owner($user_info["id"]))
    {
        return False;
    }
    
    $bot = new Bot($owner, $name, $currency_code, $to_discoin, $from_discoin);
    return $bot;
}


function get_bots()
{
    $bots = \MacDue\DB\get_collection_data("bots");
    foreach ($bots as $id => $bot_data)
    {
        $bots[$id] = Bot::load($bot_data);
    }
    return $bots;
}


function show_rates()
{
    header("Content-Type: text/plain");
    $rates = "Current exchange rates for Discoin follows:\n\n";
    foreach (get_bots() as $bot) 
    {
        $rates .= "$bot\n";
    }
    $rates .= "\n";
    $rates .= "Note that certain transaction limits may exist. Details will be displayed when a transaction is approved.";
    echo $rates;
}

?>
