<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 André Wiesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Links;

use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;

final class Favicon {
	private const FAIL = 'The favicon could not be fetched.';

	public function __construct(
		private readonly IClientService $clientService,
		private readonly Icons $icons,
	) {
	}

	/**
	 * @throws InvalidLink field "icon"
	 */
	public function fetch(string $href): Icon {
		try {
			$page = HttpsUrl::parse($href);
		} catch (InvalidLink) {
			throw new InvalidLink('icon', self::FAIL);
		}
		$this->assertHostAllowed($page->host());

		$client = $this->clientService->newClient();
		$origin = $this->origin($page);
		$candidates = [];
		$html = $this->downloadCapped($client, (string)$page);
		if ($html !== null) {
			$candidates = self::iconHrefs($html, (string)$page);
		}
		array_push(
			$candidates,
			$origin . '/favicon.ico',
			$origin . '/favicon.png',
			$origin . '/apple-touch-icon.png',
		);
		foreach ($candidates as $candidate) {
			$icon = $this->tryDownloadImage($client, $candidate);
			if ($icon !== null) {
				return $icon;
			}
		}

		$fallback = self::publicFaviconUrl($page->host());
		$this->assertHostAllowed((string)parse_url($fallback, PHP_URL_HOST));
		$fromService = $this->tryDownloadImage($client, $fallback);
		if ($fromService !== null) {
			return $fromService;
		}

		throw new InvalidLink('icon', self::FAIL);
	}

	/**
	 * Sites such as ChatGPT answer a server fetch with 403. This returns a PNG for the host.
	 */
	public static function publicFaviconUrl(string $host): string {
		return 'https://www.google.com/s2/favicons?domain=' . rawurlencode($host) . '&sz=64';
	}

	/**
	 * Icon URLs from link tags. Same-host addresses come first, then other https hosts.
	 *
	 * @return list<string>
	 */
	public static function iconHrefs(string $html, string $pageHref): array {
		$pageParts = parse_url($pageHref);
		if (!is_array($pageParts) || ($pageParts['scheme'] ?? null) !== 'https' || !isset($pageParts['host']) || $pageParts['host'] === '') {
			return [];
		}
		$pageHost = strtolower((string)$pageParts['host']);
		if (preg_match_all('/<link\b[^>]*>/i', $html, $matches) !== 1 && ($matches[0] ?? []) === []) {
			return [];
		}
		$sameHost = [];
		$otherHost = [];
		foreach ($matches[0] as $tag) {
			$rel = self::attributeValue($tag, 'rel');
			if ($rel === null || !self::isIconRel($rel)) {
				continue;
			}
			$rawHref = self::attributeValue($tag, 'href');
			if ($rawHref === null) {
				continue;
			}
			$resolved = self::resolveHttpsHref(html_entity_decode($rawHref, ENT_QUOTES | ENT_HTML5), $pageParts);
			if ($resolved === null) {
				continue;
			}
			$host = strtolower((string)parse_url($resolved, PHP_URL_HOST));
			if ($host === $pageHost) {
				$sameHost[] = $resolved;
			} else {
				$otherHost[] = $resolved;
			}
		}

		return array_values(array_unique([...$sameHost, ...$otherHost]));
	}

	/**
	 * First &lt;link rel="icon"&gt; or rel="shortcut icon" whose href resolves to https on the page host.
	 */
	public static function firstSameHostIconHref(string $html, string $pageHref): ?string {
		$pageParts = parse_url($pageHref);
		if (!is_array($pageParts) || ($pageParts['scheme'] ?? null) !== 'https' || !isset($pageParts['host']) || $pageParts['host'] === '') {
			return null;
		}
		$pageHost = strtolower($pageParts['host']);
		if (preg_match_all('/<link\b[^>]*>/i', $html, $matches) === false) {
			return null;
		}
		$tags = $matches[0];
		if ($tags === []) {
			return null;
		}

		foreach ($tags as $tag) {
			$rel = self::attributeValue($tag, 'rel');
			if ($rel === null || !self::isIconRel($rel)) {
				continue;
			}
			$rawHref = self::attributeValue($tag, 'href');
			if ($rawHref === null) {
				continue;
			}
			$resolved = self::resolveSameHostHref(html_entity_decode($rawHref, ENT_QUOTES | ENT_HTML5), $pageParts, $pageHost);
			if ($resolved !== null) {
				return $resolved;
			}
		}

		return null;
	}

	/**
	 * @param array<string, mixed> $pageParts
	 */
	private static function resolveSameHostHref(string $raw, array $pageParts, string $pageHost): ?string {
		$raw = trim($raw);
		if ($raw === '') {
			return null;
		}
		$host = (string)$pageParts['host'];
		$port = isset($pageParts['port']) ? ':' . $pageParts['port'] : '';
		$authority = $host . $port;

		if (str_starts_with($raw, '//')) {
			$absolute = 'https:' . $raw;
		} elseif (preg_match('#^https://#i', $raw) === 1) {
			$absolute = $raw;
		} elseif (preg_match('#^http://#i', $raw) === 1) {
			return null;
		} elseif (str_starts_with($raw, '/')) {
			$absolute = 'https://' . $authority . $raw;
		} else {
			$path = $pageParts['path'] ?? '/';
			if (!is_string($path) || $path === '') {
				$path = '/';
			}
			$baseDir = preg_replace('#/[^/]*$#', '/', $path);
			if (!is_string($baseDir) || $baseDir === '') {
				$baseDir = '/';
			}
			$absolute = 'https://' . $authority . $baseDir . $raw;
		}

		$parts = parse_url($absolute);
		if (!is_array($parts) || ($parts['scheme'] ?? null) !== 'https') {
			return null;
		}
		$resolvedHost = isset($parts['host']) && is_string($parts['host']) ? strtolower($parts['host']) : '';
		if ($resolvedHost === '' || $resolvedHost !== $pageHost) {
			return null;
		}
		if (isset($parts['user']) || isset($parts['pass'])) {
			return null;
		}

		return $absolute;
	}

	/**
	 * @param array<string, mixed> $pageParts
	 */
	private static function resolveHttpsHref(string $raw, array $pageParts): ?string {
		$raw = trim($raw);
		if ($raw === '') {
			return null;
		}
		$host = (string)$pageParts['host'];
		$port = isset($pageParts['port']) ? ':' . $pageParts['port'] : '';
		$authority = $host . $port;

		if (str_starts_with($raw, '//')) {
			$absolute = 'https:' . $raw;
		} elseif (preg_match('#^https://#i', $raw) === 1) {
			$absolute = $raw;
		} elseif (preg_match('#^http://#i', $raw) === 1) {
			return null;
		} elseif (str_starts_with($raw, '/')) {
			$absolute = 'https://' . $authority . $raw;
		} else {
			$path = $pageParts['path'] ?? '/';
			if (!is_string($path) || $path === '') {
				$path = '/';
			}
			$baseDir = preg_replace('#/[^/]*$#', '/', $path);
			if (!is_string($baseDir) || $baseDir === '') {
				$baseDir = '/';
			}
			$absolute = 'https://' . $authority . $baseDir . $raw;
		}

		$parts = parse_url($absolute);
		if (!is_array($parts) || ($parts['scheme'] ?? null) !== 'https') {
			return null;
		}
		if (!isset($parts['host']) || !is_string($parts['host']) || $parts['host'] === '') {
			return null;
		}
		if (isset($parts['user']) || isset($parts['pass'])) {
			return null;
		}

		return $absolute;
	}

	private static function isIconRel(string $rel): bool {
		$tokens = preg_split('/\s+/', strtolower(trim($rel))) ?: [];
		foreach ($tokens as $token) {
			if (str_contains($token, 'icon')) {
				return true;
			}
		}

		return false;
	}

	private static function attributeValue(string $tag, string $name): ?string {
		$pattern = '/\b' . preg_quote($name, '/') . '\s*=\s*(?:(["\'])(.*?)\1|([^\s>]+))/i';
		if (preg_match($pattern, $tag, $match) !== 1) {
			return null;
		}

		return $match[2] !== '' ? $match[2] : ($match[3] ?? null);
	}

	private function tryDownloadImage(IClient $client, string $uri): ?Icon {
		$bytes = $this->downloadCapped($client, $uri);
		if ($bytes === null || $bytes === '') {
			return null;
		}
		$png = self::pngFromIco($bytes);
		foreach ([$bytes, $png] as $candidate) {
			if (!is_string($candidate) || $candidate === '') {
				continue;
			}
			try {
				return $this->icons->store($candidate);
			} catch (InvalidLink) {
			}
		}

		return null;
	}

	/**
	 * Classic 32-bit ICO images become PNG. Embedded PNG payloads are returned as they are.
	 */
	public static function pngFromIco(string $bytes): ?string {
		if (strlen($bytes) < 6 || substr($bytes, 0, 4) !== "\x00\x00\x01\x00") {
			return null;
		}
		$count = unpack('v', substr($bytes, 4, 2))[1];
		$best = null;
		for ($i = 0; $i < $count; $i++) {
			$entry = substr($bytes, 6 + ($i * 16), 16);
			if (strlen($entry) < 16) {
				return $best;
			}
			$width = ord($entry[0]) ?: 256;
			$height = ord($entry[1]) ?: 256;
			$size = unpack('V', substr($entry, 8, 4))[1];
			$offset = unpack('V', substr($entry, 12, 4))[1];
			$image = substr($bytes, $offset, $size);
			if (str_starts_with($image, "\x89PNG\r\n\x1a\n")) {
				return $image;
			}
			$png = self::dib32ToPng($image, $width, $height);
			if ($png !== null) {
				$best = $png;
			}
		}

		return $best;
	}

	private static function dib32ToPng(string $dib, int $width, int $height): ?string {
		if (!function_exists('imagecreatetruecolor') || strlen($dib) < 40 || $width < 1 || $height < 1) {
			return null;
		}
		$headerSize = unpack('V', substr($dib, 0, 4))[1];
		if ($headerSize < 40 || strlen($dib) < $headerSize + ($width * $height * 4)) {
			return null;
		}
		$pixels = substr($dib, $headerSize);
		$rowBytes = $width * 4;
		$image = imagecreatetruecolor($width, $height);
		if ($image === false) {
			return null;
		}
		imagealphablending($image, false);
		imagesavealpha($image, true);
		for ($y = 0; $y < $height; $y++) {
			$row = substr($pixels, ($height - 1 - $y) * $rowBytes, $rowBytes);
			for ($x = 0; $x < $width; $x++) {
				$offset = $x * 4;
				if (!isset($row[$offset + 3])) {
					continue;
				}
				$alpha = 127 - intdiv(ord($row[$offset + 3]) * 127, 255);
				$color = imagecolorallocatealpha($image, ord($row[$offset + 2]), ord($row[$offset + 1]), ord($row[$offset]), $alpha);
				imagesetpixel($image, $x, $y, $color);
			}
		}
		ob_start();
		imagepng($image);
		$png = ob_get_clean();
		imagedestroy($image);

		return is_string($png) && str_starts_with($png, "\x89PNG") ? $png : null;
	}

	private function downloadCapped(IClient $client, string $uri, int $redirects = 0): ?string {
		try {
			$response = $client->get($uri, [
				'allow_redirects' => false,
				'timeout' => 5,
				'headers' => [
					'User-Agent' => 'Mozilla/5.0 (compatible; NextcloudCompanyLinks/1.1)',
				],
			]);
		} catch (\Throwable) {
			return null;
		}
		$status = $response->getStatusCode();
		if ($status >= 300 && $status < 400 && $redirects < 3) {
			$next = self::resolveRedirect($uri, $response->getHeader('Location'));
			if ($next === null) {
				return null;
			}
			$host = parse_url($next, PHP_URL_HOST);
			if (!is_string($host) || $host === '') {
				return null;
			}
			try {
				$this->assertHostAllowed($host);
			} catch (InvalidLink) {
				return null;
			}

			return $this->downloadCapped($client, $next, $redirects + 1);
		}
		if ($status !== 200) {
			return null;
		}

		return $this->readCappedBody($response);
	}

	private static function resolveRedirect(string $from, string $location): ?string {
		$location = trim($location);
		if ($location === '') {
			return null;
		}
		if (preg_match('#^https://#i', $location) === 1) {
			return $location;
		}
		$parts = parse_url($from);
		if (!is_array($parts) || !isset($parts['host']) || !is_string($parts['host'])) {
			return null;
		}

		return self::resolveHttpsHref($location, $parts);
	}

	private function readCappedBody(IResponse $response): ?string {
		$body = $response->getBody();
		if (is_string($body)) {
			if (strlen($body) > Icons::MAX_BYTES) {
				return substr($body, 0, Icons::MAX_BYTES);
			}

			return $body;
		}
		if (!is_resource($body)) {
			return null;
		}
		$chunk = stream_get_contents($body, Icons::MAX_BYTES);
		if ($chunk === false) {
			return null;
		}

		return $chunk;
	}

	private function assertHostAllowed(string $host): void {
		$candidate = $host;
		if (str_starts_with($candidate, '[') && str_ends_with($candidate, ']')) {
			$candidate = substr($candidate, 1, -1);
		}
		if (strcasecmp($candidate, 'localhost') === 0) {
			throw new InvalidLink('icon', self::FAIL);
		}
		if (filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
			$this->assertPublicIp($candidate);
			return;
		}
		$ips = gethostbynamel($candidate);
		if ($ips === false || $ips === []) {
			throw new InvalidLink('icon', self::FAIL);
		}
		foreach ($ips as $ip) {
			$this->assertPublicIp($ip);
		}
	}

	private function assertPublicIp(string $ip): void {
		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
			throw new InvalidLink('icon', self::FAIL);
		}
	}

	private function origin(HttpsUrl $url): string {
		$parts = parse_url((string)$url);
		$host = is_array($parts) && isset($parts['host']) && is_string($parts['host']) ? $parts['host'] : $url->host();
		$port = is_array($parts) && isset($parts['port']) ? ':' . $parts['port'] : '';

		return 'https://' . $host . $port;
	}
}
