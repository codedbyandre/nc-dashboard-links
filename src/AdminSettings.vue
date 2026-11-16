<!--
  SPDX-FileCopyrightText: 2026 André Wiesehoff
  SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcSettingsSection
		:name="t('dashboard_links', 'Company links')"
		:description="t('dashboard_links', 'Links sit under their category. Links without a category stay in Default.')">
		<NcNoteCard type="info">
			{{ t('dashboard_links', 'Opening a link sends the user\'s browser to that address. The response sets Referrer-Policy no-referrer. The destination can still see the IP address and usual browser headers. List those destinations in your instance privacy notice. Fetch favicon requests that site from this server. If the site returns no image, this server asks Google for a favicon of the hostname.') }}
		</NcNoteCard>
		<NcNoteCard v-if="staleNotice" type="warning">
			{{ t('dashboard_links', 'The catalog was changed. The editor now shows the saved catalog.') }}
		</NcNoteCard>
		<NcNoteCard v-for="(notice, noticeIndex) in notices" :key="'notice-' + noticeIndex" type="info">
			{{ notice }}
		</NcNoteCard>

		<div v-if="externalSites.length > 0" class="dashboard-links-toolbar">
			<NcButton :disabled="importing || saving" @click="importExternalSites">
				{{ t('dashboard_links', 'Import from External sites') }}
			</NcButton>
		</div>

		<section class="dashboard-links-group">
			<h3>{{ t('dashboard_links', 'Title and icon') }}</h3>
			<p class="dashboard-links-hint">
				{{ t('dashboard_links', 'Shown on the Dashboard tile and on the All links page. Without a title, the tile is called Company links.') }}
			</p>
			<div class="dashboard-links-row">
				<NcTextField
					class="dashboard-links-title"
					:label="t('dashboard_links', 'Title')"
					:maxlength="TITLE_MAX"
					:modelValue="catalog.title ?? ''"
					@update:modelValue="setCatalogTitle" />
				<IconField v-model="catalog.icon" :coreIcons="coreIcons" />
			</div>
		</section>

		<section
			v-for="group in groups"
			:key="group.category?.id ?? 'default'"
			class="dashboard-links-group"
			:class="{
				'dashboard-links-group--category': group.category !== null,
				'dashboard-links-group--invalid': group.category !== null && categoryInvalid(group.category),
			}">
			<h3 v-if="group.category === null">
				{{ t('dashboard_links', 'Default') }}
			</h3>
			<div v-else class="dashboard-links-category-head">
				<NcTextField
					class="dashboard-links-title"
					:label="t('dashboard_links', 'Category')"
					:modelValue="group.category.title"
					@update:modelValue="setCategoryTitle(group.category, $event)" />
				<NcButton variant="tertiary" @click="removeCategory(group.category)">
					{{ t('dashboard_links', 'Remove') }}
				</NcButton>
			</div>
			<p class="dashboard-links-kicker">
				{{ t('dashboard_links', 'Links') }}
			</p>
			<p v-if="group.linkIndices.length === 0" class="dashboard-links-empty">
				{{ t('dashboard_links', 'No links in this group yet.') }}
			</p>
			<ul v-else class="dashboard-links-list">
				<li
					v-for="index in group.linkIndices"
					:key="catalog.links[index].id"
					class="dashboard-links-row"
					:class="{ 'dashboard-links-row--invalid': rowInvalid(index) }">
					<NcTextField
						class="dashboard-links-title"
						:label="t('dashboard_links', 'Title')"
						:modelValue="catalog.links[index].title"
						@update:modelValue="setRowTitle(catalog.links[index], $event)" />
					<NcTextField
						class="dashboard-links-href"
						:label="t('dashboard_links', 'URL')"
						:modelValue="catalog.links[index].href"
						placeholder="https://"
						@update:modelValue="setRowHref(catalog.links[index], $event)" />
					<IconField
						v-model="catalog.links[index].icon"
						:coreIcons="coreIcons"
						:faviconHref="catalog.links[index].href" />
					<NcCheckboxRadioSwitch
						type="switch"
						:modelValue="catalog.links[index].enabled"
						@update:modelValue="catalog.links[index].enabled = $event">
						{{ t('dashboard_links', 'Enabled') }}
					</NcCheckboxRadioSwitch>
					<NcCheckboxRadioSwitch
						type="switch"
						:modelValue="catalog.links[index].newTab"
						@update:modelValue="catalog.links[index].newTab = $event">
						{{ t('dashboard_links', 'Open in new tab') }}
					</NcCheckboxRadioSwitch>
					<div class="dashboard-links-row-actions">
						<NcButton
							variant="tertiary"
							:disabled="!canMoveUp(index)"
							@click="moveRow(index, -1)">
							{{ t('dashboard_links', 'Move up') }}
						</NcButton>
						<NcButton
							variant="tertiary"
							:disabled="!canMoveDown(index)"
							@click="moveRow(index, 1)">
							{{ t('dashboard_links', 'Move down') }}
						</NcButton>
						<NcButton variant="tertiary" @click="removeRow(index)">
							{{ t('dashboard_links', 'Remove') }}
						</NcButton>
					</div>
				</li>
			</ul>
			<NcButton @click="addRow(group.category?.id ?? null)">
				{{ t('dashboard_links', 'Add link') }}
			</NcButton>
		</section>

		<div class="dashboard-links-toolbar">
			<NcButton @click="addCategory">
				{{ t('dashboard_links', 'Add category') }}
			</NcButton>
		</div>

		<div ref="saveAnchor" class="dashboard-links-save">
			<NcNoteCard v-if="saveError || fieldErrors.length > 0" type="error">
				<p v-if="saveError">
					{{ saveError }}
				</p>
				<ul v-if="fieldErrors.length > 0" class="dashboard-links-notes">
					<li v-for="(error, errorIndex) in fieldErrors" :key="errorIndex">
						{{ formatFieldError(error) }}
					</li>
				</ul>
			</NcNoteCard>
			<NcNoteCard v-if="savedNotice" type="success">
				{{ savedNotice }}
			</NcNoteCard>
			<NcButton variant="primary" :disabled="saving" @click="save">
				{{ t('dashboard_links', 'Save') }}
			</NcButton>
		</div>
	</NcSettingsSection>
</template>

<script setup lang="ts">
import type { CatalogEnvelope, Category, CoreIconChoice, FieldError, Row } from './types.ts'

import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { confirmPassword } from '@nextcloud/password-confirmation'
import { generateOcsUrl, generateUrl } from '@nextcloud/router'
import { computed, onMounted, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import IconField from './IconField.vue'
import { axiosBody, axiosStatus } from './axiosError.ts'
import { normalizeHttps } from './https.ts'

import '@nextcloud/password-confirmation/style.css'

interface Group {
	/** Null is the Default group of links without a category */
	category: Category | null
	/** Indices into the flat link list */
	linkIndices: number[]
}

interface ExternalSite {
	id?: unknown
	name?: unknown
	url?: unknown
	redirect?: unknown
}

/** Catalog::TITLE_MAX */
const TITLE_MAX = 80

const catalog = ref(cloneEnvelope(loadState<CatalogEnvelope>('dashboard_links', 'catalog')))
const externalSitesAvailable = loadState<boolean>('dashboard_links', 'externalSitesAvailable', false)
const externalSites = ref<ExternalSite[]>([])
const coreIcons = loadState<CoreIconChoice[]>('dashboard_links', 'coreIcons', [])
const fieldErrors = ref<FieldError[]>([])
const notices = ref<string[]>([])
const saveError = ref<string | null>(null)
const savedNotice = ref<string | null>(null)
const staleNotice = ref(false)
const saving = ref(false)
const importing = ref(false)
const saveAnchor = ref<HTMLElement | null>(null)

onMounted(async () => {
	if (!externalSitesAvailable) {
		return
	}
	const loaded = await loadExternalSites()
	if (!loaded) {
		notices.value = [t('dashboard_links', 'Could not load External sites.')]
	}
})

const groups = computed<Group[]>(() => [null, ...catalog.value.categories].map((category) => ({
	category,
	linkIndices: linkIndicesFor(category?.id ?? null),
})))

/**
 * Flat-array indices of links in one category group (null = Default).
 *
 * @param categoryId Category id or null for Default
 */
function linkIndicesFor(categoryId: string | null): number[] {
	const indices: number[] = []
	catalog.value.links.forEach((row, index) => {
		if (row.categoryId === categoryId) {
			indices.push(index)
		}
	})
	return indices
}

/**
 * @param value Field value
 */
function setCatalogTitle(value: string | number): void {
	catalog.value.title = String(value)
}

/**
 * @param category Editor category
 * @param value Field value
 */
function setCategoryTitle(category: Category, value: string | number): void {
	category.title = String(value)
}

/**
 * @param row Editor row
 * @param value Field value
 */
function setRowTitle(row: Row, value: string | number): void {
	row.title = String(value)
}

/**
 * @param row Editor row
 * @param value Field value
 */
function setRowHref(row: Row, value: string | number): void {
	row.href = String(value)
}

/**
 * Lowercase UUIDv4. randomUUID exists only in a secure context, so HTTP admin pages use getRandomValues.
 */
function uuidV4(): string {
	const webCrypto = globalThis.crypto
	if (webCrypto !== undefined && typeof webCrypto.randomUUID === 'function') {
		return webCrypto.randomUUID().toLowerCase()
	}
	if (webCrypto === undefined || typeof webCrypto.getRandomValues !== 'function') {
		throw new Error('crypto.getRandomValues is not available')
	}
	const bytes = new Uint8Array(16)
	webCrypto.getRandomValues(bytes)
	bytes[6] = (bytes[6] & 0x0f) | 0x40
	bytes[8] = (bytes[8] & 0x3f) | 0x80
	const hex = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('')
	return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`
}

/**
 * Mint a new lowercase UUIDv4 row.
 *
 * @param categoryId Assigned category or null for Default
 */
function mintRow(categoryId: string | null = null): Row {
	return {
		id: uuidV4(),
		title: '',
		href: '',
		icon: null,
		categoryId,
		enabled: true,
		newTab: false,
	}
}

/**
 * Copy a category row.
 *
 * @param row Source category
 */
function cloneCategory(row: Partial<Category>): Category {
	return {
		id: typeof row.id === 'string' ? row.id : uuidV4(),
		title: typeof row.title === 'string' ? row.title : '',
	}
}

/**
 * Copy a link row. Missing categoryId stays in the default list.
 *
 * @param row Source row
 */
function cloneRow(row: Partial<Row>): Row {
	return {
		id: typeof row.id === 'string' ? row.id : uuidV4(),
		title: typeof row.title === 'string' ? row.title : '',
		href: typeof row.href === 'string' ? row.href : '',
		icon: typeof row.icon === 'string' && row.icon !== '' ? row.icon : null,
		categoryId: typeof row.categoryId === 'string' && row.categoryId !== '' ? row.categoryId : null,
		enabled: row.enabled === true,
		newTab: row.newTab === true,
	}
}

/**
 * Copy the catalog envelope.
 *
 * @param raw Catalog from PHP or OCS
 */
function cloneEnvelope(raw: CatalogEnvelope): CatalogEnvelope {
	return {
		revision: typeof raw.revision === 'string' ? raw.revision : '',
		title: typeof raw.title === 'string' ? raw.title : null,
		icon: typeof raw.icon === 'string' && raw.icon !== '' ? raw.icon : null,
		categories: Array.isArray(raw.categories) ? raw.categories.map(cloneCategory) : [],
		links: Array.isArray(raw.links) ? raw.links.map(cloneRow) : [],
	}
}

/**
 * PUT body for one link.
 *
 * @param row Editor row
 */
function wireRow(row: Row): Row {
	return {
		id: row.id,
		title: row.title,
		href: row.href,
		icon: row.icon,
		categoryId: row.categoryId,
		enabled: row.enabled,
		newTab: row.newTab,
	}
}

/**
 * A row nobody filled in. Save leaves it out.
 *
 * @param row Editor row
 */
function blankLink(row: Row): boolean {
	return row.title.trim() === '' && row.href.trim() === ''
}

/**
 * PUT envelope from the editor.
 *
 * @param envelope Editor catalog
 */
function wireEnvelope(envelope: CatalogEnvelope): CatalogEnvelope {
	return {
		revision: envelope.revision,
		title: envelope.title,
		icon: envelope.icon,
		categories: envelope.categories.map((category) => ({
			id: category.id,
			title: category.title,
		})),
		links: envelope.links.filter((row) => !blankLink(row)).map(wireRow),
	}
}

/**
 * Insert after the last link of that category group in the flat array.
 *
 * @param categoryId Category id or null for Default
 */
function addRow(categoryId: string | null): void {
	const row = mintRow(categoryId)
	const last = lastIndexFor(categoryId)
	if (last === -1) {
		catalog.value.links.push(row)
		return
	}
	catalog.value.links.splice(last + 1, 0, row)
}

/**
 * @param categoryId Category id or null for Default
 */
function lastIndexFor(categoryId: string | null): number {
	let last = -1
	catalog.value.links.forEach((row, index) => {
		if (row.categoryId === categoryId) {
			last = index
		}
	})
	return last
}

/**
 * @param index Row index
 */
function removeRow(index: number): void {
	catalog.value.links.splice(index, 1)
}

/**
 * @param index Row index
 */
function canMoveUp(index: number): boolean {
	const row = catalog.value.links[index]
	if (row === undefined) {
		return false
	}
	const indices = linkIndicesFor(row.categoryId)
	return indices.indexOf(index) > 0
}

/**
 * @param index Row index
 */
function canMoveDown(index: number): boolean {
	const row = catalog.value.links[index]
	if (row === undefined) {
		return false
	}
	const indices = linkIndicesFor(row.categoryId)
	const pos = indices.indexOf(index)
	return pos >= 0 && pos < indices.length - 1
}

/**
 * Reorder within the same categoryId group only.
 *
 * @param index Row index
 * @param direction -1 up, 1 down
 */
function moveRow(index: number, direction: -1 | 1): void {
	const row = catalog.value.links[index]
	if (row === undefined) {
		return
	}
	const indices = linkIndicesFor(row.categoryId)
	const pos = indices.indexOf(index)
	const swapWith = indices[pos + direction]
	if (swapWith === undefined) {
		return
	}
	const copy = [...catalog.value.links]
	const current = copy[index]
	const other = copy[swapWith]
	if (current === undefined || other === undefined) {
		return
	}
	copy[index] = other
	copy[swapWith] = current
	catalog.value.links = copy
}

/**
 * Append a named category.
 */
function addCategory(): void {
	catalog.value.categories.push({
		id: uuidV4(),
		title: t('dashboard_links', 'New category'),
	})
}

/**
 * Drop a category and return its links to the default list.
 *
 * @param category Editor category
 */
function removeCategory(category: Category): void {
	catalog.value.categories = catalog.value.categories.filter((other) => other.id !== category.id)
	for (const row of catalog.value.links) {
		if (row.categoryId === category.id) {
			row.categoryId = null
		}
	}
}

/**
 * PUT the whole envelope.
 */
async function save(): Promise<void> {
	try {
		await confirmPassword()
	} catch {
		return
	}

	const local = localProblems()
	fieldErrors.value = local
	saveError.value = null
	savedNotice.value = null
	staleNotice.value = false
	if (local.length > 0) {
		saveError.value = t('dashboard_links', 'The catalog could not be saved.')
		revealSaveError()
		return
	}

	saving.value = true
	try {
		const { data } = await axios.put(
			generateOcsUrl('/apps/dashboard_links/api/v1/catalog'),
			wireEnvelope(catalog.value),
		)
		const saved = asEnvelope(data)
		if (saved !== null) {
			catalog.value = saved
		}
		savedNotice.value = t('dashboard_links', 'The catalog was saved.')
	} catch (error: unknown) {
		const status = axiosStatus(error)
		const body = axiosBody(error)
		if (status === 400) {
			fieldErrors.value = asFieldErrors(body)
			saveError.value = t('dashboard_links', 'The catalog could not be saved.')
			revealSaveError()
		} else if (status === 412) {
			const current = asEnvelope(body)
			if (current !== null) {
				catalog.value = current
				staleNotice.value = true
			} else {
				saveError.value = t('dashboard_links', 'The catalog could not be saved.')
			}
		} else {
			saveError.value = t('dashboard_links', 'The catalog could not be saved.')
			revealSaveError()
		}
	} finally {
		saving.value = false
	}
}

/**
 * External sites embeds a site unless redirect is set. The iframe lives on
 * `/apps/external/{id}/`, not on the framed URL.
 *
 * @param site External sites admin row
 */
function siteOpensInIframe(site: ExternalSite): boolean {
	const redirect = site.redirect
	return redirect !== true && redirect !== 1 && redirect !== '1'
}

/**
 * @param value External site id
 */
function externalSiteId(value: unknown): string | null {
	if (typeof value === 'number' && Number.isInteger(value) && value > 0) {
		return String(value)
	}
	if (typeof value === 'string' && /^[1-9]\d*$/.test(value)) {
		return value
	}
	return null
}

/**
 * Absolute https URL of the in-Nextcloud External sites page.
 *
 * @param id Numeric site id
 */
function externalSitePageHref(id: string): string {
	const path = generateUrl('/apps/external/{id}/', { id })
	return new URL(path, window.location.href).href
}

/**
 * @param site External sites admin row
 */
function hrefForExternalSite(site: ExternalSite): string | null {
	if (!siteOpensInIframe(site)) {
		return typeof site.url === 'string' ? site.url : null
	}
	const id = externalSiteId(site.id)
	return id === null ? null : externalSitePageHref(id)
}

/**
 * @return false when the External sites request failed
 */
async function loadExternalSites(): Promise<boolean> {
	if (!externalSitesAvailable) {
		externalSites.value = []
		return true
	}
	try {
		const { data } = await axios.get(generateOcsUrl('/apps/external/api/v1/sites'))
		externalSites.value = sitesFromExternalPayload(readOcsData(data))
		return true
	} catch {
		externalSites.value = []
		return false
	}
}

/**
 * Browser-only import. Nothing is stored until Save.
 */
async function importExternalSites(): Promise<void> {
	importing.value = true
	notices.value = []
	try {
		const loaded = await loadExternalSites()
		if (!loaded) {
			notices.value = [t('dashboard_links', 'Could not load External sites.')]
			return
		}
		const existing = new Set<string>()
		for (const row of catalog.value.links) {
			const normalized = normalizeHttps(row.href)
			if (normalized !== null) {
				existing.add(normalized)
			}
		}

		let imported = 0
		let skippedScheme = 0
		let skippedDup = 0
		for (const site of externalSites.value) {
			const title = typeof site.name === 'string' ? site.name : ''
			const href = hrefForExternalSite(site)
			if (href === null) {
				skippedScheme += 1
				continue
			}
			const normalized = normalizeHttps(href)
			if (normalized === null) {
				skippedScheme += 1
				continue
			}
			if (existing.has(normalized)) {
				skippedDup += 1
				continue
			}
			existing.add(normalized)
			catalog.value.links.push({ ...mintRow(), title, href })
			imported += 1
		}

		if (skippedScheme > 0) {
			notices.value.push(t('dashboard_links', 'Skipped {count} External sites that are not https.', { count: skippedScheme }))
		}
		if (skippedDup > 0) {
			notices.value.push(t('dashboard_links', 'Skipped {count} External sites already in the catalog.', { count: skippedDup }))
		}
		if (imported > 0) {
			notices.value.push(t('dashboard_links', 'Added {count} External sites. Save to store them.', { count: imported }))
		} else if (skippedScheme === 0 && skippedDup === 0) {
			notices.value.push(t('dashboard_links', 'No External sites to import.'))
		}
	} catch {
		notices.value = [t('dashboard_links', 'Could not load External sites.')]
	} finally {
		importing.value = false
	}
}

/**
 * @param index Row index
 */
function rowInvalid(index: number): boolean {
	return fieldErrors.value.some((error) => error.index === index && error.field !== 'category')
}

/**
 * @param category Editor category
 */
function categoryInvalid(category: Category): boolean {
	const index = catalog.value.categories.findIndex((other) => other.id === category.id)
	return fieldErrors.value.some((error) => error.index === index && error.field === 'category')
}

/**
 * What Save can tell is wrong before asking the server.
 */
function localProblems(): FieldError[] {
	const errors: FieldError[] = []
	catalog.value.categories.forEach((category, index) => {
		if (category.title.trim() === '') {
			errors.push({
				index,
				field: 'category',
				message: t('dashboard_links', 'Enter a category name.'),
			})
		}
	})
	catalog.value.links.forEach((row, index) => {
		if (blankLink(row)) {
			return
		}
		if (row.title.trim() === '') {
			errors.push({
				index,
				field: 'title',
				message: t('dashboard_links', 'Enter a title.'),
			})
		}
		if (normalizeHttps(row.href) === null) {
			errors.push({
				index,
				field: 'href',
				message: t('dashboard_links', 'Enter an https URL.'),
			})
		}
	})
	return errors
}

/**
 * Bring the error next to Save into view.
 */
function revealSaveError(): void {
	saveAnchor.value?.scrollIntoView({ block: 'nearest' })
}

/**
 * @param error Field error from PUT 400
 */
function formatFieldError(error: FieldError): string {
	return t('dashboard_links', 'Row {index}, {field}: {message}', {
		index: error.index,
		field: error.field,
		message: error.message,
	})
}

/**
 * @param payload OCS or raw body
 */
function readOcsData(payload: unknown): unknown {
	if (payload !== null && typeof payload === 'object' && 'ocs' in payload) {
		const ocs = payload.ocs
		if (ocs !== null && typeof ocs === 'object' && 'data' in ocs) {
			return ocs.data
		}
	}
	return payload
}

/**
 * @param payload OCS or raw catalog
 */
function asEnvelope(payload: unknown): CatalogEnvelope | null {
	const data = readOcsData(payload)
	if (data === null || typeof data !== 'object') {
		return null
	}
	const candidate = data as Partial<CatalogEnvelope>
	if (typeof candidate.revision !== 'string') {
		return null
	}
	return cloneEnvelope({
		revision: candidate.revision,
		title: candidate.title ?? null,
		icon: candidate.icon ?? null,
		categories: Array.isArray(candidate.categories) ? candidate.categories : [],
		links: Array.isArray(candidate.links) ? candidate.links : [],
	})
}

/**
 * @param payload OCS 400 body
 */
function asFieldErrors(payload: unknown): FieldError[] {
	const data = readOcsData(payload)
	if (data === null || typeof data !== 'object' || !('errors' in data) || !Array.isArray(data.errors)) {
		return []
	}
	return data.errors.filter((item): item is FieldError => {
		return item !== null
			&& typeof item === 'object'
			&& typeof item.index === 'number'
			&& typeof item.field === 'string'
			&& typeof item.message === 'string'
	})
}

/**
 * @param payload External sites OCS body
 */
function sitesFromExternalPayload(payload: unknown): ExternalSite[] {
	const rows = Array.isArray(payload)
		? payload
		: payload !== null && typeof payload === 'object' && 'sites' in payload && Array.isArray(payload.sites)
			? payload.sites
			: []
	return rows.filter((row): row is ExternalSite => row !== null && typeof row === 'object')
}
</script>

<style scoped>
.dashboard-links-notes {
	margin: 0;
	padding-inline-start: 1.25rem;
}

.dashboard-links-toolbar,
.dashboard-links-save {
	display: flex;
	flex-direction: column;
	align-items: flex-start;
	flex-wrap: wrap;
	gap: 0.75rem;
	margin-block: 1rem;
}

.dashboard-links-group {
	margin-block-end: 1.25rem;
	padding: 1rem 1rem 0.25rem;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large, 12px);
	background-color: var(--color-background-hover);
}

.dashboard-links-group--category {
	border-inline-start: 4px solid var(--color-primary-element);
}

.dashboard-links-group--invalid {
	border-color: var(--color-error);
}

.dashboard-links-group h3 {
	margin: 0;
	font-size: 1.05rem;
}

.dashboard-links-hint {
	margin: 0.25rem 0 0.75rem;
	color: var(--color-text-maxcontrast);
}

.dashboard-links-kicker {
	margin: 0.75rem 0 0.5rem;
	color: var(--color-text-maxcontrast);
	font-size: 0.85rem;
	font-weight: 600;
}

.dashboard-links-empty {
	margin: 0 0 0.75rem;
	padding: 0.85rem 1rem;
	border-radius: var(--border-radius-large, 12px);
	background-color: var(--color-main-background);
	color: var(--color-text-maxcontrast);
}

.dashboard-links-group .dashboard-links-list {
	margin-inline-start: 0.25rem;
	padding: 0.75rem 0.75rem 0.25rem;
	border-radius: var(--border-radius-large, 12px);
	background-color: var(--color-main-background);
}

.dashboard-links-group .dashboard-links-list + .button,
.dashboard-links-group > .button {
	margin-block: 0.75rem 0.5rem;
}

.dashboard-links-category-head {
	display: flex;
	flex-wrap: wrap;
	align-items: flex-end;
	gap: 0.75rem 1rem;
	margin-block-end: 0.75rem;
}

.dashboard-links-list {
	list-style: none;
	margin: 0 0 0.75rem;
	padding: 0;
}

.dashboard-links-row {
	display: flex;
	flex-wrap: wrap;
	align-items: flex-end;
	gap: 0.75rem 1rem;
	margin-block-end: 1.25rem;
	padding-block-end: 1rem;
}

.dashboard-links-row--invalid {
	box-shadow: inset 3px 0 0 var(--color-error);
	padding-inline-start: 0.75rem;
}

.dashboard-links-title,
.dashboard-links-href {
	flex: 1 1 16rem;
}

.dashboard-links-row-actions {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 0.5rem;
}
</style>
