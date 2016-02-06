<?php
namespace pdyn\datatype;

/**
 * Class for dealing wth plain text.
 */
class Text extends \pdyn\datatype\Base {
	/** @var string The current text being worked with. */
	protected $val = '';

	/**
	 * Constructor.
	 *
	 * @param string $text The text you want to work with.
	 */
	public function __construct($text) {
		$this->val = (string)$text;
	}

	/**
	 * Extracts hashtags from text.
	 *
	 * @param bool $pad_for_ft_search If using the output for mysql fulltext search, use this to pad
	 *                                short hashtags to meet the minimum search length.
	 * @return array Array of found hashtags.
	 */
	public function extract_hashtags($pad_for_ft_search=false) {
		preg_match_all('@(#[a-z0-9]+)@iu', $this->val, $matches);
		foreach ($matches[1] as $i => $tag) {
			$matches[1][$i] = mb_strtolower($tag);
		}
		if ($pad_for_ft_search === true) {
			$tags = [];
			foreach ($matches[1] as $i => $tag) {
				$tags[] = (mb_strlen($tag) < 6)
					? str_pad($tag, 6, '-', STR_PAD_RIGHT)
					: $tag;
			}
			return $tags;
		} else {
			return $matches[1];
		}
	}

	/**
	 * Sanitize the text for display.
	 *
	 * @param bool $striptags If true, will remove all html tags. If false, will just htmlentities() them.
	 */
	public function sanitize($striptags = true) {
		if (!is_numeric($this->val)) {
			if ($striptags === true) {
				$this->val = htmlspecialchars(strip_tags($this->val), ENT_QUOTES, 'UTF-8', false);
			} else {
				$this->val = htmlentities($this->val, ENT_HTML401, 'UTF-8', false);
			}
		}
	}

	/**
	 * Truncate the text to a specific display length.
	 *
	 * Note: This decodes HTML entities so they are not affected.
	 *
	 * @param int $length The length to truncate to.
	 */
	public function truncate($length) {
		$this->val = html_entity_decode($this->val, ENT_QUOTES, 'UTF-8');
		if (mb_strlen($this->val) > $length) {
			$this->val = mb_substr($this->val, 0, $length).'...';
		}
	}

	/**
	 * Remove all whitespace from the string.
	 */
	public function remove_whitespace() {
		$needle = ["\n", "\r", "\t", "\x0B", "\0", ' '];
		for ($i = 0; $i <= 31; $i++) {
			$needle[] = html_entity_decode('&#'.str_pad($i, 2, '0', STR_PAD_LEFT).';'); //add ASCII meta characters
		}
		$replace = '';
		$this->val = str_replace($needle, $replace, $this->val);
	}

	/**
	 * Generate a color based on a string.
	 *
	 * @return array Array of color components.
	 */
	public function generate_color($hex = false) {
		$max_intensity = 200;

		$colors = abs(crc32($this->val));
		$colors = mb_substr($colors, 0, 9);
		$colors = str_pad($colors, 9, '5');

		// Shuffle.
		$colorsparts = [
			$colors[0].$colors[3].$colors[6],
			$colors[1].$colors[4].$colors[7],
			$colors[2].$colors[5].$colors[8],
		];

		$colors = implode('', $colorsparts);

		$bgcolor = [
			'r' => round((mb_substr($colors, 3, 3) / 1000) * 255),
			'g' => round((mb_substr($colors, 0, 3) / 1000) * 255),
			'b' => round((mb_substr($colors, 6, 3) / 1000) * 255),
		];

		foreach ($bgcolor as $c => $val) {
			if ($val > $max_intensity) {
				$bgcolor[$c] = $max_intensity;
			}
		}

		if ($hex === true) {
			$bgcolor = dechex($bgcolor['r']).dechex($bgcolor['g']).dechex($bgcolor['b']);
		}

		return $bgcolor;
	}

	/**
	 * Force UTF-8 encoding on a string.
	 *
	 * @param string $s A string of questionable encoding.
	 * @return string A UTF-8 string.
	 */
	public static function force_utf8($s) {
		$encoding = mb_detect_encoding($s);
		if ($encoding === 'UTF-8') {
			return $s;
		} else {
			if (empty($encoding)) {
				$encoding = 'ISO-8859-1,ASCII';
			}
			return mb_convert_encoding($s, 'UTF-8', $encoding);
		}
	}

	/**
	 * Force all items in an array into UTF-8.
	 *
	 * @param array $ar Array to convert.
	 * @return array Converted array.
	 */
	public static function force_utf8_array(array $ar) {
		$arutf8 = [];
		foreach ($ar as $k => $v) {
			$kutf8 = static::force_utf8($k);
			if (is_array($v)) {
				$arutf8[$kutf8] = static::force_utf8_array($v);
			} elseif (is_string($v)) {
				$arutf8[$kutf8] = static::force_utf8($v);
			} else {
				$arutf8[$kutf8] = $v;
			}
			unset($ar[$k]);
		}
		return $arutf8;
	}

	/**
	 * Generates a url-safe representation of string $s. Useful for generating URLs from strings (posts, pages, albums, etc)
	 * Makes the following changes:
	 *     Removed common TLDs because they look weird when converted
	 *     Removes quotes and periods.
	 *     Converts any non-alphanumeric character to an underscore.
	 *     Converts string to lowercase
	 *     Removed starting and ending underscores.
	 *
	 * @param string $s The incoming string
	 * @return string The transformed string
	 */
	public static function make_slug($s) {
		$reserved = ['me', 'type'];
		if (in_array($s, $reserved, true)) {
			$s .= '_1';
		}

		//remove common TLDs because they look weird when you get something like CNN.com => cnncom :/
		$s = preg_replace('/\.com|\.org|\.net /iu', '', $s);

		//remove quotes first because it's dumb to have ex. "Joe's" convert to "joe_s", "joes" is better :)
		$s = preg_replace('/[\'\".]+/iu', '', $s);

		$s = preg_replace('/[^a-z0-9]+/iu', '_', $s);
		$s = mb_strtolower($s);
		$s = trim($s, '_');
		return $s;
	}
}