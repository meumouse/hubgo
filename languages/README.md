# HubGo translations

AI-powered translation workflow for the HubGo plugin, mirroring the Joinotify
pipeline. It extracts translatable strings from PHP (`admin/src`, `hubgo.php`,
`templates`) and JS/Vue (`app/src`, `assets`), then translates and compiles the
runtime artifacts WordPress loads (`.mo`, `.l10n.php`, and per-handle `.json` for
script translations).

## Setup

```bash
cd languages
npm install
cp .env.example .env   # then fill in your API key(s)
```

Supported engines (set via `--engine`, default `google`):

- `openai` — AI translation via OpenAI Chat Completions (`OPENAI_API_KEY`).
- `google` — Google Cloud Translation (`GOOGLE_TRANSLATE_API_KEY`).

## Commands

```bash
npm run pot                 # regenerate hubgo.pot from source
npm run translate:ai        # AI-translate every locale (incremental)
npm run translate:ai:lang pt_BR   # single locale
npm run translate:ai:retry  # re-translate identical passthroughs
npm run translate           # Google engine
npm run compile:mo          # (re)compile .po -> .mo
npm run compile:php         # (re)compile .po -> .l10n.php
```

From the plugin root you can also run the whole pipeline through the build
orchestrator:

```bash
npm run build:translate     # pot -> AI translate -> compile
```

## Locales

`en_US`, `es_ES`, `pt_BR`, `pt_PT`, `de_DE`, `fr_FR`, `it_IT` (edit `LANGUAGES`
in `translate-cli.js`).

## Notes

- Incremental by default: only empty entries are (re)translated. Use
  `translate:ai:retry` to re-send entries whose translation equals the source.
- The AI prompt preserves sprintf placeholders, HTML, URLs and HubGo/WooCommerce
  template tokens (`{{ hubgo_tracking_code }}`, `{{ wc_order_total }}`), and keeps
  brand names untranslated.
- `.env` and `node_modules` are git-ignored; the compiled artifacts are committed
  and shipped in the plugin.
