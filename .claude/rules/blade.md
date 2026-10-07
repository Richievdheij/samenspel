---
paths:
  - resources/views/**
description: Conventions for Blade templates, components and accessibility.
---

# Blade

Prettier does not touch these files — it has no PHP parser and would rewrite
directives and `{{ }}` expressions as if they were HTML. `make build` runs
`view:cache`, which syntax-checks every template.

## Structure

- `components/layouts/app.blade.php` — the page shell. A page writes
  `<x-layouts.app>` and its body arrives in `{{ $slot }}`.
- `components/*.blade.php` — anonymous components, used as `<x-button>`,
  `<x-field>`, `<x-alert>`. Declare inputs with `@props`, defaults included.
- `partials/*.blade.php` — fragments included with `@include`, not reusable
  components. Header, footer, flash.

## Rules

- **No `env()` in a template.** `make checks` refuses it. Use `config()`.
- **`{{ }}`, never `{!! !!}`**, unless you can name the sanitiser that ran first.
- **Every route reference is `route('name')`**, never a literal path.
- **User-facing text goes through `__()`**, with the Dutch string in
  `lang/nl.json`. The application runs `nl` with an `en` fallback.
- **`@csrf` in every POST form.** Its absence is a 419 that reads like a routing
  bug.

## Accessibility, which is not optional here

- A link navigates, a button acts. `<x-button>` renders `<a>` when given `href`
  and `<button>` otherwise, and that distinction is what a keyboard and a screen
  reader depend on — even when the two look identical.
- Every input has a real `<label for>`. `<x-field>` wires that up along with the
  old value, the error message, `aria-invalid` and `aria-describedby`.
- Colour is never the only signal. A state carries a role, text or an icon too.
- Never remove the focus ring without replacing it with something as visible.
