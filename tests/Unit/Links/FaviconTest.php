<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 André Wiesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Tests\Unit\Links;

use OCA\DashboardLinks\Links\Favicon;
use PHPUnit\Framework\TestCase;

final class FaviconTest extends TestCase {
	public function testFirstSameHostIconHrefAbsoluteSameHost(): void {
		$html = '<html><head><link rel="icon" href="https://example.com/assets/icon.png"></head></html>';
		self::assertSame(
			'https://example.com/assets/icon.png',
			Favicon::firstSameHostIconHref($html, 'https://example.com/page'),
		);
	}

	public function testFirstSameHostIconHrefResolvesPathAndProtocolRelative(): void {
		$pathHtml = '<link rel="shortcut icon" href="/img/fav.ico">';
		self::assertSame(
			'https://example.com/img/fav.ico',
			Favicon::firstSameHostIconHref($pathHtml, 'https://example.com/app/index.html'),
		);

		$protocolHtml = '<link rel="icon" href="//example.com/static/icon.svg">';
		self::assertSame(
			'https://example.com/static/icon.svg',
			Favicon::firstSameHostIconHref($protocolHtml, 'https://example.com/'),
		);
	}

	public function testFirstSameHostIconHrefIgnoresOffHost(): void {
		$html = '<link rel="icon" href="https://cdn.example.net/icon.png">';
		self::assertNull(Favicon::firstSameHostIconHref($html, 'https://example.com/'));
	}

	public function testIconHrefsKeepsCdnIconAfterSameHost(): void {
		$html = '<link rel="icon" href="/local.png"><link rel="icon" href="https://cdn.example.net/icon.png">';
		self::assertSame(
			['https://example.com/local.png', 'https://cdn.example.net/icon.png'],
			Favicon::iconHrefs($html, 'https://example.com/'),
		);
	}

	public function testPublicFaviconUrlUsesOnlyTheHost(): void {
		self::assertSame(
			'https://www.google.com/s2/favicons?domain=chatgpt.com&sz=64',
			Favicon::publicFaviconUrl('chatgpt.com'),
		);
	}

	public function testPngFromIcoReturnsEmbeddedPng(): void {
		$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
		self::assertIsString($png);
		$ico = "\x00\x00\x01\x00" . "\x01\x00";
		$ico .= chr(1) . chr(1) . "\x00\x00" . pack('v', 1) . pack('v', 32) . pack('V', strlen($png)) . pack('V', 22);
		$ico .= $png;
		self::assertSame($png, Favicon::pngFromIco($ico));
	}

	public function testFirstSameHostIconHrefNoIconTag(): void {
		$html = '<html><head><link rel="stylesheet" href="/app.css"></head></html>';
		self::assertNull(Favicon::firstSameHostIconHref($html, 'https://example.com/'));
	}
}
