<!--
  SPDX-FileCopyrightText: 2026 André Wiesehoff
  SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="dashboard-links-icon">
		<NcSelect
			class="dashboard-links-icon-select"
			label="label"
			:inputLabel="t('dashboard_links', 'Choose an icon.')"
			:modelValue="selected"
			:options="options"
			:clearable="false"
			@update:modelValue="select" />
		<img
			v-if="previewUrl !== null"
			class="dashboard-links-icon-preview"
			:class="{ 'dashboard-links-icon-preview--mono': isCore }"
			:src="previewUrl"
			alt="">
		<NcButton @click="fileInput?.click()">
			{{ icon !== null && !isCore ? t('dashboard_links', 'Replace icon') : t('dashboard_links', 'Upload icon') }}
		</NcButton>
		<NcButton v-if="faviconHref !== undefined" @click="fetchFavicon">
			{{ t('dashboard_links', 'Fetch favicon') }}
		</NcButton>
		<NcButton v-if="icon !== null" variant="tertiary" @click="icon = null">
			{{ t('dashboard_links', 'Remove icon') }}
		</NcButton>
		<input
			ref="fileInput"
			class="hidden-upload-input"
			type="file"
			accept="image/png,image/svg+xml,image/jpeg,image/webp,.png,.svg,.jpg,.jpeg,.webp"
			@change="upload">
		<p v-if="error !== null" class="dashboard-links-icon-error" role="alert">
			{{ error }}
		</p>
	</div>
</template>

<script setup lang="ts">
import type { CoreIconChoice } from './types.ts'

import axios from '@nextcloud/axios'
import { t } from '@nextcloud/l10n'
import { confirmPassword } from '@nextcloud/password-confirmation'
import { generateUrl } from '@nextcloud/router'
import { computed, ref, useTemplateRef } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { axiosBody } from './axiosError.ts'
import { normalizeHttps } from './https.ts'

interface SelectOption {
	id: string
	label: string
}

/** Stored file name, `core:` id, or null for no icon */
const icon = defineModel<string | null>({ required: true })

const props = defineProps<{
	/** Nextcloud icons an administrator can choose */
	coreIcons: CoreIconChoice[]
	/** Address whose favicon can be fetched. Leave out to hide Fetch favicon. */
	faviconHref?: string
}>()

const NO_ICON: SelectOption = { id: 'none', label: t('dashboard_links', 'None') }

const fileInput = useTemplateRef<HTMLInputElement>('fileInput')
const error = ref<string | null>(null)

const options = computed<SelectOption[]>(() => [
	NO_ICON,
	...props.coreIcons.map((choice) => ({ id: choice.id, label: choice.label || choice.id })),
])

const isCore = computed(() => icon.value?.startsWith('core:') ?? false)

/** An uploaded image is not one of the options, so the select shows None next to its preview. */
const selected = computed(() => options.value.find((option) => option.id === icon.value) ?? NO_ICON)

const previewUrl = computed<string | null>(() => {
	if (icon.value === null) {
		return null
	}
	if (isCore.value) {
		return props.coreIcons.find((choice) => choice.id === icon.value)?.url ?? null
	}
	return generateUrl('/apps/dashboard_links/icons/{file}', { file: icon.value })
})

/**
 * @param option Chosen NcSelect option
 */
function select(option: SelectOption | null): void {
	icon.value = option === null || option.id === NO_ICON.id ? null : option.id
}

/**
 * POST /apps/dashboard_links/icons field icon.
 *
 * @param event File input change
 */
async function upload(event: Event): Promise<void> {
	const input = event.target as HTMLInputElement
	const file = input.files?.[0]
	input.value = ''
	if (file === undefined) {
		return
	}
	const form = new FormData()
	form.append('icon', file)
	await store(
		() => axios.post(generateUrl('/apps/dashboard_links/icons'), form),
		t('dashboard_links', 'The icon could not be uploaded.'),
	)
}

/**
 * POST /apps/dashboard_links/icons/favicon with the address.
 */
async function fetchFavicon(): Promise<void> {
	const failed = t('dashboard_links', 'The favicon could not be fetched.')
	const href = props.faviconHref?.trim() ?? ''
	if (normalizeHttps(href) === null) {
		error.value = failed
		return
	}
	await store(() => axios.post(generateUrl('/apps/dashboard_links/icons/favicon'), { href }), failed)
}

/**
 * Confirm the password, send the request, and take the icon the server stored.
 *
 * @param send Request that answers `{ icon }`
 * @param failed Message when the server gives no reason
 */
async function store(send: () => Promise<{ data: unknown }>, failed: string): Promise<void> {
	try {
		await confirmPassword()
	} catch {
		return
	}

	try {
		const { data } = await send()
		if (hasString(data, 'icon')) {
			icon.value = data.icon
			error.value = null
		} else {
			error.value = failed
		}
	} catch (cause: unknown) {
		const body = axiosBody(cause)
		error.value = hasString(body, 'message') ? body.message : failed
	}
}

/**
 * @param body Response body
 * @param key Property that must hold a string
 */
function hasString<Key extends string>(body: unknown, key: Key): body is Record<Key, string> {
	return body !== null
		&& typeof body === 'object'
		&& key in body
		&& typeof (body as Record<Key, unknown>)[key] === 'string'
}
</script>

<style scoped>
.dashboard-links-icon {
	display: flex;
	flex: 1 1 100%;
	flex-wrap: wrap;
	align-items: center;
	gap: 0.5rem;
}

.dashboard-links-icon-select {
	min-width: 12rem;
}

.dashboard-links-icon-preview {
	width: 32px;
	height: 32px;
	object-fit: contain;
}

.dashboard-links-icon-preview--mono {
	filter: var(--background-invert-if-dark);
}

.dashboard-links-icon-error {
	flex: 1 1 100%;
	margin: 0;
	color: var(--color-text-error, var(--color-error));
}

.hidden-upload-input {
	position: absolute;
	width: 1px;
	height: 1px;
	overflow: hidden;
	clip: rect(0, 0, 0, 0);
}
</style>
