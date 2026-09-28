<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 André Wiesehoff
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DashboardLinks\Links;

final class UnreadableCatalog extends \RuntimeException {
	public function __construct() {
		parent::__construct('stored catalog is unreadable');
	}
}
