<?php
/**
 * Crappy Discord Auth implementation.
 * (there are not any docs for this)
 *
 * @author MacDue
 */
require_once ("discordstuff.php");
require_once ("auth.php");

session_name('discoin_auth');
$discord_auth = new DiscordAuth(['clientId' => CLIENT_ID, 'clientSecret' => CLIENT_SECRET, 'redirectUri' => REDIRECT_URL, ]);
session_start();


// If on the verify page attempt to get auth and verify the user.
if (startsWith($_SERVER['REQUEST_URI'], '/verify')) {
    if (!isset($_SESSION['access_token'])) {
        if (isset($_GET['code'])) {
            $token = $discord_auth->provider->getAccessToken('authorization_code', ['code' => $_GET['code'], ]);
            $_SESSION['access_token'] = $token;
        }
        else
        if (!isset($_GET['error'])) {
            header('Location: ' . $discord_auth->get_auth_url());
            die();
        }
    }

    // Login worked!

    redirect();
    var_dump($discord_auth->get_user_details());
    echo "Login OKAY!";
}



if (isset($_SESSION['access_token'])) {
    if (!$discord_auth->check_auth()) {
        logout();
    }
}



function redirect()
{

    // NOT IMPLEMENTED

}



function logout()
{
    session_destroy();
    redirect();
}

?>
