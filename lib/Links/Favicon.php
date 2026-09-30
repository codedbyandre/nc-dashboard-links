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
		$fromIco = $this->tryDownloadImage($client, $origin . '/favicon.ico');
		if ($fromIco !== null) {
			return $fromIco;
		}

		$html = $this->downloadCapped($client, (string)$page);
		if ($html === null) {
			throw new InvalidLink('icon', self::FAIL);
		}
		$iconHref = self::firstSameHostIconHref($html, (string)$page);
		if ($iconHref === null) {
			throw new InvalidLink('icon', self::FAIL);
		}
		$fromLink = $this->tryDownloadImage($client, $iconHref);
		if ($fromLink === null) {
			throw new InvalidLink('icon', self::FAIL);
		}

		return $fromLink;
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

	private static function isIconRel(string $rel): bool {
		$tokens = preg_split('/\s+/', strtolower(trim($rel))) ?: [];
		return in_array('icon', $tokens, true);
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
		try {
			return $this->icons->store($bytes);
		} catch (InvalidLink) {
			return null;
		}
	}

	private function downloadCapped(IClient $client, string $uri): ?string {
		try {
			$response = $client->get($uri, [
				'allow_redirects' => false,
				'timeout' => 5,
			]);
		} catch (\Throwable) {
			return null;
		}
		if ($response->getStatusCode() !== 200) {
			return null;
		}

		return $this->readCappedBody($response);
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
		$records = dns_get_record($candidate, DNS_A + DNS_AAAA);
		if ($records === false || $records === []) {
			throw new InvalidLink('icon', self::FAIL);
		}
		foreach ($records as $record) {
			$ip = $record['ip'] ?? $record['ipv6'] ?? null;
			if (is_string($ip)) {
				$this->assertPublicIp($ip);
			}
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
