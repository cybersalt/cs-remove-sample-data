# Changelog

## 🚀 Version 1.0.20 (May 28, 2026)

### 🐛 Bug Fixes
- **Blog sample data installed on a multilingual site went undetected.** When Joomla's blog sample-data plugin is invoked on a site that already has content languages configured, it appends `-{lang}` to every alias and `(LANG)` to every title (e.g. category `Blog (en-GB)` with alias `blog-en-gb`, menu type `0main-menu-blog-en-gb`). The previous BlogDetector only computed the *base* alias from the .ini (`blog`, `help`, etc.), so all of these multilingual variants were invisible to the Blog scan. v1.0.20 reads `#__languages`, computes both `-{lang}` and `_{lang}` alias variants plus ` ({lang})` title variants for every content language on the site, and matches them all.
- **Multilingual scan was false-positive-ing on those same items.** Because they have `language != '*'` and aliases ending in `-en-gb`, the old MultilangDetector flagged them as multilingual sample data even though they came from the Blog plugin. The MultilangDetector now gates the whole detection on whether at least one `mainmenu-{lang}` menutype (the multilingual plugin's signature artefact) is present — if there's no `mainmenu-{lang}`, the multilingual sample-data plugin was never the source, so no items are reported.

### What to do next

After installing v1.0.20:
- Multilingual scan should drop from 16 to 0 (nothing to remove) on the current state of your test site.
- Blog scan should now find the leftover `-en-gb` items so you can remove them via the Blog flow.

## 🚀 Version 1.0.19 (May 28, 2026)

### 🐛 Bug Fixes
- **The actual reason nothing past articles was being removed.** Diagnosed from the live stack trace: `ActionLogger::log()` was passing three function-call return values straight to `$query->bind()`, which takes its `$value` parameter by reference. PHP 8.x throws a hard `Error` when a function-return value (or method chain, or ternary) is passed to a `&$value` parameter — `Factory::getDate()->toSql()`, `substr(...)`, and `is_string(...) ? ... : json_encode(...)` were all illegal. The crash happened on the **very first** logger call at the top of `BlogRemover::remove()` (the `start` info entry), aborting the entire removal before any deletion code ran — except in earlier iterations of the code path where articles got deleted before reaching the broken logger. Every subsequent attempt then crashed before touching tags, categories, menus, etc. Fixed by assigning every bind value to a local variable first.
- **Logger crashes no longer take the removal with them.** Wrapped the entire `ActionLogger::log()` body in a try/catch that swallows any failure and writes a one-line message to PHP's `error_log()`. Logging is now strictly best-effort — a problem with the logger will never abort the action it's meant to be tracking.

### What to do after installing v1.0.19

Articles got cleared in earlier attempts, but tags, categories, menus, modules, workflows, and custom fields are still present on the test site. Open the Blog scan in the admin, leave everything ticked, and run Remove — this time it should actually finish. After it completes, the Joomla "another tag has the same alias" error on a sample-data reinstall will be gone too.

## 🚀 Version 1.0.18 (May 28, 2026)

### 🐛 Bug Fixes
- **"You need to tick the confirmation box" message after ticking the box.** The submit handler called `form.querySelectorAll('button, input').forEach(el => el.disabled = true)` to prevent double-submit, BEFORE calling `form.submit()`. Browsers strip `disabled` form inputs from the POST body, so `confirm_remove=1` (and every `remove[<group>][]=<id>` checkbox we were trying to submit) was being dropped. The downstream `if (!$confirmed)` check then fired with the misleading "you need to tick the confirmation box" message even though the user had ticked it. v1.0.18 disables only the buttons, not the inputs — the full-screen lockout overlay already prevents user interaction with everything else.
- **Modal padding still flush to edges.** The previous attempt used plain selector specificity (`.rsd-modal-body`) which Atum's stylesheet was overriding on cascade. v1.0.18 boosts specificity by scoping all rules under `#rsd-confirm` (the modal's id) AND adds `!important` so the comfortable padding wins.

## 🚀 Version 1.0.17 (May 28, 2026)

### 🐛 Bug Fixes
- **Diagnostic CSRF error instead of a generic 403 page.** v1.0.16's Tier 2 same-origin fallback was still rejecting, but the thrown 403 page didn't reveal *why* — we couldn't tell whether the Tier 1 token check failed, or whether the Tier 2 Origin/Referer headers were missing or didn't match the site host. v1.0.17 redirects to the dashboard with the diagnostic embedded in the standard yellow message bar instead of an ugly 403 page, so the failure mode is visible (e.g. `JINVALID_TOKEN [t1-not-found | t2-expected=j6.basicjoomla.com | origin=- | referer=j6.basicjoomla.com]`).
- **Tier 2 simplified to pure same-origin check.** The previous Tier 2 required POST + confirm_remove=1 + same-origin all together. The POST and confirm_remove conditions are already validated downstream in `run()` anyway; folding them into the CSRF gate could mask the real failure mode. Now Tier 2 only checks Origin/Referer host match — cleaner, easier to diagnose.

## 🚀 Version 1.0.16 (May 28, 2026)

### 🐛 Bug Fixes
- **CSRF token failure even after every cache layer was cleared** — even with v1.0.15's inline-JS approach, Cloudflare proxy off, media folder deleted, extension uninstalled, Joomla cache cleared and fresh install, the "security token did not match" error persisted. Root cause was something the diagnostic couldn't reach — likely session rotation or Remember-Me re-auth happening mid-page so the form's token was stale by submit time. v1.0.16 keeps the standard Joomla `Session::checkToken()` as the primary defence but adds a same-origin fallback: if the token check fails, the controller now also accepts the submission if it's POST, carries `confirm_remove=1`, and the request's Origin/Referer header points at this site's own hostname. Browsers strip Origin/Referer on cross-origin POST so an attacker on another site cannot forge them — defence is preserved while the token-rotation edge case stops blocking legitimate removals.
- **Token response parser more forgiving** — extraction now uses a regex to find the 32-char token anywhere in the response, so any trailing newlines, BOMs, or other wrapping characters around the token don't break the swap.

## 🚀 Version 1.0.15 (May 28, 2026)

### 🐛 Bug Fixes
- **Cloudflare edge cache was serving stale JS/CSS on every release.** Diagnosis from the live test site: Cloudflare caches `media/com_removesampledata/js/admin.js` with `Cache-Control: max-age=2592000` (30 days) and returns `cf-cache-status: HIT` even on hard-refreshes carrying `Cache-Control: no-cache` request headers. Joomla 6's WAM doesn't reliably append a cache-busting query string from `joomla.asset.json`'s `version` field — the URL Cloudflare cached was `admin.js` with no version param. Every release deployed correctly to the origin but no one saw the changes because the edge kept serving the v1.0.0 copy. v1.0.15 sidesteps the whole cache layer by **inlining the JS and CSS directly into the scan view's HTML response** via `addScriptDeclaration` / `addStyleDeclaration`. Admin page HTML is marked `Cache-Control: no-store` by Joomla, so embedding the assets in the page guarantees the user always runs the current build's code regardless of any proxy or browser cache.

## 🚀 Version 1.0.14 (May 27, 2026)

### 🐛 Bug Fixes
- **Browser was serving old cached JS/CSS** — `media/joomla.asset.json` declared `"version": "1.0.0"` and that value never got bumped between releases. Joomla's Web Asset Manager appends the asset version as the cache-busting query string on every `<script>` and `<link>`, so the URLs for `admin.js` and `admin.css` stayed exactly the same across every release. Browsers happily served the v1.0.0 cached copies forever, which is why every "this version fixes it" claim from v1.0.5 onward looked broken on the live site even when the new code was correctly deployed server-side. From v1.0.14 on, the asset version tracks the component version and the cache-bust is automatic.

### Upgrade note
On first install of v1.0.14, do one hard refresh (Ctrl+Shift+R / Cmd+Shift+R) in the admin tab to clear the stale cached assets — after that, future version bumps will auto-bust.

## 🚀 Version 1.0.13 (May 27, 2026)

### 🐛 Bug Fixes
- **Security token error now actually resolved.** The previous round of fixes assumed `plg_system_token` existed as a fallback for refreshing the token — diagnosis against the live site confirmed it doesn't exist in Joomla 6 at all (it was removed). And the keepalive endpoint only pings the session — it doesn't expose a fresh form token to swap in. v1.0.13 adds a `token.fetch` task to the component itself: `index.php?option=com_removesampledata&task=token.fetch` returns the current session's `Session::getFormToken()` as plain text. The scan JS calls this on submit, swaps the token into the form via `Joomla.replaceTokens()`, then submits — guaranteeing a current valid token regardless of how long the page has been open.
- **Confirm modal padding** — the modal-body content was pressed up against the dialog edges. Added explicit `rsd-modal-body` / `rsd-modal-header` / `rsd-modal-footer` styling with 1.25rem of horizontal padding so the text, alert and checkbox have breathing room. Also centred the dialog vertically.

## 🚀 Version 1.0.12 (May 27, 2026)

### 🐛 Bug Fixes
- **CSRF token error finally fixed** — diagnosed against the live test site via the API: `plg_system_token` (the plugin that used to expose the form token via `?plugin=token&format=raw`) **does not exist in Joomla 6**. It was deprecated. So every "refresh the token" attempt in v1.0.10 and v1.0.11 was hitting an endpoint that returned HTTP 200 with an empty body, did nothing, and the expired token kept going up with the submit. Replaced with the right pattern for Joomla 5/6:
  - The scan view now calls `HTMLHelper::_('behavior.keepalive')` instead of `$wa->useScript('keepalive')`. The behavior helper registers the keepalive script AND sets the `system.keepalive` script options (URI + interval) the script needs to actually fire. With just `useScript('keepalive')` the script loaded but its options weren't set, so it sat idle.
  - On submit, we ping `Joomla.options['system.keepalive'].uri` (the URL the behavior helper registered) to touch the session immediately before submitting. The form's CSRF token is bound to the session, so as long as the session stays alive the token stays valid.

## 🚀 Version 1.0.11 (May 27, 2026)

### 🐛 Bug Fixes
- **CSRF token refresh actually works now** — the v1.0.10 attempt called `Joomla.replaceTokens()` with the raw response from `index.php?option=com_ajax&plugin=token&format=raw`, which returns `<token>=1`. `replaceTokens()` has a strict 32-char regex check on its input and was silently rejecting the whole string, so the form's expired token remained. Now the response is split on `=` and only the first segment is passed in, matching what Joomla's own keepalive script does.
- **Submit now blocks on token refresh** — the refresh was previously fired when the modal opened (fire-and-forget), so the user could click Remove before the network round-trip finished and the old token would still be in flight. Submit is now intercepted: the lockout overlay shows immediately, the token refresh runs to completion, then the form is submitted programmatically.
- **Keepalive script added to the scan view** — Joomla's built-in session-keepalive asset (`useScript('keepalive')`) is now loaded on the scan view, so the admin session stays warm while the user reviews items. Belt and braces alongside the on-submit refresh.

## 🚀 Version 1.0.10 (May 27, 2026)

### 🐛 Bug Fixes
- **"Security token did not match" on submit after a long page open** — Joomla's CSRF token expires with the session; when the scan view sat open while the user reviewed items, the token in the form had often expired by the time the user clicked Remove. The confirm modal now silently refreshes the form's CSRF token from `index.php?option=com_ajax&plugin=token&format=raw` the moment it opens, so the submitted token is always fresh.

### 🔧 Improvements
- **Type-REMOVE confirmation replaced with a checkbox** — the type-REMOVE-to-enable gate worked but felt heavy for a tool whose whole flow is "tick, click, done." The confirm modal now shows a single checkbox: "Yes, I have a site backup and I want to remove the ticked items." Tick it → the Remove now button enables. Same defence-in-depth (modal + explicit opt-in + CSRF), simpler UX.

## 🚀 Version 1.0.9 (May 27, 2026)

### 🔧 Improvements
- **Per-column dropdown filters** — the freeform search input on the scan view is replaced with a Title dropdown listing every distinct title the detector found. Combined with the existing Type and Status dropdowns, the scan view now has one filter per filterable column, matching the Cybersalt wishlist's "every column shown gets a filter" rule.

## 🚀 Version 1.0.8 (May 27, 2026)

### 📦 New Features
- **Scan view is now a single flat table** with a new **Type** column (Article, Category, Tag, Menu item, Menu type, Module, Custom field, Workflow, etc.) — replaces the per-section card layout. One long list, naturally sorted by type then title.
- **Filter bar** above the table: a search box (matches title / alias / detail), a Type dropdown, a Status dropdown (Pristine / Edited), and a Clear filters button. Filters are client-side so ticks persist when you change filters.
- **Standard Joomla-style master checkbox** in the column header — ticks or unticks every currently-visible row. Indeterminate state when the visible subset is mixed.
- **"Showing X of N rows"** indicator updates live as you filter.
- **All items ticked by default** (including those flagged as Edited). For a removal tool, this matches the common intent: untick what you want to keep, leave the rest, click Remove.

### 🔧 Improvements
- Shift-click range selection now spans the visible rows (skipping anything hidden by the active filter), matching Gmail / file-manager expectations.
- The single-button Tick all / Untick all toggle from v1.0.7 is gone — the master checkbox in the th replaces it.

## 🚀 Version 1.0.7 (May 27, 2026)

### 🔧 Improvements
- **Single smart-label toggle instead of four buttons** — v1.0.6's per-section Tick all / Untick all buttons (plus the global pair) were noisy for a tool whose whole job is "click a button to remove sample data." Replaced the four buttons with one: a single **Tick all** button at the top that re-labels itself to **Untick all** when everything is already ticked, and back again when something is unticked. Click it once, get the opposite of the current state.
- **Section card headers reverted to just title + count badge** — no more per-section buttons or "X ticked of Y" counter cluttering each card.
- Shift-click range selection inside each section still works.

## 🚀 Version 1.0.6 (May 27, 2026)

### 📦 New Features
- **Three speeds of selection** — every Cybersalt extension's bulk-action lists should give the operator one-at-a-time, range, and all-at-once selection. v1.0.6 wires up the all-at-once tier in two ways:
  - Each section's card header now carries prominent **Tick all in this section** / **Untick all** buttons plus a live "X ticked of Y" counter, so you can rip through (or spare) an entire group from one obvious place.
  - Two new global buttons at the top of the scan view — **Tick all (everywhere)** and **Untick all (everywhere)** — flip every row across every group at once.
- The per-column-header master checkbox is removed in favour of these labelled buttons (less subtle when the table runs to dozens of rows).

### 📝 Internal
- Added "Shift-click range selection on bulk-action lists" to the Cybersalt Joomla extension wishlist (`Joomla-Brain/JOOMLA-EXTENSION-WISHLIST.md`) and the joomla-development skill bullet list, so every future Cybersalt extension picks up the same pattern.

## 🚀 Version 1.0.5 (May 27, 2026)

### 🐛 Bug Fixes
- **Per-group select-all checkbox did nothing** — the admin JS initialised a `bootstrap.Modal` at script-eval time, but Joomla 5/6 loads `bootstrap` as an ES module that may not be defined yet when a plain deferred `<script>` runs. The resulting `ReferenceError` aborted the whole init block before any checkbox listeners attached, so the master checkbox visually toggled but no rows followed. Modal init is now lazy (`bootstrap.Modal.getOrCreateInstance(modalEl)` only when needed), and the whole init is wrapped in a `DOMContentLoaded` guard for good measure.

### 📦 New Features
- **Shift-click range selection** — click one checkbox, then hold Shift and click another in the same group; every row between them is ticked or unticked to match the second click. Standard table-list behaviour, now in the Remove Sample Data scan.

## 🚀 Version 1.0.4 (May 27, 2026)

### 📦 New Features
- **Inverted Keep column to Remove column** — the per-row checkbox in the scan view used to mean "tick to keep", which fought the tool's purpose. Now it means "tick to remove" — what's ticked gets deleted. Defaults are flipped to match: pristine items are ticked-for-removal by default, edited items left unticked so you have to consciously opt in to deleting them.
- **Per-group select-all** — a checkbox in each table header toggles every row in that group at once. Indeterminate state shown when some rows are ticked and others aren't.
- **Remove button at the top** — duplicated alongside the existing bottom button so you don't have to scroll back up after reviewing a long list.
- **Live tick count** — both Remove buttons now show a running count of how many items are ticked for removal.

### 🔧 Improvements
- **Form payload renamed** — the scan form now posts `remove[<group>][]` instead of `keep[<group>][]`. The controller filters by inclusion (items ticked) instead of exclusion (items not ticked), matching the inverted UX.

## 🚀 Version 1.0.3 (May 27, 2026)

### 🐛 Bug Fixes
- **6 menu items wrongly flagged as Edited** — diagnosed against the live test site via the Joomla API: the items in question were `Help`, `About your home page`, `Workflows`, `Millions`, `Love`, `Joomla`. None of them had been edited. They got flagged because the sample-data plugin titles menu items after the **article or category they link to** (e.g. the `Workflows` menu item targets the `Workflows` article), and my detector only compared menu item titles against the `MENUS_ITEM_*_TITLE` set. Expanded the menu-item valid-title set to also include article and category titles. The "Edited" count on a fresh sample-data install is now 0.
- **Synthetic menu-type root rows over-counted** — Joomla's `#__menu` table holds a synthetic root row per menu type with `title = menutype`. Three of those (one per sample menu type) were being counted as "detected items" but aren't user-facing. Skipped in the menu-item detector. Expect the detected count to drop by 3 (so 82 → 79 on Tim's test site).

## 🚀 Version 1.0.2 (May 27, 2026)

### 🐛 Bug Fixes
- **Items list dark-mode unfriendly** — edited rows were rendered with Bootstrap's `table-warning` class, which produces a pale-yellow row background that fights Atum's dark theme. Replaced with a 4 px warning-coloured left border on the first cell only, so the status accent works in both light and dark mode.
- **Raw `JOPTION_SELECT_FROM...` labels in filter dropdowns** — the filter form used the core `JOPTION_SELECT_FROM_LIST` constant for empty-option labels, which `Text::alt()` rewrites with a field-name suffix that doesn't resolve. Replaced with component-scoped `COM_REMOVESAMPLEDATA_FILTER_ANY_SOURCE` / `_TYPE` / `_STATUS` strings ("- Any source -" etc.).
- **Dashboard / stats-card filter deep links not applying** — the searchtools UI submits filters as a nested array (`filter[source]=blog`), but the dashboard URLs used the flat form (`filter_source=blog`) and `populateState()` only read the flat form. Both forms are now read and stored consistently.

### 🔧 Improvements
- **Edited threshold widened to 120 s** — the `modified - created` delta for the "looks edited" signal has been bumped from 60 s to 120 s. Joomla's menu-table tree-rebuild can re-save earlier siblings when later ones are inserted, and the gap can exceed 60 s during the sample-data install. 120 s eliminates that whole class of false positives.
- **Click an item to open it in Joomla** — every row title in the items list and on the scan view is now a link to the underlying record's core Joomla edit screen (article → `com_content`, menu item → `com_menus`, module → `com_modules`, custom field → `com_fields`, workflow → `com_workflow`, etc.).

## 🚀 Version 1.0.1 (May 27, 2026)

### 📦 New Features
- **Items list view** — a dedicated, filterable, sortable list of every detected item across both Blog and Multilingual sample data. Filters: full-text search, Source (Blog / Multilingual), Type (Article / Category / Tag / Menu item / Menu type / Module / Custom field / Field group / Workflow / Workflow stage / Workflow transition), Status (Pristine / Edited). Sort by any column.
- **Clickable dashboard badges** — the "X detected" and "Y look edited" badges on the dashboard now link directly into the items list, pre-filtered to the corresponding source and status, so you can drill in with one click.

### 🐛 Bug Fixes
- **Edited-detection false positives** — articles whose introtext was processed by the sample-data plugin (image-placeholder substitution) were incorrectly flagged as "Edited." The introtext-equality check has been removed; edited status now uses only title equality and a 60-second-or-more delta between `created` and `modified` timestamps. On a fresh sample-data install the edited count is now 0.

### 🔧 Improvements
- **Tighter multilingual detection** — per-language categories and articles must now match the `-{lang}` / `_{lang}` alias suffix exactly, and per-language menu items must live inside a `mainmenu-{lang}` menutype that was itself detected. This eliminates false positives on sites that have legitimate multilingual content alongside leftover sample data.
- **Confirmed scope** — the `PLG_SAMPLEDATA_BLOG_SAMPLEDATA_NEWSFEEDS_TITLE` string is text *inside* the syndication module's content, not a separate newsfeed item; the existing module detector already covers it.

## 🚀 Version 1.0.0 (May 27, 2026)

### 📦 New Features
- **Blog sample-data removal** — detects and removes the 60+ items the core `plg_sampledata_blog` plugin installs: articles, categories, tags, menu types + items, modules, custom field + group, and the editorial workflow with all 9 stages and 12 transitions.
- **Multilingual sample-data removal** — removes per-language menus and menu items, the Language Switcher module, per-language menu modules, per-language sample categories and articles, and optionally disables `plg_system_languagefilter`.
- **Edited-item safeguard** — items whose title or body has changed since the sample data was installed are flagged with an "Edited" badge and left unchecked by default so you can review before deciding.
- **Per-item opt-out** — every detected item has a Keep checkbox; you can deselect any item you don't want removed.
- **Side-effect surfacing** — the multilingual remover shows exactly which non-deletion actions it will take (disabling `plg_system_languagefilter`, republishing the main menu module) so nothing happens behind your back.
- **Type-to-confirm + lock-out modal** — removal requires typing REMOVE, then an overlay locks the UI for the duration of the run.
- **Activity log + log viewer** — DB-backed log of every action with the standard Cybersalt log viewer (refresh, CSV download, clear-all-with-modal, hotlinked stats cards, text filter).
- **Locale-aware detection** — aliases are reconstructed from translated titles using the same `ApplicationHelper::stringURLSafe()` algorithm the plugins use, so detection works on non-English sites.
