# Cybersalt Remove Sample Data

A Joomla 5 and 6 administrator component that finds the items Joomla's core **Blog** and **Multilingual** sample-data plugins installed and removes them, with a preview, per-item opt-out and an activity log.

- **Documentation:** https://docs.cybersalt.com/extensions/remove-sample-data
- **Product page and download:** https://www.cybersalt.com/extensions/cs-remove-sample-data
- **Changelog:** [CHANGELOG.md](CHANGELOG.md) · https://docs.cybersalt.com/extensions/remove-sample-data/changelog
- **Support:** https://www.cybersalt.com/services/support-request-form

> **Back up your site (files and database) before you remove anything.** Removal deletes database rows directly and cannot be undone from inside the extension.

## Why

Clicking *Install* next to Blog Sample Data adds around 85 items to a site: articles, categories, tags, three menus and their menu items, modules, a custom field and field group, and an editorial workflow with its stages and transitions. Joomla has no way to take them out again. Deleting them by hand is slow, misses the workflow and custom-field rows, and leaves aliases behind that block the sample data from being installed a second time.

## What it removes

**Blog sample data** (`plg_sampledata_blog`): the sample articles and their custom-field values, featured flags, workflow associations and tag links; the four sample categories; the four sample tags; the three sample menus and their menu items; the twelve sample modules (eleven on the site, one administrator "Site Information" module); the "The Author" field group and "About the Author" field; the "Blog Workflow" with its stages and transitions. ACL asset rows of removed items go too, and the category, menu and tag trees are rebuilt.

**Multilingual sample data** (`plg_sampledata_multilang`): the per-language main menus (`mainmenu-xx-xx`) and their Home and Categories menu items, the Language Switcher module, the per-language "Main menu xx-XX" modules, and the per-language sample category and article with their associations. Optionally it disables the Language Filter plugin, and it republishes the Main Menu module the plugin unpublished (setting in Options).

## How it decides what is sample data

The sample-data plugins build aliases at install time from translated titles with `ApplicationHelper::stringURLSafe()`. The detector loads `plg_sampledata_blog.ini` for every installed administrator language and repeats that, so it finds the sample data on non-English sites too, including the `-xx-xx` alias and ` (xx-XX)` title suffixes the blog plugin adds on multilingual sites.

A match on title or alias alone is not enough, because titles such as "About", "Help", "Search" or "Image" are common on real sites:

- an article must sit in one of the sample categories;
- a module must match both the sample title and the sample module type;
- a menu must have the sample menu type (`0…`, `1…`, `2…`), not only the sample title;
- a multilingual article or category must carry the plugin's ` (xx-xx)` title suffix.

Anything that fails a check, or was changed after installation, is marked **Edited** and starts **unticked**. The badge's tooltip says why. At removal time a category that still holds articles or sub-categories, or a menu that still holds menu items, is kept and reported as skipped.

## Screens

- **Dashboard:** one card each for Blog and Multilingual with the number of items found and how many look edited.
- **Scan & Remove:** one table of every item found, with filters by type, title and status. Tick what should go and confirm in a dialog that requires the "I have a backup" box.
- **Browse items:** a sortable, filterable list of everything detected.
- **Log:** every deletion and side effect, with CSV download.

## Requirements

- Joomla 5.0 or later, or Joomla 6
- PHP 8.1 or later
- MySQL or MariaDB

## Installing

Download the latest release from the product page above (or from [GitHub releases](https://github.com/cybersalt/cs-remove-sample-data/releases)) and install it in **System → Install → Extensions**. Open it from **Components → Cybersalt Remove Sample Data**. Updates arrive through Joomla's normal update manager.

## Building

`bash build.sh --release` builds `com_removesampledata_v<version>.zip` with 7-Zip and refuses to build when the changelog files are missing or have no entry for the manifest version.

## Licence

GNU General Public License version 2 or later. See [LICENSE](LICENSE) and [NOTICE](NOTICE).

Cybersalt Consulting Ltd — https://www.cybersalt.com
