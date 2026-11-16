// SPDX-FileCopyrightText: 2026 André Wiesehoff
// SPDX-License-Identifier: AGPL-3.0-or-later

export interface Category {
	id: string
	title: string
}

export interface Row {
	id: string
	title: string
	href: string
	icon: string | null
	categoryId: string | null
	enabled: boolean
	newTab: boolean
}

export interface CatalogEnvelope {
	revision: string
	/** Null keeps the translated "Company links" */
	title: string | null
	/** Stored file name, `core:` id, or null for the app icon */
	icon: string | null
	categories: Category[]
	links: Row[]
}

export interface CoreIconChoice {
	id: string
	url: string
	label: string
}

export interface FieldError {
	index: number
	field: string
	message: string
}
