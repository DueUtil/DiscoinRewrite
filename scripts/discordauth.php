<?php
namespace Discord\Auth;

/**
 * Crappy Discord Auth implementation.
 * (there are not any docs for this)
 *
 * @author MacDue
 */
require_once __DIR__."/discordstuff.php";
require_once __DIR__."/../auth.php";
require_once __DIR__."/../scripts/util.php";


session_name('discoin_auth');
$discord_auth = new \Discord\DiscordAuth(['clientId' => CLIENT_ID, 'clientSecret' => CLIENT_SECRET,
                                          'redirectUri' => "http://$_SERVER[HTTP_HOST]$request"]);
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
// Login worked!


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

return $discord_auth;

?>
