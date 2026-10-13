# Reference copy of the parent theme

`factfusion-ai/` is a read-only copy of the live parent theme (FactFusion AI 0.9.0), added so fixes can be written against its real code.

It is **not deployed** to `wp-content/themes`. Do not edit it. To change something, override it in `theme/factfusion-child/`:

- Template or part: copy e.g. `templates/single-fact.html` to `theme/factfusion-child/templates/single-fact.html` and edit the copy.
- Colors, fonts, spacing: add only the changed keys to `theme/factfusion-child/theme.json`.
- CSS: `theme/factfusion-child/style.css`.
- PHP hooks: `theme/factfusion-child/inc/*.php`.
