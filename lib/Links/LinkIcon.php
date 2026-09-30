<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 André Wiesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Links;

final readonly class LinkIcon {
	private function __construct(
		private ?string $chosenAbsoluteUrl,
		private bool $monochrome,
	) {
	}

	public static function none(): self {
		return new self(null, true);
	}

	/**
	 * @param bool $monochrome Nextcloud core icons are black artwork. Uploaded images are not.
	 */
	public static function chosen(string $absoluteUrl, bool $monochrome): self {
		if ($absoluteUrl === '') {
			throw new \InvalidArgumentException('chosen icon URL must not be empty');
		}

		return new self($absoluteUrl, $monochrome);
	}

	public function tileUrl(): ?string {
		return $this->chosenAbsoluteUrl;
	}

	public function imageUrl(string $defaultAppIconUrl): string {
		return $this->chosenAbsoluteUrl ?? $defaultAppIconUrl;
	}

	/**
	 * Black artwork, including the app icon used when a link has none.
	 * Dark mode inverts it. A stored upload keeps its own colors.
	 */
	public function monochrome(): bool {
		return $this->monochrome;
	}
}
