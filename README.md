# WordPress Beaver Builder to Elementor content migration

This repository contains a lightweight WordPress plugin that helps move content built in Beaver Builder into a site that uses Elementor.

## What it does

The plugin adds a small admin tool that lets you:

- scan pages for Beaver Builder content
- export a selected page to a JSON file
- import that JSON into an existing WordPress page on another site
- write Elementor metadata so the imported content can open in Elementor editor
- keep the exported HTML as a fallback payload so some content still renders even when the visual Builder structure cannot be perfectly rebuilt

## Why this exists

Beaver Builder and Elementor use different internal structures. A true one-to-one conversion is not always possible. This plugin is designed for practical content migration: moving the usable page content into an existing page, then finishing the design in Elementor manually if needed.

## Repository contents

- [bb-to-elementor-exporter-importer](bb-to-elementor-exporter-importer): the WordPress plugin itself
- [bb-to-elementor-exporter-importer.zip](bb-to-elementor-exporter-importer.zip): packaged plugin archive for installation

## Installation

1. Upload the plugin ZIP to a WordPress site.
2. Activate the plugin.
3. Go to Tools → Beaver Builder to Elementor.

## Export flow

1. Open the source site.
2. Go to Tools → Beaver Builder to Elementor.
3. Choose a page and click Export JSON.
4. Save the export file and move it to the destination site.

## Import flow

1. Open the destination site.
2. Install and activate Elementor.
3. Install this plugin.
4. Go to Tools → Beaver Builder to Elementor.
5. Upload the JSON file.
6. Select the target existing page if needed.
7. Click Update existing page.
8. Open the page in Elementor to finish the layout visually.

## Important limitation

This is not a pixel-perfect Beaver Builder → Elementor conversion engine. It is a practical migration helper.

It works best when the goal is to move the content into an existing page and then use Elementor to refine styling, spacing, and layout. Pages with advanced custom Beaver Builder modules may still require manual cleanup after import.

## Restore point

A restore point tag is created in git for this working state before the final commit is pushed.

## Notes for the project

- The exporter prefers actual rendered page HTML when Builder metadata is unavailable.
- The importer updates an existing page in place, rather than creating a new draft page.
- This is most useful for content migration and layout handoff rather than exact visual duplication.
