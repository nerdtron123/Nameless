<?php 
/*
 *	Made by Samerton
 *  http://worldscapemc.co.uk
 *
 *  License: MIT
 */

// Index page
$page = 'index'; // for navbar

// Server query
// Get the main IP
$main_ip = $queries->getWhere('mc_servers', array('is_default', '=', 1));
$pre17 	 = $main_ip[0]->pre;
$main_ip = htmlspecialchars($main_ip[0]->ip);

/*
 *  Resolve real IP address (to support SRV records)
 */
require('core/integration/status/SRVResolver.php');
$parts = explode(':', $main_ip);
if(count($parts) == 1){
	$domain = $parts[0];
	$query_ip = SRVResolver($domain);
	$parts = explode(':', $query_ip);
	$default_ip = $parts[0];
	$default_port = $parts[1];
} else if(count($parts) == 2){
	$domain = $parts[0];
	$default_ip = $parts[0];
	$default_port = $parts[1];
	$port = $parts[1];
} else {
	echo 'Invalid IP';
	die();
}

// IP to display
if((!isset($port))||($port == "25565")){
	$address = $domain;
} else {
	$address = $domain . ':' . $port;
}


$connect_with = str_replace('{x}', htmlspecialchars($domain), $general_language['connect_with']);
$smarty->assign('CONNECT_WITH', $connect_with);

// Query the main IP
// Are we using the built-in query or an external API?
$external_query = $queries->getWhere('settings', array('name', '=', 'external_query'));
$external_query = $external_query[0]->value;

if($external_query == 'false'){
	// Built in query, continue as normal
	require('core/integration/status/global.php'); 
} else {
	// External query
	$cache = new Cache();
	require('core/integration/status/global_external.php');
}

if(empty($Info)){
	// Unable to query, offline
	$smarty->assign('MAIN_ONLINE', 0);
} else {
	// Able to query, online
	$smarty->assign('MAIN_ONLINE', 1);
}

// Player count
if($pre17 == 0){
	$player_count = $Info['players']['online'];
} else {
	$player_count = $Info['Players'];
}

$smarty->assign('PLAYERS_ONLINE', str_replace('{x}', $player_count, $general_language['x_players_online']));
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="The homepage for the <?php echo $sitename; ?> community">
    <meta name="author" content="Samerton">
	<?php if(isset($custom_meta)){ echo $custom_meta; } ?>

    <title><?php echo $sitename; ?> &bull; <?php echo $navbar_language['home']; ?></title>
	
	<?php
	// Generate header and navbar content
	require('core/includes/template/generate.php');
	?>
	
	<!-- Custom style -->
	<style>
	html {
		overflow-y: scroll;
	}
	.jumbotron {
		margin-bottom: 0px;
		background-image: url(core/assets/img/background-1920x828.jpg);
		background-position: 0% 25%;
		background-size: cover;
		background-repeat: no-repeat;
		color: white;
	}
	</style>
	
  </head>
  <body>
	<?php
	// Load navbar
	$smarty->display('styles/templates/' . $template . '/navbar.tpl');

	// Session
	if(Session::exists('home')){
		$smarty->assign('SESSION_FLASH', Session::flash('home'));
	} else {
		$smarty->assign('SESSION_FLASH', '');
	}
	
	// Generate code for page
	$jumbotron_content = '
	<h1>WorldscapeMC</h1>
	<p>There are currently 2 players online</p>
	<p>Join with <strong>play.worldscapemc.com</strong></p>
	';
	$smarty->assign('SITENAME', $sitename);
	$smarty->assign('JUMBOTRON_CONTENT', $jumbotron_content);
	$smarty->assign('NEWS', $general_language['news']);
	$smarty->assign('SOCIAL', $general_language['social']);

	// Get news content
	$forum = new Forum(); // Initialise the forum to get the latest news
	$latest_news = $forum->getLatestNews(5); // Get latest 5 items
	
	// HTML Purifier
	require('core/includes/htmlpurifier/HTMLPurifier.standalone.php');
	$config = HTMLPurifier_Config::createDefault();
	$config->set('HTML.Doctype', 'XHTML 1.0 Transitional');
	$config->set('URI.DisableExternalResources', false);
	$config->set('URI.DisableResources', false);
	$config->set('HTML.Allowed', 'u,p,b,a,i,small,blockquote,span[style],span[class],p,strong,em,li,ul,ol,div[align],br,img');
	$config->set('CSS.AllowedProperties', array('text-align', 'float', 'color','background-color', 'background', 'font-size', 'font-family', 'text-decoration', 'font-weight', 'font-style', 'font-size'));
	$config->set('HTML.AllowedAttributes', 'href, src, height, target, width, alt, class, *.style');
	$config->set('HTML.SafeIframe', true);
	$config->set('URI.SafeIframeRegexp', '%^(https?:)?//(www\.youtube(?:-nocookie)?\.com/embed/|player\.vimeo\.com/video/)%');
	$purifier = new HTMLPurifier($config);
	
	$news = array();
	foreach($latest_news as $item){
		// Get poster's avatar
		$post_user = $queries->getWhere("users", array("id", "=", $item["author"]));
		$has_avatar = $post_user[0]->has_avatar;
		if($has_avatar == '0'){ 
			$avatar = '<img class="img-rounded" src="https://cravatar.eu/avatar/' . htmlspecialchars($user->idToMCName($item["author"])) . '/25.png" />';
		} else {
			$avatar = '<img class="img-rounded" style="width:25px; height:25px;" src="' . $user->getAvatar($item["author"], "../") . '" />';
		}
		
		$news[] = array(
			'id' => $item['topic_id'],
			'date' => date('d M Y, H:i', $item['topic_date']),
			'title' => htmlspecialchars($item['topic_title']),
			'views' => $item['topic_views'],
			'replies' => $item['replies'],
			'author_mcname' => htmlspecialchars($user->idToMCName($item['author'])),
			'author_username' => htmlspecialchars($user->idToName($item['author'])),
			'author_avatar' => $avatar,
			'content' => $purifier->purify(htmlspecialchars_decode($item['content']))
		);
	}

	$smarty->assign('newsArray', $news);

	// Twitter feed
	if(isset($twitter_feed_query) && $twitter_feed_query[0]->value !== 'null'){
		if($twitter_feed_query[0]->value == '0'){
			// Disabled
			$twitter = '';
		} else {
			// Enabled
			$twitter = '<a class="twitter-timeline" data-dnt="true" href="' . htmlspecialchars($twitter_url[0]->value) . '"  data-widget-id="' . htmlspecialchars($twitter_feed_query[0]->value) . '">Tweets</a><script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0],p=/^http:/.test(d.location)?\'http\':\'https\';if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src=p+"://platform.twitter.com/widgets.js";fjs.parentNode.insertBefore(js,fjs);}}(document,"script","twitter-wjs");</script>';
		}
	}
	
	$smarty->assign('TWITTER_FEED', $twitter);
	
	// Voice server module
	$viewer = '';

	if(isset($voice_server_enabled) && $voice_server_enabled == 'teamspeak'){
		// Teamspeak module
		require_once('core/includes/TeamSpeak3/TeamSpeak3.php');
		
		try {
			// connect to local server, authenticate and spawn an object for the virtual server on a defined port
			$ts3_VirtualServer = TeamSpeak3::factory('serverquery://' . $voice_server_username . ':' . $voice_server_password . '@' . $voice_server_ip . ':' . $voice_server_port . '/?server_port=' . $voice_virtual_server_port . '&nickname=Query');

			// build and display HTML treeview using custom image paths (remote icons will be embedded using data URI sheme)
			$viewer = $ts3_VirtualServer->getViewer(new TeamSpeak3_Viewer_Html("core/assets/img/ts3/viewer/", "core/assets/img/ts3/flags/", "data:image"));
		} catch(Exception $e) {
			$viewer = '<div class="alert alert-warning">' . $e->getMessage() . '</div>';
		}
	}
	
	$smarty->assign('VOICE_VIEWER_TITLE', 'TeamSpeak');
	$smarty->assign('VOICE_VIEWER', $viewer);
	
	$smarty->display('styles/templates/' . $template . '/index.tpl');

	// Footer
	require('core/includes/template/footer.php');
	$smarty->display('styles/templates/' . $template . '/footer.tpl');
	
	// Scripts 
	require('core/includes/template/scripts.php');
	?>
  </body>
</html>
