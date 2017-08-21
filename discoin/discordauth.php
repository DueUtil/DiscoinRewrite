<?php
/**
 * Crappy Discord Auth implementation.
 * (there are not any docs for this)
 *
 * @author MacDue
 */
namespace Discoin\Auth;

require_once __DIR__."/discoin.php";
require_once __DIR__."/../scripts/discordstuff.php";
require_once __DIR__."/../scripts/util.php";

if (!isset($request))
    die("No request page set!");

session_name('discoin_auth');
$discord_auth = new \Discord\DiscordAuth(['clientId' => CLIENT_ID, 
                                          'clientSecret' => CLIENT_SECRET,
                                          'redirectUri' => PROTCOL."$_SERVER[HTTP_HOST]$request"]);
session_start();


// If no auth yet
if (!isset($_SESSION['access_token'])) {
    if (isset($_GET['code'])) {
        // Attempt to get access from code
        try {
            $token = $discord_auth->get_access_token($_GET['code']);
            $_SESSION['access_token'] = $token;
        } catch (\Discord\OAuth\DiscordRequestException $discord_error) {
            echo "Ծ_Ծ Could not login! Outdated/invalid code?";
        }
    } else if (isset($_GET['error'])) {
        echo ".·´¯`(>▂<)´¯`·. Login error?! Did you cancle on me?!";
    } else {
        // Login redirect
        header('Location: ' . $discord_auth->get_auth_url());
        die();
    }

} else {
    // Just in case we lose the login.
    if (!$discord_auth->logged_in()) {
        logout();
        \MacDue\Util\unauthorized();
    }
}


function logout()
{
    session_destroy();
}

// Return auth.
return $discord_auth;

?>
