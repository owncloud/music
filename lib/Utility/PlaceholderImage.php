<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 * @copyright 2018 John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @author Christopher Schäpers <kondou@ts.unde.re>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Jan-Christoph Borchardt <hey@jancborchardt.net>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ <skjnldsv@protonmail.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Michael Weimann <mail@michael-weimann.eu>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Sergey Shliakhov <husband.sergey@gmail.com>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */
namespace OCA\Music\Utility;

use OCA\Music\Utility\PlaceholderImage\Color;

/**
 * Create placeholder images. This is a modified and stripped-down copy of https://github.com/nextcloud/server/blob/master/lib/private/Avatar/Avatar.php.
 */
class PlaceholderImage {

	/**
	 * Generate PNG image data
	 */
	public static function generate(string $name, string $seed, int $size) : string {
		$text = self::getText($name);
		$backgroundColor = self::getBackgroundColor($seed);

		$im = \imagecreatetruecolor($size, $size);
		$background = \imagecolorallocate(
			$im,
			$backgroundColor->red(),
			$backgroundColor->green(),
			$backgroundColor->blue()
		);
		$white = \imagecolorallocate($im, 255, 255, 255);
		\imagefilledrectangle($im, 0, 0, $size, $size, $background);

		$font = self::findFontPath();

		$fontSize = $size * 0.4;
		[$x, $y] = self::imageTtfCenter($im, $text, $font, (int)$fontSize);

		\imagettftext($im, $fontSize, 0, $x, $y, $white, $font, $text);

		\ob_start();
		\imagepng($im);
		$data = \ob_get_contents();
		\ob_end_clean();

		return $data;
	}

	/**
	 * Generate placeholder data in format compatible with OCA\Music\Http\FileResponse
	 */
	public static function generateForResponse(string $name, string $seed, int $size) : array {
		return [
			'content' => self::generate($name, $seed, $size),
			'mimetype' => 'image/png'
		];
	}

	private static function findFontPath() : string {
		// try to find the font from the hosting cloud instance first
		$files = \glob(__DIR__ . '/../../../../core/fonts/*Regular.ttf');

		// If the suitable font was not found from the core, then fall back to our local font.
		// The drawback is that this font contains only the Latin alphabet.
		return $files[0] ?? __DIR__ . '/../../fonts/OpenSans-Regular.woff';
	}

	/**
	 * Returns the first letter of the display name, or "?" if no name given.
	 */
	private static function getText(string $displayName): string {
		if (empty($displayName) === true) {
			return '?';
		}
		$firstTwoLetters = array_map(
			fn($namePart) => \mb_strtoupper(\mb_substr($namePart, 0, 1), 'UTF-8'),
			\explode(' ', $displayName, 2)
		);
		return \implode('', $firstTwoLetters);
	}

	/**
	 * Calculate real image ttf center
	 *
	 * @param mixed $image Prior to PHP 8.0 a resource and from 8.0 onwards a GdImage
	 * @param string $text text string
	 * @param string $font font path
	 * @param int $size font size
	 * @param int $angle
	 * @return int[]
	 */
	private static function imageTtfCenter($image, string $text, string $font, int $size, int $angle = 0) : array {
		// Image width & height
		$xi = \imagesx($image);
		$yi = \imagesy($image);

		// bounding box
		$box = \imagettfbbox($size, $angle, $font, $text);

		// imagettfbbox can return negative int
		$xr = \abs(\max($box[2], $box[4]));
		$yr = \abs(\max($box[5], $box[7]));

		// calculate bottom left placement
		$x = \intval(($xi - $xr) / 2);
		$y = \intval(($yi + $yr) / 2);

		return [$x, $y];
	}


	/**
	 * Convert a string to an integer evenly
	 * @param string $hash the text to parse
	 * @param int $maximum the maximum range
	 * @return int between 0 and $maximum
	 */
	private static function hashToInt(string $hash, int $maximum) : int {
		$final = 0;
		$result = [];

		// Splitting evenly the string
		for ($i = 0; $i < \strlen($hash); $i++) {
			// chars in md5 goes up to f, hex:16
			$result[] = \intval(\substr($hash, $i, 1), 16) % 16;
		}
		// Adds up all results
		foreach ($result as $value) {
			$final += $value;
		}
		// chars in md5 goes up to f, hex:16
		return \intval($final % $maximum);
	}

	/**
	 * @return Color Object containing r g b int in the range [0, 255]
	 */
	private static function getBackgroundColor(string $hash) : Color {
		// Normalize hash
		$hash = \strtolower($hash);

		// Already a md5 hash?
		if (preg_match('/^([0-9a-f]{4}-?){8}$/', $hash, $matches) !== 1) {
			$hash = \md5($hash);
		}

		// Remove unwanted char
		$hash = \preg_replace('/[^0-9a-f]+/', '', $hash);

		$red = new Color(182, 70, 157);
		$yellow = new Color(221, 203, 85);
		$blue = new Color(0, 130, 201); // Nextcloud blue

		// Number of steps to go from a color to another
		// 3 colors * 6 will result in 18 generated colors
		$steps = 6;

		$palette1 = Color::mixPalette($steps, $red, $yellow);
		$palette2 = Color::mixPalette($steps, $yellow, $blue);
		$palette3 = Color::mixPalette($steps, $blue, $red);

		$finalPalette = \array_merge($palette1, $palette2, $palette3);

		return $finalPalette[self::hashToInt($hash, $steps * 3)];
	}
}


namespace OCA\Music\Utility\PlaceholderImage;

/**
 * A stripped-down copy of https://github.com/nextcloud/server/blob/master/lib/public/Color.php
 */
class Color {
	private $r;
	private $g;
	private $b;

	public function __construct(int $r, int $g, int $b) {
		$this->r = $r;
		$this->g = $g;
		$this->b = $b;
	}

	public function red() : int {
		return $this->r;
	}

	public function green() : int {
		return $this->g;
	}

	public function blue() : int {
		return $this->b;
	}

	/**
	 * Mix two colors
	 *
	 * @param int $steps the number of intermediate colors that should be generated for the palette
	 * @param Color $color1 the first color
	 * @param Color $color2 the second color
	 * @return Color[]
	 */
	public static function mixPalette(int $steps, Color $color1, Color $color2) : array {
		$palette = [$color1];
		$step = self::stepCalc($steps, [$color1, $color2]);
		for ($i = 1; $i < $steps; $i++) {
			$r = intval($color1->red() + ($step[0] * $i));
			$g = intval($color1->green() + ($step[1] * $i));
			$b = intval($color1->blue() + ($step[2] * $i));
			$palette[] = new Color($r, $g, $b);
		}
		return $palette;
	}

	/**
	 * Calculate steps between two Colors
	 * @param int $steps start color
	 * @param Color[] $ends end color
	 * @return array{0: int, 1: int, 2: int} [r,g,b] steps for each color to go from $steps to $ends
	 */
	private static function stepCalc(int $steps, array $ends) : array {
		return [
			($ends[1]->red() - $ends[0]->red()) / $steps,
			($ends[1]->green() - $ends[0]->green()) / $steps,
			($ends[1]->blue() - $ends[0]->blue()) / $steps
		];
	}
}