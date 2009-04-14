<?php

define('BFOX_TRANSLATIONS_TABLE', BFOX_BASE_TABLE_PREFIX . 'translations');
define('BFOX_BOOK_COUNTS_TABLE', BFOX_BASE_TABLE_PREFIX . 'book_counts');
define('BFOX_TRANSLATION_INDEX_TABLE', BFOX_BASE_TABLE_PREFIX . 'trans_index');
define('BFOX_TRANSLATIONS_DIR', BFOX_DIR . '/translations');

/*
 * FULLTEXT Indexing Workaround:
 *
 * For bible searching, this plugin uses MySQL FULLTEXT indexes. However, the FULLTEXT
 * indexer exludes words that are too short or are considered 'stop words'. To counteract
 * this, we add a prefix to those words so that they are long enough and are not stop words,
 * and therefore will be indexed.
 */

/*
 * BFOX_FT_INDEX_PREFIX is added to the beginning of words which don't fit the MySQL
 * FULLTEXT indexing criteria of minimum length and matching a stopword.
 * Thus, by adding this prefix to the word, the word passes the indexing criteria,
 * and thereby can be succesfully indexed (see Translation::get_index_words()).
 *
 * BFOX_FT_INDEX_PREFIX must be long enough to increase any word to at least the minimum
 * string length. For instance, if the min length is 4 (BFOX_FT_MIN_WORD_LEN should
 * reflect this), BFOX_FT_INDEX_PREFIX could be length 3, so that even 1 letter words
 * are prefixed with 3 additional letters to reach the minimum length of 4 letters.
 *
 * The characters in BFOX_FT_INDEX_PREFIX must be unique enough so that when prefixing
 * any word, the new word will not be a MySql stop word.
 *
 * This can be over-ridden in the wp-config file for different server setups.
 * Remember to rebuild the translation indexes if modifying this value.
 */
if (!defined('BFOX_FT_INDEX_PREFIX'))
	define('BFOX_FT_INDEX_PREFIX', 'bfx');

/*
 * Define the minimum word length for full text searches to be 4 by default.
 * Any word shorter than length BFOX_FT_MIN_WORD_LEN will be prefixed with
 * BFOX_FT_INDEX_PREFIX so that they will be long enough to be indexed by
 * MySQL's FULLTEXT search.
 *
 * This can be over-ridden in the wp-config file for different server setups.
 * Remember to rebuild the translation indexes if modifying this value.
 */
if (!defined('BFOX_FT_MIN_WORD_LEN'))
	define('BFOX_FT_MIN_WORD_LEN', 4);

/*
 * Define the list of MySQL FULLTEXT stop words to ignore. Any of these words
 * will be prefixed with BFOX_FT_INDEX_PREFIX so that MySQL won't detect them
 * as stop words and they will be successfully indexed.
 *
 * This can be over-ridden in the wp-config file for different server setups.
 * Remember to rebuild the translation indexes if modifying this value.
 */
global $bfox_ft_stopwords;
if (empty($bfox_ft_stopwords)) include('stopwords.php');

/**
 * A class for individual bible translations
 *
 */
class Translation
{
	public $id, $short_name, $long_name, $is_default, $is_enabled;
	public $table;

	/**
	 * Construct an instance using an stdClass object (as returned by querying the Translation::translation_table DB table)
	 *
	 * @param stdClass $translation
	 */
	function __construct(stdClass $translation)
	{
		$this->id = (int) $translation->id;
		$this->short_name = (string) $translation->short_name;
		$this->long_name = (string) $translation->long_name;
		$this->is_default = (bool) $translation->is_default;
		$this->is_enabled = (bool) $translation->is_enabled;

		// Set the translation table if it exists
		$table = Translations::get_translation_table_name($this->id);
		if (BfoxUtility::does_table_exist($table)) $this->table = $table;
		else $this->table = '';
	}

	/**
	 * Get the verse content for some bible references
	 *
	 * @param string $ref_where SQL WHERE statement as returned from BibleRefs::sql_where()
	 * @return string Formatted bible verse output
	 */
	public function get_verses($ref_where)
	{
		$verses = array();

		// We can only grab verses if the verse data exists
		if (!empty($this->table))
		{
			global $wpdb;
			$verses = $wpdb->get_results("SELECT unique_id, book_id, chapter_id, verse_id, verse FROM $this->table WHERE $ref_where");
		}

		return $verses;
	}

	/**
	 * Get the verse content for a sequence of chapters
	 *
	 * @param integer $book
	 * @param integer $chapter1
	 * @param integer $chapter2
	 * @param string $visible SQL WHERE statement to determine which scriptures are visible (ex. as returned from BibleRefs::sql_where())
	 * @return string Formatted bible verse output
	 */
	public function get_chapter_verses($book, $chapter1, $chapter2, $visible)
	{
		$chapters = array();

		// We can only grab verses if the verse data exists
		if (!empty($this->table))
		{
			global $wpdb;
			$verses = (array) $wpdb->get_results($wpdb->prepare("
				SELECT unique_id, chapter_id, verse_id, verse, ($visible) as visible
				FROM $this->table
				WHERE book_id = %d AND chapter_id >= %d AND chapter_id <= %d",
				$book, $chapter1, $chapter2));

			foreach ($verses as $verse) $chapters[$verse->chapter_id] []= $verse;
		}

		return $chapters;
	}
}

/**
 * Manages all translations
 *
 */
class Translations
{
	const page = 'bfox-translations';
	const min_user_level = 10;
	const translation_table = BFOX_TRANSLATIONS_TABLE;
	const book_counts_table = BFOX_BOOK_COUNTS_TABLE;
	const index_table = BFOX_TRANSLATION_INDEX_TABLE;
	const dir = BFOX_TRANSLATIONS_DIR;

	/**
	 * Creates the DB table for the translations
	 *
	 */
	public static function create_tables()
	{
		// Note this function creates the table with dbDelta() which apparently has some pickiness
		// See http://codex.wordpress.org/Creating_Tables_with_Plugins#Creating_or_Updating_the_Table

		$sql = "
		CREATE TABLE IF NOT EXISTS " . self::translation_table . " (
			id int unsigned NOT NULL auto_increment,
			short_name varchar(8) NOT NULL default '',
			long_name varchar(128) NOT NULL default '',
			is_default boolean NOT NULL default 0,
			is_enabled boolean NOT NULL default 0,
			PRIMARY KEY  (id)
		);
		CREATE TABLE " . self::book_counts_table . " (
			trans_id int,
			book_id int,
			chapter_id int,
			value int
		);
		";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}

	/**
	 * Returns all the data from the translation table
	 *
	 * @param bool $get_disabled Whether we should include disabled translations in the results
	 * @return unknown
	 */
	public static function get_translations($get_disabled = FALSE)
	{
		global $wpdb;
		if (!$get_disabled) $where = 'WHERE is_enabled = 1';
		$translations = (array) $wpdb->get_results("SELECT * FROM " . self::translation_table . " $where ORDER BY short_name, long_name");

		foreach ($translations as &$translation) $translation = new Translation($translation);

		return $translations;
	}

	/**
	 * Returns the translation data for one particular translation
	 *
	 * @param int $trans_id
	 * @return unknown
	 */
	public static function get_translation($trans_id)
	{
		global $wpdb;
		return new Translation((object) $wpdb->get_row($wpdb->prepare("SELECT * FROM " . self::translation_table . " WHERE id = %d", $trans_id)));
	}

	/**
	 * Returns an array of file names for the translations files in the translation directory
	 *
	 * @return unknown
	 */
	private static function get_translation_files()
	{
		$files = array();

		$translations_dir = opendir(Translations::dir);
		if ($translations_dir)
		{
			while (($file_name = readdir($translations_dir)) !== false)
			{
				if (substr($file_name, -4) == '.xml')
					$files[] = "$file_name";
			}
		}
		@closedir($translations_dir);

		return $files;
	}

	/**
	 * Returns the translation table name for a given translation id
	 *
	 * @param integer $trans_id
	 * @return string
	 */
	public static function get_translation_table_name($trans_id)
	{
		return BFOX_BASE_TABLE_PREFIX . "trans{$trans_id}_verses";
	}

	/**
	 * Creates the verse data table
	 *
	 */
	private static function create_translation_table($table_name)
	{
		// Note this function creates the table with dbDelta() which apparently has some pickiness
		// See http://codex.wordpress.org/Creating_Tables_with_Plugins#Creating_or_Updating_the_Table

		global $wpdb;
		$sql = "
		CREATE TABLE $table_name (
			unique_id int unsigned NOT NULL,
			book_id int unsigned NOT NULL,
			chapter_id int unsigned NOT NULL,
			verse_id int unsigned NOT NULL,
			verse text NOT NULL,
			PRIMARY KEY  (unique_id)
		);
		";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}

	/**
	 * Create the translation index table
	 *
	 */
	public static function create_translation_index_table()
	{
		// TODO3: This function should not be called by admin tools and be private

		// Note this function creates the table with dbDelta() which apparently has some pickiness
		// See http://codex.wordpress.org/Creating_Tables_with_Plugins#Creating_or_Updating_the_Table

		// Creates a FULLTEXT index on the verse data
		$sql = "
		CREATE TABLE " . self::index_table . " (
			unique_id int unsigned NOT NULL,
			book_id int unsigned NOT NULL,
			trans_id int unsigned NOT NULL,
			index_text text NOT NULL,
			FULLTEXT (index_text)
		);
		";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}

	/**
	 * Refresh the index data for a given translation
	 *
	 * @param Translation $trans
	 * @param string $group
	 */
	public static function refresh_translation_index(Translation $trans, $group = 'protest')
	{
		// TODO3: This function should not be called by admin tools and be private

		global $wpdb;

		// Delete all the old index data for this translation
		$wpdb->query($wpdb->prepare("DELETE FROM " . self::index_table . " WHERE trans_id = %d", $trans->id));

		// Add the new index data, one book at a time
		$books = range(BibleGroupPassage::get_first_book($group), BibleGroupPassage::get_last_book($group));
		foreach ($books as $book)
		{
			// Get all the verses to index for this book (we don't index chapter 0 or verse 0)
			$verses = $wpdb->get_results($wpdb->prepare("SELECT unique_id, book_id, verse FROM $trans->table WHERE book_id = %d AND chapter_id != 0 AND verse_id != 0", $book));

			// If we have verses for this book, insert their index text into the index table
			if (!empty($verses))
			{
				$values = array();
				foreach ($verses as $verse)
				{
					$values []= $wpdb->prepare('(%d, %d, %d, %s)', $verse->unique_id, $verse->book_id, $trans->id, implode(' ', self::get_index_words($verse->verse)));
				}
				$wpdb->query("INSERT INTO " . self::index_table . " (unique_id, book_id, trans_id, index_text) VALUES " . implode(', ', $values));
			}
		}
	}

	/**
	 * Repairs a full text index
	 *
	 * @param string $table_name
	 */
	private static function repair_index($table_name)
	{
		global $wpdb;
		$wpdb->query("REPAIR TABLE $table_name QUICK");
	}

	/**
	 * Returns the words needed for indexing the given text string
	 *
	 * @param string $text
	 * @return array of strings
	 */
	public static function get_index_words($text)
	{
		global $bfox_ft_stopwords;

		// TODO1: Use str_word_count()

		// Strip out HTML tags, lowercase it, and parse into words
		$words = str_word_count(strtolower(strip_tags($text)), 1);

		// Check each word to see if it is below the FULLTEXT min word length, or if it is a FULLTEXT stopword
		// If so, we need to prefix it with some characters so that it doesn't get ignored by MySQL
		foreach ($words as &$word)
		{
			if ((strlen($word) < BFOX_FT_MIN_WORD_LEN) || isset($bfox_ft_stopwords[$word]))
				$word = BFOX_FT_INDEX_PREFIX . $word;
		}

		return $words;
	}

	/**
	 * Updates the verse data for a given verse in the given translation table
	 *
	 * @param string $table_name
	 * @param BibleVerse $verse
	 * @param string $verse_text
	 */
	public static function update_verse_text($table_name, BibleVerse $verse, $verse_text)
	{
		global $wpdb;
		$sql = $wpdb->prepare("REPLACE INTO $table_name (unique_id, book_id, chapter_id, verse_id, verse)
							  VALUES (%d, %d, %d, %d, %s)",
							  $verse->unique_id,
							  $verse->book,
							  $verse->chapter,
							  $verse->verse,
							  $verse_text
							  );
		$wpdb->query($sql);
	}

	/**
	 * Modifies/Creates the info for a particular translation in the translations table
	 *
	 * @param object $trans
	 * @param int $id Optional translation ID
	 * @return unknown
	 */
	private static function edit_translation($trans, $id = NULL)
	{
		global $wpdb;

		if (!empty($id))
		{
			$wpdb->query($wpdb->prepare("UPDATE " . self::translation_table . " SET short_name = %s, long_name = %s, is_enabled = %d WHERE id = %d",
				$trans->short_name, $trans->long_name, $trans->is_enabled, $id));
		}
		else
		{
			$wpdb->query($wpdb->prepare("INSERT INTO " . self::translation_table . " SET short_name = %s, long_name = %s, is_enabled = %d",
				$trans->short_name, $trans->long_name, $trans->is_enabled));
			$id = $wpdb->insert_id;
		}

		// If a file was specified, we need to add verse data from that file
		if (!empty($trans->file_name))
		{
			$table_name = self::get_translation_table_name($id);

			// Drop the table if it already exists
			$wpdb->query("DROP TABLE IF EXISTS $table_name");

			// Create the table
			self::create_translation_table($table_name);

			// Add the new verse data
			self::load_usfx($table_name, $trans->file_name);

			// Update the book counts table
			self::update_book_counts($id, $table_name);
		}

		return $id;
	}

	/**
	 * Add verse data from a USFX file
	 *
	 * @param string $table_name
	 * @param string $file_name File name for the USFX file
	 */
	private static function load_usfx($table_name, $file_name)
	{
		require_once('usfx.php');
		$usfx = new BfoxUsfx();
		$usfx->set_table_name($table_name);
		$usfx->read_file(Translations::dir . '/' . $file_name);
	}

	/**
	 * Updates the book counts table for the given translation id, using the given translation table
	 *
	 * @param integer $trans_id
	 * @param string $table_name
	 */
	private static function update_book_counts($trans_id, $table_name)
	{
		global $wpdb;

		// Add the chapter counts
		$wpdb->query($wpdb->prepare('
			REPLACE INTO ' . self::book_counts_table . '
			(trans_id, book_id, chapter_id, value)
			SELECT %d, book_id, 0, MAX(chapter_id)
			FROM ' . $table_name . '
			GROUP BY book_id',
			$trans_id),
			ARRAY_N
		);

		// Add the verse counts
		$wpdb->query($wpdb->prepare('
			REPLACE INTO ' . self::book_counts_table . '
			(trans_id, book_id, chapter_id, value)
			SELECT %d, book_id, chapter_id, MAX(verse_id)
			FROM ' . $table_name . '
			WHERE chapter_id > 0
			GROUP BY book_id, chapter_id',
			$trans_id),
			ARRAY_N
		);
	}

	/**
	 * Deletes a translation from the translation table
	 *
	 * @param unknown_type $trans_id
	 */
	private static function delete_translation($trans_id)
	{
		global $wpdb;

		// Drop the verse data table if it exists
		$table_name = self::get_translation_table_name($trans_id);
		if (BfoxUtility::does_table_exist($table_name)) $wpdb->query("DROP TABLE $table_name");

		// Delete the translation data from the translation table
		$wpdb->query($wpdb->prepare("DELETE FROM " . self::translation_table . " WHERE id = %d", $trans_id));

		// Delete the translation data from the book counts table
		$wpdb->query($wpdb->prepare("DELETE FROM " . self::book_counts_table . " WHERE trans_id = %d", $trans_id));
	}

	/**
	 * Returns the default bible translation id
	 *
	 * @return unknown
	 */
	private static function get_default_id()
	{
		global $wpdb;
		return $wpdb->get_var("SELECT id FROM " . self::translation_table . " WHERE is_default = 1 LIMIT 1");
	}

	/**
	 * Returns the user's default bible translation id
	 *
	 * @return unknown
	 */
	private static function get_user_default_id()
	{
		// TODO2: User should have his own default translation
		return self::get_default_id();
	}

	/**
	 * Outputs an html select input with a list of translations
	 *
	 * @param unknown_type $select_id
	 */
	public static function output_select($select_id = NULL, $use_short = FALSE)
	{
		// Get the list of enabled translations
		$translations = self::get_translations();

		?>
		<select name="<?php echo Bible::var_translation ?>">
		<?php foreach ($translations as $translation): ?>
			<option value="<?php echo $translation->id ?>" <?php if ($translation->id == $select_id) echo 'selected' ?>><?php echo ($use_short) ? $translation->short_name : $translation->long_name; ?></option>
		<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Called before loading the manage translations admin page
	 *
	 * Performs all the user's translation edit requests before loading the page
	 *
	 */
	public function manage_page_load()
	{
		$bfox_page_url = 'admin.php?page=' . self::page;

		$action = $_POST['action'];
		if ( isset($_POST['deleteit']) && isset($_POST['delete']) )
			$action = 'bulk-delete';

		switch($action)
		{
		case 'addtrans':

			check_admin_referer('add-translation');

			if ( !current_user_can(self::min_user_level))
				wp_die(__('Cheatin&#8217; uh?'));

			$trans = array();
			$trans['short_name'] = stripslashes($_POST['short_name']);
			$trans['long_name'] = stripslashes($_POST['long_name']);
			$trans['is_enabled'] = (int) $_POST['is_enabled'];
			$trans['file_name'] = stripslashes($_POST['trans_file']);
			$trans_id = self::edit_translation((object) $trans);

			wp_redirect(add_query_arg(array('action' => 'edit', 'trans_id' => $trans_id, 'message' => 1), $bfox_page_url));

			exit;
		break;

		case 'bulk-delete':
			check_admin_referer('bulk-translations');

			if ( !current_user_can(self::min_user_level) )
				wp_die( __('You are not allowed to delete translations.') );

			foreach ((array) $_POST['delete'] as $trans_id)
				self::delete_translation($trans_id);

			wp_redirect(add_query_arg('message', 2, $bfox_page_url));

			exit;
		break;

		case 'editedtrans':
			$trans_id = (int) $_POST['trans_id'];
			check_admin_referer('update-translation-' . $trans_id);

			if ( !current_user_can(self::min_user_level) )
				wp_die(__('Cheatin&#8217; uh?'));

			$trans = array();
			$trans['short_name'] = stripslashes($_POST['short_name']);
			$trans['long_name'] = stripslashes($_POST['long_name']);
			$trans['is_enabled'] = (int) $_POST['is_enabled'];
			$trans['file_name'] = stripslashes($_POST['trans_file']);
			$trans_id = self::edit_translation((object) $trans, $trans_id);

			wp_redirect(add_query_arg(array('action' => 'edit', 'trans_id' => $trans_id, 'message' => 3), $bfox_page_url));

			exit;
		break;
		}
	}

	/**
	 * Outputs the translation management admin page
	 *
	 */
	public function manage_page()
	{
		$messages[1] = __('Translation added.');
		$messages[2] = __('Translation deleted.');
		$messages[3] = __('Translation updated.');
		$messages[4] = __('Translation not added.');
		$messages[5] = __('Translation not updated.');

		if (isset($_GET['message']) && ($msg = (int) $_GET['message'])): ?>
			<div id="message" class="updated fade"><p><?php echo $messages[$msg]; ?></p></div>
			<?php $_SERVER['REQUEST_URI'] = remove_query_arg(array('message'), $_SERVER['REQUEST_URI']);
		endif;

		switch($_GET['action'])
		{
		case 'edit':
			$trans_id = (int) $_GET['trans_id'];
			include('edit-translation-form.php');
			break;

		case 'validate':
			$file = (string) $_GET['file'];
			bfox_usfx_menu($file);
			break;

		default:
			include('manage-translations.php');
			break;
		}
	}

	/**
	 * Sets the global translation to the default translation ID
	 *
	 */
	public static function set_global_translations()
	{
		global $bfox_trans;
		$bfox_trans = self::get_translation(self::get_default_id());
	}

	/**
	 * Return verse content for the given bible refs with minimum formatting
	 *
	 * @param BibleRefs $refs
	 * @param Translation $trans
	 * @return string
	 */
	public static function get_verse_content(BibleRefs $refs, Translation $trans = NULL)
	{
		if (is_null($trans)) $trans = $GLOBALS['bfox_trans'];

		$content = '';

		$verses = $trans->get_verses($refs->sql_where());

		foreach ($verses as $verse)
		{
			if ($verse->verse_id != 0) $content .= '<b>' . $verse->verse_id . '</b> ';
			$content .= $verse->verse;
		}

		// Fix the footnotes
		// TODO3: this function does more than just footnotes
		$content = bfox_special_syntax($content);

		return $content;
	}

	/**
	 * Return verse content for the given bible refs formatted for email output
	 *
	 * @param BibleRefs $refs
	 * @param Translation $trans
	 * @return unknown
	 */
	public static function get_verse_content_email(BibleRefs $refs, Translation $trans = NULL)
	{
		// Pre formatting is for when we can't use CSS (ie. in an email)
		// We just replace the tags which would have been formatted by css with tags that don't need formatting
		$content =
			str_replace('<span class="bible_poetry_indent_2"></span>', '<span style="margin-left: 20px"></span>',
				str_replace('<span class="bible_poetry_indent_1"></span>', '',
					str_replace('<span class="bible_end_poetry"></span>', "<br/>\n",
						str_replace('<span class="bible_end_p"></span>', "<br/><br/>\n",
							self::get_verse_content($refs, $trans)))));

		return $content;
	}

	/**
	 * Return verse content for display in chapter groups
	 *
	 * @param integer $book
	 * @param integer $chapter1
	 * @param integer $chapter2
	 * @param string $visible
	 * @param Translation $trans
	 * @return string
	 */
	public static function get_chapters_content($book, $chapter1, $chapter2, $visible, Translation $trans = NULL)
	{
		if (is_null($trans)) $trans = $GLOBALS['bfox_trans'];

		$content = '';

		$book_name = BibleMeta::get_book_name($book);

		// Get the verse data from the bible translation
		$chapters = $trans->get_chapter_verses($book, $chapter1, $chapter2, $visible);

		if (!empty($chapters))
		{
			// We don't want to start with a hidden rule
			$add_rule = FALSE;

			foreach ($chapters as $chapter_id => $verses)
			{
				$is_hidden_chapter = TRUE;
				$prev_visible = TRUE;
				$index = 0;

				$sections = array();

				foreach ($verses as $verse)
				{
					if (0 == $verse->verse_id) continue;

					if ($verse->visible) $is_hidden_chapter = FALSE;

					if ($prev_visible != $verse->visible) $index++;
					$prev_visible = $verse->visible;

					$sections[$index] .= "
						<span class='bible_verse' verse='$verse->verse_id'>
							<b>$verse->verse_id</b>
							$verse->verse
						</span>
						";
				}
				$last_index = $index;

				if ($is_hidden_chapter)
				{
					$content .= "
						<span class='chapter hidden_chapter'>
							<h5>$chapter_id</h5>
							$sections[1]
						</span>
						";

					// Don't show a hidden rule immediately following a hidden chapter
					$add_rule = FALSE;
				}
				else
				{
					$chapter_content = '';
					foreach ($sections as $index => $section)
					{
						// Every odd numbered section is hidden
						if ($index % 2)
						{
							$chapter_content .= "
								<span class='hidden_verses'>
									$section
								</span>
								";

							// If we can add a rule, do it now
							// We don't want to add a rule for the last section, though
							if ($add_rule)// && ($last_index != $index))
							{
								$chapter_content .= "<hr class='hidden_verses_rule' />";

								// Don't add a rule immediately after this one
								$add_rule = FALSE;
							}
						}
						else
						{
							$chapter_content .= $section;

							// We only want to add a rule if the previous section was not hidden
							$add_rule = TRUE;
						}
					}

					$content .= "
						<span class='chapter visible_chapter'>
							<h5>$chapter_id</h5>
							$chapter_content
						</span>
						";
				}

			}

		}

		// Fix the footnotes
		// TODO3: this function does more than just footnotes
		$content = bfox_special_syntax($content);

		return $content;
	}
}

// Set the global translation (using the default translation)
Translations::set_global_translations();

/**
 * Returns the number of chapters in a book for a given translation
 *
 * @param int $book_id
 * @param int $trans_id
 * @return int The number of chapters in a book
 */
function bfox_get_num_chapters($book_id, $trans_id = NULL)
{
	if (is_null($trans_id)) $trans_id = $GLOBALS['bfox_trans']->id;

	global $wpdb;
	$num = $wpdb->get_var($wpdb->prepare('SELECT value
											FROM ' . BFOX_BOOK_COUNTS_TABLE . '
											WHERE trans_id = %d AND book_id = %d AND chapter_id = 0',
											$trans_id, $book_id));
	return $num;
}

function bfox_books_two_cols()
{
	$groups = array();
	$groups['all'] = range(1, 81);
	$groups['bible'] = range(1, 66);
	$groups['old'] = range(1, 39);
	$groups['new'] = range(40, 66);
	$groups['torah'] = range(1, 5);
	$groups['history'] = range(6, 17);
	$groups['wisdom'] = range(18, 22);
	$groups['prophets'] = range(28, 39);
	$groups['major_prophets'] = range(23, 27);
	$groups['minor_prophets'] = range(28, 39);
	$groups['gospels'] = range(40, 43);
	$groups['acts'] = array(44);
	$groups['gospelacts'] = range(40, 44);
	$groups['paul'] = range(45, 57);
	$groups['epistles'] = range(58, 65);
	$groups['revelation'] = array(66);
	$groups['apocrypha'] = range(67, 81);

	$bfox_bible_groups = $groups;

	$content .= '<div id="bible_toc">';

	$content .= '<div id="old_testament"><h3>Old Testament</h3>';
	$content .= '<ul>';
	foreach ($bfox_bible_groups['old'] as $book_id)
		$content .= '<li>' . BfoxLinks::ref_link(BibleMeta::get_book_name($book_id)) . '</li>';
	$content .= '</ul></div>';

	$content .= '<div id="new_testament"><h3>New Testament</h3>';
	$content .= '<ul>';
	foreach ($bfox_bible_groups['new'] as $book_id)
	$content .= '<li>' . BfoxLinks::ref_link(BibleMeta::get_book_name($book_id)) . '</li>';
	$content .= '</ul></div>';

	$content .= '<div id="apocrypha"><h3>Apocryphal Books</h3>';
	$content .= '<ul>';
	foreach ($bfox_bible_groups['apocrypha'] as $book_id)
	$content .= '<li>' . BfoxLinks::ref_link(BibleMeta::get_book_name($book_id)) . '</li>';
	$content .= '</ul></div>';

	$content .= '</div>';
	return $content;
}

function bfox_show_toc_groups($groups, $books, $depth = 3)
{
	global $bfox_bible_groups;

	foreach ($groups as $key => $group)
	{
		if (is_array($group)) $output .= bfox_show_toc_groups($group, $books, $depth + 1);
		else
		{
			if ('0' != $key)
			{
				$output .= "<p><h$depth>$group</h$depth></p>";
				foreach ($bfox_bible_groups[$key] as $book_id)
				{
					if (isset($books[$book_id]))
					{
						$book_name = BibleMeta::get_book_name($book_id);
						$output .= '<p>' . $book_name;
						$chaps = array();
						for ($chapter = 0; $chapter < $books[$book_id]; $chapter++)
							$chaps[] = BfoxLinks::ref_link(array('ref_str' => $book_name . ' ' .($chapter + 1), 'text' => $chapter + 1));
//						$output .= '<br/>' . implode(', ', $chaps);
						$output .= '</p>';
					}
				}
			}
			else
			{
				$depth2 = $depth - 1;
				$output .= "<p><h$depth2>$group</h$depth2></p>";
			}
		}
	}

	return $output;
}

function bfox_show_toc($trans_id = 12)
{
	echo bfox_books_two_cols();
/*
	global $wpdb;
	$data = $wpdb->get_results($wpdb->prepare('SELECT book_id, value
											  FROM ' . BFOX_BOOK_COUNTS_TABLE . '
											  WHERE trans_id = %d AND chapter_id = 0',
											  $trans_id));

	foreach ($data as $row)
		$books[$row->book_id] = $row->value;

	$groups = array('',
					'old' => array('Old Testament',
								   'torah' => 'The Books of Moses',
								   'history' => 'The Historical Books',
								   'wisdom' => 'The Books of Wisdom',
								   'prophets' => array('The Prophets',
													   'major_prophets' => 'Major Prophets',
													   'minor_prophets' => 'Minor Prophets')),
					'new' => array('New Testament',
								   'gospels' => 'The Gospels',
								   'acts' => 'Acts',
								   'paul' => 'Pauline Epistles',
								   'epistles' => 'General Epistles',
								   'revelation' => 'Revelation'),
					'apocrypha' => 'Apocryphal Books'
	);

	echo '<center>';
	echo bfox_show_toc_groups($groups, $books);
	echo '</center>';*/
}

function bfox_output_bible_group($group)
{
	$content = '';
	foreach (BibleMeta::$book_groups[$group] as $child)
	{
		$child_content = '';

		if (isset(BibleMeta::$book_groups[$child])) $child_content = bfox_output_bible_group($child);
		else $child_content = BfoxLinks::ref_link(BibleMeta::get_book_name($child));

		$content .= "<li>$child_content</li>";
	}

	return "<span class='book_group_title'>
		" . BfoxLinks::ref_link(array('ref_str' => BibleMeta::get_book_name($group), 'href' => BfoxLinks::ref_url($group))) . "
	</span>
	<ul class='book_group'>
		$content
	</ul>";
}

/**
 * Performs a regular full text search
 *
 * @param unknown_type $text
 * @return unknown
 */
function bfox_search_regular($text)
{
	global $wpdb, $bfox_trans;
	$match = $wpdb->prepare("MATCH(index_text) AGAINST(%s)", $text);
	$results = $wpdb->get_results("SELECT *, $match AS match_val FROM $bfox_trans->table WHERE $match LIMIT 5");

	return $results;
}

/**
 * Performs a full text search with query expansion
 *
 * @param unknown_type $text
 * @return unknown
 */
function bfox_search_expanded($text)
{
	global $wpdb, $bfox_trans;
	$match = $wpdb->prepare("MATCH(index_text) AGAINST(%s WITH QUERY EXPANSION)", $text);
	$results = $wpdb->get_results("SELECT *, $match AS match_val FROM $bfox_trans->table WHERE verse_id != 0 AND $match LIMIT 5");

	return $results;
}

?>