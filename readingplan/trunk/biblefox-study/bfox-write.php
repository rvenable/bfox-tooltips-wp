<?php
	
	function bfox_create_bible_ref_table()
	{
		// Note this function creates the table with dbDelta() which apparently has some pickiness
		// See http://codex.wordpress.org/Creating_Tables_with_Plugins#Creating_or_Updating_the_Table

		$table_name = 
		$sql = "CREATE TABLE " . BFOX_TABLE_BIBLE_REF . " (
				post_id int,
				ref_order int,
				verse_begin int,
				verse_end int
			);";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}

	function bfox_get_post_bible_refs($post_id = 0)
	{
		global $wpdb;
		$table_name = BFOX_TABLE_BIBLE_REF;

		// If the table does not exist then there are obviously no bible references
		if ((0 == $post_id) || ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name))
			return;

		$select = $wpdb->prepare("SELECT verse_begin, verse_end FROM $table_name WHERE post_id = %d ORDER BY ref_order ASC", $post_id);
		$ranges = $wpdb->get_results($select, ARRAY_N);

		$refs = array();
		foreach ($ranges as $range)
		{
			$refs[] = bfox_get_ref_for_range($range);
		}
		return $refs;
	}
	
	function bfox_set_post_bible_refs($post_id, $refs)
	{
		global $wpdb;
		$table_name = BFOX_TABLE_BIBLE_REF;
		$id = 1;

		// If the table doesn't exist create it, otherwise remove any previous entries for this post
		if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name)
			bfox_create_bible_ref_table();
		else
			$wpdb->query($wpdb->prepare("DELETE FROM $table_name WHERE post_id = %d", $post_id));

		$ref_order = 0;
		foreach ($refs as $ref)
		{
			$range = bfox_get_unique_id_range($ref);
			$insert = $wpdb->prepare("INSERT INTO $table_name (post_id, ref_order, verse_begin, verse_end) VALUES (%d, %d, %d, %d)", $post_id, $ref_order, $range[0], $range[1]);
			$wpdb->query($insert);
			$ref_order++;
		}
	}

	// Function for creating the form field to edit a post's bible references
	function bfox_form_edit_bible_refs()
	{
		if (isset($_GET['bible_ref']))
			$refStr = $_GET['bible_ref'];
		else
		{
			global $post_ID;
			$refs = bfox_get_post_bible_refs($post_ID);
			if (0 < count($refs))
				$refStr = bfox_get_reflist_str($refs);
			else
				$refStr = '';
		}
	?>
<div id="biblerefdiv" class="postbox">
<h3><?php _e('Scripture References'); ?></h3>
<div class="inside">
<input name="bible_ref" type="text" size="25" id="bible_ref" value="<?php echo attribute_escape($refStr); ?>" />
</div>
</div>
<?php
	}
	
	
	
?>