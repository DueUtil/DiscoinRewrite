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

session_name('discoin_auth');
$discord_auth = new \Discord\DiscordAuth(['clientId' => CLIENT_ID, 
                                          'clientSecret' => CLIENT_SECRET,
                                          'redirectUri' => PROTCOL."$_SERVER[HTTP_HOST]$request"]);
session_start();


// If on the verify page attempt to get auth and verify the user.
if (!isset($_SESSION['access_token']))
{
    if (isset($_GET['code']))
    {
        $token = $discord_auth->get_access_token($_GET['code']);
        $_SESSION['access_token'] = $token;
    }
    else if (!isset($_GET['error']))
    {
        header('Location: ' . $discord_auth->get_auth_url());
        die();
    }
}
// Login worked! (check with $discord_auth->logged_in())


if (isset($_SESSION['access_token']))
{
    if (!$discord_auth->check_auth())
    {
        unauthorized();
    }
}

function logout()
{
    session_destroy();
}

// Return auth.
return $discord_auth;

?>
