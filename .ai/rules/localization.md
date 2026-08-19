# Bangla and English move together

The UI is bilingual: Bangla is the default locale, English the fallback. `lang/en` and
`lang/bn` each hold one file per domain (`masters`, `billing`, `accounting`, `expenses`,
`reports`, `nav`, `admin`, `auth`, `setup`) with flat, single-level key arrays.

- **Never add a key to one locale only.** The two trees are currently at exact parity, and
  that is worth keeping. Every new string lands in both files in the same change.
- Journal entry descriptions are translated too — services call `__('billing.…')` when
  building the description passed to `JournalService::post()`.
- Record-level bilingualism is separate from lang files: models carry a `name_bn` column and
  expose `displayName()`, which branches on `app()->getLocale()`. Use `displayName()` in
  views rather than branching inline.
- Locale is switched by `POST /locale` and held in the session by `App\Http\Middleware\SetLocale`.
