/**
 * Server-provided boot data (SettingsPage inline script `window.lwTranslate`).
 */
const boot = window.lwTranslate || {};

export const VERSION = boot.version || '';
export const NAMESPACE = boot.namespace || 'lw-translate/v1';
export const DOCS_URL =
	boot.docsUrl || 'https://github.com/lwplugins/lw-translate#readme';
