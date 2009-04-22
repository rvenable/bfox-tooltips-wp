<?php
	require_once('utility.php');
	require_once("bfox-settings.php");

	require_once('biblerefs/ref.php');
	include_once('translations/bfox-translations.php');
	include_once('admin/admin-tools.php');
	include_once('blog/blog.php');
	include_once('bible/load.php');
	include_once('site/site.php');

	// TODO3: These files are probably obsolete
	require_once('site/message.php');

	require_once BFOX_DIR . '/query.php';

	function bfox_add_head_files()
	{
		?>
		<link rel="stylesheet" href="<?php echo get_option('siteurl') ?>/wp-content/mu-plugins/biblefox/scripture.css" type="text/css"/>
		<?php
	}
	add_action('wp_head', 'bfox_add_head_files');

	// TODO3: Move this function to somewhere specific to the bible viewer
	function bfox_add_admin_head_files()
	{
		// use JavaScript SACK library for Ajax
		wp_print_scripts( array( 'sack' , 'jquery'));

		$url = get_option('siteurl');
		?>
		<link rel="stylesheet" href="<?php echo $url; ?>/wp-content/mu-plugins/biblefox/bible/bible.css" type="text/css"/>
		<script type="text/javascript" src="<?php echo $url; ?>/wp-content/mu-plugins/biblefox/bible/bible.js"></script>
		<?php
	}
	add_action('wp_head', 'bfox_add_admin_head_files');

?>