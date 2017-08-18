<?php
namespace Discord;

require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__."/util.php";

/**
 * Basic implementation for Discord stuff.
 *
 * @author MacDue
 */
 
 
/**
 * A Discord embed object
 *
 * @param string $title The title of the embed
 * @param int $colour An integer colour value
 * @param string $type The type of the embed defaults to ricg
 * @author MacDue
 */
class Embed
{
    public $title = null;
    public $type = null;
    public $description = null;
    public $url = null;
    public $timestamp = null;
    public $color = null;
    private $fields = null;
    private $footer = null;
    private $image = null;
    private $thumbnail = null;
    private $video = null;
    private $provider = null;
    private $author = null;
    
    function __construct($title, $colour, $type = "rich")
    {
        $this->title = $title;
        $this->color = $colour;
        $this->type = $type;
    }
    
    /**
     * Adds a field to an embed
     *
     * @param string $name The name of the field
     * @param string $value The field content
     * @param boolean $inline Whether the field is inline or not
     * @author MacDue
     */
    public function add_field($name, $value, $inline = False)
    {
        if (is_null($this->fields)) $this->fields = array();
        $this->fields[] = array(
            'name' => $name,
            'value' => $value,
            'inline' => $inline
        );
    }
    
    /**
     * Sets the footer of the embed
     *
     * @param string $text The footer text
     * @param string $icon_url A url to an image for the footer
     * @author MacDue
     */
    public function set_footer($text, $icon_url = "")
    {
        $this->footer = array(
            'text' => $text,
            'icon_url' => $icon_url
        );
    }
    
    /**
     * Sets the main image of the embed
     *
     * @param string $url A url to an image
     * @author MacDue
     */
    public function set_image($url)
    {
        $this->image = array(
            'url' => $url
        );
    }
    
    /**
     * Sets the thumbnail of the embed
     *
     * @param string $url A url to an image
     * @author MacDue
     */
    public function set_thumbnail($url)
    {
        $this->thumbnail = array(
            'url' => $url
        );
    }
    
    /**
     * Sets the embeds video
     *
     * @param string $url A url to a video
     * @author MacDue
     */
    public function set_video($url)
    {
        $this->video = array(
            'url' => $url
        );
    }
    
    /**
     * Sets the embed provider
     * (not sure what that is)
     *
     * @param string $name Providers name
     * @param string $url A url?
     * @author MacDue
     */
    public function set_provider($name, $url)

    {
        $this->provider = array(
            'name' => $name,
            'url' => $url
        );
    }
    
    /**
     * Sets the embed author
     *
     * @param string $name Author name
     * @param string $url A url to the authors site or whatever
     * @paran string $icon_url A url to an image icon
     * @author MacDue
     */
    public function set_author($name, $url = "", $icon_url = "")
    {
        $this->author = array(
            'name' => $name,
            'url' => $url,
            'icon_url' => $icon_url
        );
    }
    
    public function toArray()
    {
        $vars = get_object_vars($this);
        $final_vars = array();
        foreach($vars as $name => $var) {
            if (!is_null($var)) $final_vars[$name] = $var;
        }
        return $final_vars;
    }
}


/**
 * Send a discord webhook!
 *
 * @param string $webhook_url The webhooks url (given when the webhook was created)
 * @param array[mixed] $params The webhook content (supports embed, content or both)
 *
 * Example
 * <code>
 * $params = array(
 *   'embeds'      => [$embed, $another_embed],  // An array of Embed objects
 *   'content'   => 'This is a message!'  // A simple text message.
 * );
 * </code>
 *
 * You only need to pass embeds or content (though you can do both if you like).
 * @author MacDue
 */
function send_webhook($webhook_url, $params)
{
    $webhook = array();
    if (isset($params["embeds"])) {
        $webhook["embeds"] = array();
        foreach($params["embeds"] as $embed) $webhook["embeds"][] = $embed->toArray();
    }
    unset($params["embeds"]);
    $webhook = array_merge($webhook, $params);
    $options = array(
        'http' => array(
            'header' => "Content-Type: application/json\r\n" . "Accept: application/json\r\n",
            'method' => 'POST',
            'content' => json_encode($webhook)
        )
    );
    $context = stream_context_create($options);
    $result = file_get_contents($webhook_url, False, $context);
    if ($result === False) { /* TODO: Handle error */
    }
}


/**
 * Basic Discord auth implementation
 *
 * @param array[string] $client_details Client details
 * (clientId, clientSecret, redirectUri)
 * @param string $scopes The scopes the client needs.
 * @author MacDue
 */
class DiscordAuth
{
    public $provider;
    public $scopes;

    public function __construct($client_details, $scopes = ['identify', 'email'])
    {
        $client_details['scope'] = $scopes;
        $this->provider = new \Discord\OAuth\Discord($client_details);
        $this->scopes = $scopes;
    }
    
    /**
     * Crappy function to attempt to check for auth and try again if we lose it.
     */
    public function check_auth()
    {
        if (isset($_SESSION['access_token'])) {
            try {
                $this->provider->getResourceOwnerDetailsUrl($_SESSION['access_token']);
            }
            catch(DiscordRequestException $e) {
                $refresh = $this->provider->getAccessToken('refresh_token', 
                                                           ['refresh_token' => $getOldTokenFromMemory->getRefreshToken()]);
                $_SESSION['access_token'] = $refresh;
                try {
                    $this->provider->getResourceOwnerDetailsUrl($_SESSION['access_token']);
                }
                catch(DiscordRequestException $e) {
                    return False;
                }
            }
            return True;
        }
        return False;
    }
    
    /**
     * Get the logged in user details.
     * Will return null if there is no logged in user.
     *
     * return array[mixed] The users details.
     */
    public function get_user_details()
    {
        if ($this->check_auth() && isset($_SESSION['access_token'])) 
            return $this->provider->getResourceOwner($_SESSION['access_token'])->toArray();
        else return null;
    }
    
    /**
     * Returns the login status.
     * With the auth token or login url.
     *
     * return array[string] Login status
     */
    public function get_auth()
    {
        if (!$this->check_auth() || !isset($_SESSION['access_token'])) {
            return array(
                'login' => False,
                'authURL' => get_auth_url()
            );
        }
        else {
            return array(
                'login' => True,
                'token' => $_SESSION['access_token']
            );
        }
    }
    
    
    public function logged_in()
    {
        return $this->get_auth()["login"];
    }
    
    /**
     * Returns the url a user needs to login via Discord
     *
     * return $string Login url
     */
    public function get_auth_url()
    {
        return $this->provider->getAuthorizationUrl(array(
            'scope' => $this->scopes
        ));
    }
    
    
    public function get_access_token($code)
    {
        return $this->provider->getAccessToken('authorization_code', ['code' => $code]); 
    }
}
?>
