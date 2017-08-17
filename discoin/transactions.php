<?php
require_once __DIR__."/../scripts/dbconn.php";


class Transaction
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
        $this->user = $user;
        $this->source = $source;
        $this->target = $target;
        $this->amount = $amount;
        $this->type = $type;
        $this->receipt = get_receipt();
    }
    
    private function get_receipt()
    {
        return sha1(uniqid(time().$this->user, True));
    }
    
    private function save()
    {
        
      
    }
    
}


function create_transaction($user, $source, $target, $amount, $type="normal")
{
    $transaction = new Transaction($user, $source, $target, $amount, $type);
    $transaction->save();
    return $transaction;
}

?>
