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

	public function testFirstSameHostIconHrefNoIconTag(): void {
		$html = '<html><head><link rel="stylesheet" href="/app.css"></head></html>';
		self::assertNull(Favicon::firstSameHostIconHref($html, 'https://example.com/'));
	}
}
