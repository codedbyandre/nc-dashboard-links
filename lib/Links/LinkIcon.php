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
	) {
	}

	public static function none(): self {
		return new self(null);
	}

	public static function chosen(string $absoluteUrl): self {
		if ($absoluteUrl === '') {
			throw new \InvalidArgumentException('chosen icon URL must not be empty');
		}

		return new self($absoluteUrl);
	}

	public function tileUrl(): ?string {
		return $this->chosenAbsoluteUrl;
	}

	public function imageUrl(string $defaultAppIconUrl): string {
		return $this->chosenAbsoluteUrl ?? $defaultAppIconUrl;
	}
}
