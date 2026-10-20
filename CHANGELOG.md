# Changelog

## 🚀 Version 1.1.0 (October 20, 2026)

Safety release. On a test site with the Blog sample data plus a few items of the site's own, 1.0.1 with its default ticks removed the site's own "About" article, a "Search" module, a menu item, a reused tag, and the category under one of the site's articles. 1.1.0 keeps all of them and removes only the sample data.

### 🔒 Safety
- **Your own articles in the sample Blog category disappeared from the Articles list.** The sample Blog category uses the sample "Blog Workflow", so articles you wrote in it sit in one of that workflow's stages. Removing the workflow left them pointing at a stage that no longer existed, and Joomla's Articles list hides such articles. Kept articles are now moved to the default stage of your default workflow first, and kept categories that used the sample workflow are set back to inherit their workflow.
- **Your own articles with sample-looking titles were removed.** Blog detection matched articles by title or alias alone, so a site's own "About" page (or "Help", "Joomla", "Typography") was listed as sample data and ticked for removal. An article now only counts as untouched sample data when it sits in one of the four sample categories; anywhere else it is marked Edited.
- **Your own modules with sample-looking titles were removed.** Modules were matched by title only, so a site's own "Search", "Image" or "Site Information" module was removed. A module now has to match both the sample title and the sample module type (and site/administrator side). A sample menu module that now shows a different menu is marked Edited.
- **Menus with a sample title but a different menu type were treated as sample data.** The blog plugin's menus have menu types that start with 0, 1 or 2 (for example `0main-menu-blog`). A site's own "Bottom Menu" with any other menu type is now marked Edited, and its menu items are no longer listed.
- **Categories could be deleted out from under your own articles.** Removing a sample category that also held your own articles (or sub-categories) left those articles pointing at a category that no longer existed. Such categories are now marked Edited, and at removal time a category that still holds content, or a menu that still holds menu items, is kept and reported as skipped.
- **Sample tags you had reused were removed from your content.** A sample tag ("Joomla 6", "Love", ...) that you had also put on your own content is now marked Edited.
- **Edited items now start unticked.** Anything marked Edited is no longer ticked for removal by default; you tick it yourself if you want it gone. The Edited badge's tooltip now explains why each item was flagged.
- **Custom-field values of other content could be deleted.** Field values were deleted by article id without checking the field's context, which could also remove values belonging to a user, contact or category that happened to have the same id. Only article-field values are deleted now. Field, field group and workflow detection is also limited to the article context.
- **Multilingual removal deleted language associations of other items.** Associations were deleted by id without their context, so removing sample articles or categories could also remove associations of menu items or articles that happened to share the id. Each delete is now scoped to its own context.

### 🐛 Fixes
- **The Log retention option did nothing.** Log entries older than the configured number of days are now deleted at the start of each removal run.
- **The multilingual side effects never ran.** The Language Filter plugin was never disabled and the unpublished Main Menu module was never republished, because the removal step emptied the settings it needed. After a multilingual removal the site was left with the Language Filter on and no visible main menu. Both side effects now work, and unticking the Language Filter switch now keeps the plugin enabled (before, the switch was ignored).
- **Activity-log table re-created if missing.** A 1.1.0 schema update creates the log table when it is missing, instead of the log and result pages failing with a database error.
- **Multilingual per-language menu modules were left behind.** The "Main menu <language&gt;" modules the multilingual plugin creates are menu modules, but detection only looked at language-switcher modules, so they stayed on the site pointing at deleted menus. They are now detected and removed. Multilingual articles and categories must now also carry the plugin's " (xx-xx)" title suffix.
- **Removal left orphaned rows behind.** Deleting articles, categories and modules left their ACL asset rows, workflow associations, tag-map and UCM rows, and module-to-page assignments in the database. These are now removed with the item, and the category, menu and tag trees are rebuilt afterwards.

### 🔧 Changes
- **Stat cards: the Total number was unreadable.** Atum's heading colour overrode the card's white text, so the number on the navy Total card (log and Browse items) was dark on dark. The numbers now follow the card's text colour.
- **Renamed to Cybersalt Remove Sample Data.** The component's display name follows the Cybersalt naming convention.
- **Joomla changelog feed.** The manifest now points at a Joomla-format `changelog.xml`, so the changelog button in the Extensions manager works.
- **Licence files.** The repository and package now carry the verbatim GPL-2.0 text in `LICENSE`; the copyright notice moved to `NOTICE`.
- **JED Checker clean-up.** Replaced a PHP `error_log()` fallback with Joomla's logger, fixed an unknown language key and a language string with a leading space.

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
