<?php
/*
* Simple requests handler.
* This simple script just handles the requests (of course).
* Transactions and stuff will be handled in other files.
* 
* @author MacDue
*/
require_once("scripts/util.php");

$request = rtrim($_SERVER['REQUEST_URI'], "/");
$get_request = $_SERVER['REQUEST_METHOD'] === "GET";


if ($get_request) {
    // GET requests
    if ($request === "")
    { 
        echo "Welcome to Discoin!";
    } else if ($request === "/transactions") 
    {
        echo "Transactions GET";
    } else if (startsWith($request, "/verify")) {
        require_once("scripts/discordauth.php");
    } else{
        echo "Unknown GET request";
    }    
} else {
    // POST requests
    
    if ($request === "/transaction") {
        echo "Transaction POST";
    }else if ($request === "/transaction/reverse") {
        echo "Transaction reverse POST";
    } else {
        echo "Unknown POST request";
    }
}



?>
