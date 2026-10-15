# Beaver Builder to Elementor export/import plugin

This plugin is built for practical page migration from Beaver Builder to Elementor.

## What it does

The plugin adds a small admin interface to:

- list Beaver Builder-backed pages
- export a selected page to JSON
- import that JSON into an existing page on another WordPress site
- write Elementor metadata so the page can be opened and edited in Elementor
- include exported HTML so the content is not lost even when the Builder structure cannot be perfectly reconstructed

## Intended workflow

1. On the source site, open Tools → Beaver Builder to Elementor.
2. Select a Beaver Builder page and export it to JSON.
3. Move the JSON file to the destination site.
4. On the destination site, install Elementor and this plugin.
5. Open Tools → Beaver Builder to Elementor.
6. Upload the JSON file and choose the existing destination page.
7. Click Update existing page.
8. Open the destination page in Elementor and finish the visual design there.

## How it handles the content

This is not a perfect pixel-for-pixel conversion engine.

Instead, the plugin focuses on preserving the page content and page structure in a way Elementor can open and edit. In practice this means:

- page content is imported into the selected existing page
- Elementor metadata is written so the editor can render the imported layout
- exported HTML is preserved as a fallback source
- complex Beaver Builder modules and styling still need manual cleanup in Elementor

## Best use cases

- moving content from Beaver Builder pages to a new Elementor site
- preserving page text, sections, and core layout blocks
- preparing pages for visual refinement in Elementor

## Limitations

- exact Beaver Builder styling is not guaranteed to transfer 1:1
- some custom modules or dynamic sections may need manual rework
- this should be treated as a migration helper, not a fully automatic visual clone

## Restore point

This repository uses a git tag restore point to mark the working project state before final push.
