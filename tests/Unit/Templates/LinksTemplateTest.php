<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 André Wiesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

final class LinksTemplateTest extends TestCase {
	public function testOnlyNewTabLinksLeaveTheCurrentTab(): void {
		$html = $this->render([
			$this->link('Intranet', 'https://cloud.example.test/open/intranet', false),
			$this->link('Wiki', 'https://cloud.example.test/open/wiki', true),
		]);

		self::assertStringContainsString('<a href="https://cloud.example.test/open/intranet">', $html);
		self::assertStringContainsString(
			'<a href="https://cloud.example.test/open/wiki" target="_blank" rel="noopener noreferrer">',
			$html,
		);
		self::assertSame(1, substr_count($html, 'target='));
	}

	public function testHeadingIsThePageTitle(): void {
		$html = $this->render([], 'Hand<book>');

		self::assertStringContainsString('<h2>Hand&lt;book&gt;</h2>', $html);
	}

	/**
	 * @param list<array{title: string, url: string, iconUrl: ?string, iconMonochrome: bool, subtitle: string, newTab: bool}> $links
	 */
	private function render(array $links, string $pageTitle = 'Company links'): string {
		$_ = [
			'pageTitle' => $pageTitle,
			'emptyTitle' => 'No company links',
			'emptyHint' => 'Ask an administrator to add company links.',
			'bands' => $links === [] ? [] : [['label' => '', 'links' => $links]],
		];
		ob_start();
		try {
			require __DIR__ . '/../../../templates/links.php';
		} finally {
			$html = (string)ob_get_clean();
		}

		return $html;
	}

	/**
	 * @return array{title: string, url: string, iconUrl: null, iconMonochrome: false, subtitle: string, newTab: bool}
	 */
	private function link(string $title, string $url, bool $newTab): array {
		return [
			'title' => $title,
			'url' => $url,
			'iconUrl' => null,
			'iconMonochrome' => false,
			'subtitle' => 'cloud.example.test',
			'newTab' => $newTab,
		];
	}
}
