# Remove Sample Data — Joomla 5/6 component

A Joomla 5/6 administrator component that detects and removes the items installed by Joomla's core sample-data plugins (Blog and Multilingual), with preview, per-item opt-out, filtering, and a full activity log.

When you install Joomla and click "Install sample data → Blog" (or Multilingual), Joomla drops 60+ items into your database — articles, categories, tags, menus, modules, custom fields, an editorial workflow with stages and transitions, and (for multilingual) per-language menus and the Language Filter plugin. There's no built-in way to remove this once you've started building a real site on top of it. Removing items individually tends to miss the workflow stages, the field-values rows, the per-language association entries, etc.

This extension knows what each sample-data plugin creates and removes it cleanly, with a preview before deletion and an "Edited" safeguard so anything you've touched since install is flagged for review.

## Features

- **Blog sample-data removal** — every item the core `plg_sampledata_blog` plugin creates: ~11 articles, 4 categories, 4 tags, 3 menu types, ~17 menu items, ~12 modules, 1 custom field + group, 1 editorial workflow with 9 stages and 12 transitions.
- **Multilingual sample-data removal** — per-language menus, language switcher module, per-language language-menu modules, per-language sample categories and articles, and optionally disables `plg_system_languagefilter`.
- **Locale-aware detection** — aliases are reconstructed from translated titles using the same `ApplicationHelper::stringURLSafe()` algorithm the sample-data plugin used, so detection works on non-English sites.
- **Blog-on-multilingual detection** — when Joomla's blog plugin is invoked on a site that has content languages installed, it appends `-{lang}` to every alias and ` ({LANG})` to every title. The Blog detector reads `#__languages` and matches the variants too.
- **Items list view** with per-column dropdown filters (Source / Type / Title / Status), sortable columns, pagination, and hot-linked stats cards.
- **Scan view** as a single flat table with one filter dropdown per column, master checkbox in the column header, shift-click range selection. Everything ticked by default — untick what you want to keep.
- **Confirmation modal** with a backup-acknowledgement checkbox + lock-out overlay during the run.
- **Activity log + viewer** — every action and side-effect (e.g. disabling a plugin) is recorded; the viewer has refresh / CSV download / clear-all-with-modal / stats cards / text filter / color-coded levels.
- **Clickable row titles** open the underlying record in the matching core Joomla editor.

## Detection strategy

The sample-data plugins don't hardcode their aliases in English — they generate them at runtime by feeding translated titles through `ApplicationHelper::stringURLSafe()`. On a French site the alias for `ARTICLE_0_TITLE` is "ma-cote-tropicale" not "tropical-shoreline".

This extension reuses the exact same algorithm: it loads `plg_sampledata_blog.ini` (or `plg_sampledata_multilang.ini`) for each installed admin language, runs the same `stringURLSafe()` transform, and looks up matching items by alias in their correct context (category, menu type, etc.). For blog-installed-on-multilingual setups, it additionally enumerates `#__languages` and computes the `-{lang}` / `_{lang}` alias variants the blog plugin appends.

## Safety

- Preview every item before any deletion.
- Per-item opt-out via the column checkboxes.
- "Edited" items flagged with a warning badge so you can spot anything you've changed since install.
- "Yes, I have a site backup and want to remove these items" checkbox in the confirm modal.
- Lock-out overlay during the run prevents double-submit and tab navigation.
- Every action (and side-effect like disabling a plugin) is logged.

**Take a full site backup before running.** Removal cannot be undone from inside this extension.

## Requirements

- Joomla 5.0+ or 6.0+
- PHP 8.1+

## Installation

Download `com_removesampledata_v1.0.0.zip` from the [releases page](https://github.com/cybersalt/cs-remove-sample-data/releases) and install via Joomla's Extensions → Install.

After installation, find it under **Components → Remove Sample Data**.

The component is set up for in-place updates through Joomla's Extension Manager — future releases will appear in `Extensions → Manage → Update`.

## License

GNU General Public License version 2 or later.

## Author

Cybersalt Consulting Ltd. — https://cybersalt.com
