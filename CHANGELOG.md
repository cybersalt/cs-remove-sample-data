# Changelog

## 🚀 Version 1.0.1 (May 28, 2026)

### 🔧 Improvements
- **Update server moved to Cybersalt-hosted endpoint.** The Joomla update check now goes through `https://www.cybersalt.com/index.php?option=com_csreleasemanager&task=api.updatexml&format=raw&element=com_removesampledata` (served by cs-Release-Manager) rather than the static GitHub raw URL. Future versions are published through cs-Release-Manager so cybersalt.com is the single source of truth for available releases.
- The static `update.xml` on GitHub raw is retained as a fallback so sites still running v1.0.0 (whose manifest points at the old URL) can find v1.0.1 and migrate. From v1.0.1 onwards the cs-Release-Manager endpoint is authoritative.

## 🚀 Version 1.0.0 (May 28, 2026)

First public release.

### 📦 What it does

A Joomla 5/6 administrator component that detects and removes the items installed by Joomla's core sample-data plugins (Blog and Multilingual), with preview, per-item opt-out, type/title/status filters, and a full activity log.

### 📦 Features

- **Blog sample-data removal.** Detects every item the core `plg_sampledata_blog` plugin creates: ~11 articles, 4 categories, 4 tags, 3 menu types, ~17 menu items, ~12 modules, 1 custom field + group, 1 editorial workflow with 9 stages and 12 transitions.
- **Multilingual sample-data removal.** Detects the per-language menus, language switcher module, per-language language-menu modules, per-language sample categories and articles, and optionally disables `plg_system_languagefilter`.
- **Locale-aware detection.** Aliases are reconstructed from translated titles via `ApplicationHelper::stringURLSafe()` using the same language file the sample-data plugin used, so detection works on non-English sites.
- **Blog-on-multilingual detection.** When Joomla's blog plugin is invoked on a site that has content languages installed, it appends `-{lang}` to every alias and ` ({LANG})` to every title (`Blog (en-GB)` → `blog-en-gb`, `0main-menu-blog-en-gb`, etc.). The Blog detector reads `#__languages` and computes both `-{lang}` and `_{lang}` alias variants plus title variants so these get caught as well.
- **Tight multilingual scope.** The Multilingual detector gates the entire detection on whether at least one `mainmenu-{lang}` menutype (the multilingual plugin's signature artefact) is present, so blog-on-multilingual items don't false-positive as multilingual sample data.
- **Edited-item safeguard.** Items whose title no longer matches the sample-data title, or that were edited more than 120 seconds after creation, are flagged with an "Edited" badge so you can review them before deciding.
- **Items list view** with per-column dropdown filters (Source / Type / Title / Status), sortable columns, pagination, and stats cards that link into pre-filtered views. Dashboard badges drill straight into the Items list.
- **Scan view** as a single flat table with one filter dropdown per column, master checkbox in the column header, shift-click range selection, and "all ticked by default" so the common case is one click to confirm. Click any row title to open it in core Joomla.
- **Confirmation modal** with a "Yes, I have a site backup and want to remove the ticked items" checkbox. Lock-out overlay during the run.
- **Activity log + log viewer.** Every action (and side-effect like disabling a plugin) is recorded to a DB log table. The viewer has the standard Cybersalt rig — Refresh / Download CSV / Clear-all-with-modal, hotlinked stats cards, text filter, color-coded levels, expandable detail.

### 🔐 Security baseline

Passes the Cybersalt security review with zero HIGH and zero MEDIUM findings.

- Every controller method gates on `PermissionHelper::requireManage` / `requireScan` / `requireRemove`.
- Component dispatcher enforces `core.manage` at the component boundary as defence-in-depth.
- Every state-changing endpoint validates the Joomla form token, with a same-origin Referer/Origin fallback that survives session-token rotation (e.g. Remember-Me re-auth) without losing CSRF defence.
- All SQL uses `quoteName()` for identifiers and either bound parameters or strict `(int)` casts for values.
- All template echoes pass through `$this->escape()`, including defensive escaping on values that are safe today but might become unsafe under future refactors.
- CSV log download sanitises every cell against formula-injection (Excel/LibreOffice/Numbers).
- Token-refresh endpoint hardens its headers (`no-store`, `nosniff`, etc.) and surfaces only what the browser strictly needs.
- Exception messages and request-header values that surface in flash messages are HTML-escaped before interpolation.
- No path-traversal primitives, no file inclusion from request, no debug code, no hardcoded secrets.

### 🏗 How detection works

The sample-data plugins generate aliases at runtime from translated titles via `ApplicationHelper::stringURLSafe()`. v1.0.0 reuses the exact same algorithm fed by the exact same language file the plugin used, then looks up matching items by alias in their correct context (category, menu type, etc.). For blog-on-multilingual installs, the detector additionally enumerates `#__languages` and computes the `-{lang}` / `_{lang}` alias variants Joomla's blog plugin appends. Detection works on any locale without locale-specific code.

### 🏛 Architecture

- Joomla 6-native MVC component (works on J5+ too).
- PHP 8.1+, modern Toolbar API, `DatabaseInterface` DI, `Joomla\Input\Input`, web component patterns, no deprecated APIs.
- Tight MVC split: detectors and removers in `Service/`, list/state in `Model/`, presentation in `View/` and `tmpl/`.
- Token-refresh AJAX endpoint baked into the component (Joomla 6 doesn't ship the legacy `plg_system_token` that earlier Joomla versions used for this).
- Scan view inlines its JS and CSS directly into the page HTML to sidestep proxy/CDN cache issues with `/media/` assets.

### 🎨 UX

- Single "Tick all" button at top, shift-click range selection within visible rows, per-column filter dropdowns.
- Per-section card design replaced with one long flat table (cleaner for the "tick what you want gone" mental model).
- Dark-mode safe — no `bg-light` / `bg-white`, no `btn-outline-*` variants. Edited-row highlight is a 4 px warning-yellow left border that themes correctly.
- Standard Joomla-style master checkbox in the column header, indeterminate state when partially ticked.
- Clickable row titles open the underlying record in the matching core Joomla editor (article → `com_content`, menu item → `com_menus`, module → `com_modules`, etc.).
- Comfortable modal padding via component-scoped CSS that survives Atum's overrides.

### ⚙️ Settings

- Log retention period.
- Republish the main menu module after multilingual removal (the multilingual sample-data plugin unpublishes it; this opt-in restores it).
