# factfusionai

This repo is the deploy source for the factfusionai WordPress site on Hostinger.

- The repo root is the **FactFusion Deploy** plugin. Hostinger Git deploys it into `public_html/wp-content/plugins/factfusion-deploy`.
- All site changes go in `theme/factfusion-child/`. On the next page load after a deploy, the plugin copies that folder to `wp-content/themes/factfusion-child`.
- If the plugin is ever deleted, the theme (and every change in it) stays on the site.

## Hostinger settings (hPanel → Websites → Advanced → Git)

| Field | Value |
|---|---|
| Repository | `https://github.com/6977980-hash/factfusionai.git` |
| Branch | `main` |
| Directory | `wp-content/plugins/factfusion-deploy` (it is relative to `public_html`, and must be empty/new) |

Then turn on **Auto Deployment**, copy the webhook URL Hostinger shows, and add it in GitHub → repo Settings → Webhooks (content type `application/json`, push event).

## First-time steps in WordPress

1. Plugins → activate **FactFusion Deploy** (copies the theme, using the current theme as parent).
2. Appearance → Themes → activate **FactFusion Child**.
3. Tools → FactFusion Deploy shows sync status and has a "Sync theme now" button.

## Workflow

Edit files in `theme/factfusion-child/`, commit, merge to `main`. Hostinger pulls the commit, and the plugin copies the theme on the next visit.
