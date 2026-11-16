<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 André Wiesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Links;

use OCA\DashboardLinks\AppInfo\Application;
use OCP\IAppConfig;

final class CatalogStore {
	private const KEY = 'catalog';
	private const SCHEMA = 2;
	private const LEGACY_SCHEMA = 1;

	public function __construct(
		private readonly IAppConfig $config,
	) {
	}

	public function current(): Catalog {
		try {
			return $this->interpret()['catalog'];
		} catch (UnreadableCatalog) {
			return Catalog::empty();
		}
	}

	/**
	 * IAppConfig has no compare-and-swap. Equal content on schema 2 returns
	 * without a write so a retry is safe. A lost race still 412s the next
	 * distinct save. An unreadable document is not that empty catalog.
	 *
	 * @throws StaleCatalog
	 * @throws UnreadableCatalog
	 */
	public function replace(Catalog $next, string $expectedRevision): Catalog {
		$read = $this->interpret();
		$current = $read['catalog'];
		if ($next->revision() === $current->revision() && !$read['upgrade']) {
			return $current;
		}
		if ($expectedRevision !== $current->revision()) {
			throw new StaleCatalog($current);
		}
		$this->config->setValueArray(
			Application::APP_ID,
			self::KEY,
			['schema' => self::SCHEMA] + $next->content(),
			true,
		);

		return $next;
	}

	/**
	 * Missing key and schema 1 are writable. Unknown schema and a schema 2
	 * document that fails parse are not an empty catalog.
	 *
	 * @return array{catalog: Catalog, upgrade: bool}
	 * @throws UnreadableCatalog
	 */
	private function interpret(): array {
		$doc = $this->config->getValueArray(Application::APP_ID, self::KEY, [], true);
		if ($doc === []) {
			return ['catalog' => Catalog::empty(), 'upgrade' => true];
		}
		$schema = $doc['schema'] ?? null;
		if ($schema === self::LEGACY_SCHEMA) {
			$rows = $doc['links'] ?? [];

			return [
				'catalog' => Catalog::fromLegacyRows(is_array($rows) ? array_values($rows) : []),
				'upgrade' => true,
			];
		}
		if ($schema !== self::SCHEMA) {
			throw new UnreadableCatalog();
		}
		try {
			return [
				'catalog' => Catalog::parse([
					'title' => $doc['title'] ?? null,
					'icon' => $doc['icon'] ?? null,
					'categories' => $doc['categories'] ?? [],
					'links' => $doc['links'] ?? [],
				]),
				'upgrade' => false,
			];
		} catch (InvalidCatalog) {
			throw new UnreadableCatalog();
		}
	}
}
