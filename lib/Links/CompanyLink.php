<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 André Wiesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Links;

final readonly class CompanyLink implements \JsonSerializable {
	public const TITLE_MAX = 120;

	public string $title;

	public function __construct(
		public LinkId $id,
		string $title,
		public HttpsUrl $href,
		public ?Icon $icon,
		public ?CategoryId $categoryId,
		public bool $enabled,
		public bool $newTab,
	) {
		$title = trim($title);
		$length = mb_strlen($title);
		if ($length < 1 || $length > self::TITLE_MAX) {
			throw new InvalidLink('title', 'title must be between 1 and 120 characters');
		}
		$this->title = $title;
	}

	/**
	 * @param mixed $row untrusted wire or storage row
	 */
	public static function parse(mixed $row): self {
		if (!is_array($row)) {
			throw new InvalidLink('id', 'row must be an object');
		}
		if (array_key_exists('importance', $row)) {
			throw new InvalidLink('importance', 'importance is not used; assign a category or leave the default list');
		}

		$id = self::stringField($row, 'id');
		$title = self::stringField($row, 'title');
		$href = self::stringField($row, 'href');

		$icon = Icon::parseNullable($row['icon'] ?? null);

		$categoryId = null;
		if (array_key_exists('categoryId', $row) && $row['categoryId'] !== null) {
			$categoryId = CategoryId::tryParse($row['categoryId']);
		}

		if (!array_key_exists('enabled', $row) || !is_bool($row['enabled'])) {
			throw new InvalidLink('enabled', 'enabled must be a boolean');
		}

		// Rows stored before 1.2.0 have no newTab. They keep opening in the same tab.
		$newTab = false;
		if (array_key_exists('newTab', $row)) {
			if (!is_bool($row['newTab'])) {
				throw new InvalidLink('newTab', 'newTab must be a boolean');
			}
			$newTab = $row['newTab'];
		}

		return new self(
			LinkId::parse($id),
			$title,
			HttpsUrl::parse($href),
			$icon,
			$categoryId,
			$row['enabled'],
			$newTab,
		);
	}

	/**
	 * @return array{id: string, title: string, href: string, icon: ?string, categoryId: ?string, enabled: bool, newTab: bool}
	 */
	#[\Override]
	public function jsonSerialize(): array {
		return [
			'id' => (string)$this->id,
			'title' => $this->title,
			'href' => (string)$this->href,
			'icon' => $this->icon === null ? null : (string)$this->icon,
			'categoryId' => $this->categoryId === null ? null : (string)$this->categoryId,
			'enabled' => $this->enabled,
			'newTab' => $this->newTab,
		];
	}

	/**
	 * @param array<array-key, mixed> $row
	 */
	private static function stringField(array $row, string $field): string {
		if (!array_key_exists($field, $row) || !is_string($row[$field])) {
			throw new InvalidLink($field, $field . ' must be a string');
		}

		return $row[$field];
	}
}
