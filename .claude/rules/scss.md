---
paths:
  - resources/scss/**
  - resources/js/**
description: Conventions for the SCSS layers, design tokens, BEM and frontend JavaScript.
---

# Styling

Dart Sass through `sass-embedded`. No Tailwind, and no utility framework is to be
added — the tokens below are the shared vocabulary that would otherwise be.

## Layers

`main.scss` is the only entry point Vite compiles, and its `@use` order is the
cascade order:

```
abstracts  tokens and mixins — must exist before anything reads them
base       element defaults
layout     the page frame
components blocks
utilities   the few single-purpose classes
```

Each directory has an `_index.scss` that `@forward`s its parts. A component reads
`@use '../abstracts' as *;`. Sass emits the shared CSS once however often a module
is used, so there is no cost to using it everywhere.

## Tokens

Design tokens are **CSS custom properties on `:root`**, in
`abstracts/_tokens.scss`. Not Sass variables: custom properties stay readable in
devtools, can be overridden per component or per media query, and JavaScript can
read them.

**Add a token before using a raw value.** A hex code, a `px` gap or a font stack
written inline is exactly what this file exists to prevent. The dark scheme
changes only the colour tokens, so a component that reads token names needs no
dark rule of its own.

The one place a Sass variable is correct is `$breakpoints`: a media query cannot
read a custom property. Use `@include from('m')`, never a literal width.

Use `map.get` from `sass:map`, never the global `map-get` — the globals are
deprecated and become an error in Dart Sass 3.

## BEM

`.block`, `.block__element`, `.block--modifier`. A modifier never appears without
its block; an element never styles anything outside its block. Block names
describe the role, never the appearance — renaming `.site-header` because it
turned blue is the failure this avoids.

## JavaScript

`resources/js/app.js` is progressive enhancement, and is meant to stay small. No
framework. Query by `data-` attribute rather than by CSS class: a class is for
styling and will be renamed. Leave the markup usable without JavaScript.

`VITE_PORT` is declared in both env templates and Vite runs with `strictPort`, so
a busy port fails loudly instead of moving to 5174 and serving a page against a
manifest written by another process.
