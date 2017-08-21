<?php
// DEBUG Logout thing.
$request = "";
require_once __DIR__."/discordauth.php";

if ($discord_auth->logged_in())
    \Discoin\Auth\logout();
?>
