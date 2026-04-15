# Changelog

## 0.0.6Beta - 2026-04-15

- Added last exported tracking for saved prompt definitions.
- Added `Last exported` column to the prompt definitions list.
- Added non-editable last exported status near the export button on the add/edit screen.
- Added upgrade handling for the new `last_exported` database column.
- Updated README documentation for last exported tracking.

## 0.0.5Beta - 2026-04-14

- Added public repository README guidance.
- Added 0.0.5Beta release zip URL to the README installation instructions.
- Added README example prompt instructions with a `STOP` cue for missing or uncertain required content.
- Added next-step implementation notes for receiving and processing generated agent data.
- Added MIT license file.
- Marked the module as beta and advised testing before production use.

## 0.0.3 - 2026-04-13

- Added ProcessWire admin Process module for managing prompt definitions.
- Added dedicated database table storage for prompt definitions.
- Added list, add, edit, delete, bulk delete, preview, and export workflows.
- Added markdown prompt export and main JSON field-name export.
- Added field guidance for labels, field types, required/optional status, and constraints.
- Added Page Reference sidecar option JSON exports.
- Added Page Reference exclusions for Admin, Trash, configured 404, RockPageBuilder datapages, Repeater, and RepeaterMatrix pages.
- Added Select Options sidecar JSON exports for select, radios, and multi-checkbox fields.
- Added checkbox and toggle prompt guidance, with toggle choices kept inline in markdown.
- Added URL, Datetime, and Decimal prompt guidance, including host-aware URL wording.
- Added File and Image prompt guidance as attributed source URL references only.
- Added minimal vanilla JS for prompt key generation, field check-all, and bulk delete interactions.
