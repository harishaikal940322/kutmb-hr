# DOC-012 | Vue 3 Onboarding & Frontend Delivery Plan (MOD-001)

> v0.1 DRAFT · 29 October 2026 · **For Laravel-experienced developer new to Vue**  
> This is a proposed technical learning/implementation sequence, not a course prerequisite or approval to code.  
> **Physical file structure intentionally deferred.**

## Core idea

Think of Inertia as an adapter, not a second backend. Laravel retains route handlers, validation, permissions, sessions and MySQL persistence; Vue renders the page and reacts to state. **Never design a second authorization logic inside Vue**.

Recommended starting point: Official **Laravel 13 Vue starter kit** (Vue 3 Composition API + `<script setup>`, TypeScript, Inertia 3, Vite). Disable unapproved auth options and audit starter defaults first. Avoid inventing an independent REST API.

## Guided sequence: learn by building synthetic MOD-001 screens

| Step | Vue/Inertia learning outcome | Small exercise | Review check |
|---|---|---|---|
| VUE-01 | Understand SFC (`template`, `script setup`), declarative rendering, scoped state | Display synthetic users/status badges using template bindings and `v-for` | Props typed and no unsafe HTML |
| VUE-02 | Learn `ref`, `computed`, event handlers, conditional rendering, props/emits | Build local search and status filters | No mutated parent props; predictable state |
| VUE-03 | Learn components, form inputs, validation error display | Create a non-saving user-form prototype | Required fields clear; keyboard/focus review |
| VUE-04 | Learn Inertia link/navigation, page props, form submission and server errors | Submit a synthetic user form to a stubbed/review-only page flow | Backend remains authority; no raw JSON API duplication |
| VUE-05 | Understand session-based login, server permissions and UI hints | Hide disabled controls but confirm backend denial via tests | Client UI never treated as security |
| VUE-06 | Learn TypeScript props/types for users and validation errors | Add typed form contract and pagination result projection | Minimal props; no leaked secrets |
| VUE-07 | Component and browser testing, accessibility | Form error, disabled account and role denied scenario tests | Tests tied to RTM IDs |
| VUE-08 | Review performance & maintainability | Pagination, lazy-loading candidate views, loading/error states | No excessive global store or plugins |

The sequence is iterative: fundamentals first, then actual authorized MOD-001 behavior once decisions/gates are approved. Do not use real HR data as sample fixtures.

## What not to introduce immediately

- Separate Vue CLI project or separate deployment domain.
- Vue Router merely to replicate Laravel/Inertia routing.
- Pinia unless truly shared cross-page client state becomes necessary.
- Axios-only/REST-first CRUD when Inertia form submissions cover the requirements.
- JWT in browser/localStorage in place of Laravel's cookie session.
- SSR, microfrontend, animation-heavy UI or a complex component package by default.
- A second validation or permission business policy in TypeScript.

## Technical quality bar

1. Composition API / `<script setup>` conventions, typed public component interfaces.
2. Client forms show field errors but Laravel validates every operation.
3. Always think through pending, success, error, unauthorized and revoked-session UI states.
4. Accessible labels, visible focus, keyboard use, responsive at 360px and 1280px; reduced-motion preference.
5. Consistent table pagination/filtering and explicit empty state; no accidental bulk user data fetch.
6. TypeScript check + Vue component tests + Laravel HTTP feature/security tests + end-to-end browser tests.
7. Read official Laravel/Vue/Inertia documentation instead of outdated Vue 2 or pre-Inertia-3 patterns.

## Useful conceptual example (not implementation)

```vue
<script setup lang="ts">
type UserRow = { id: string; displayName: string; status: 'active' | 'inactive' }
defineProps<{ users: UserRow[] }>()
</script>

<template>
  <ul>
    <li v-for="user in users" :key="user.id">
      {{ user.displayName }} ({{ user.status }})
    </li>
  </ul>
</template>
```

This only renders data. **No role authorization is performed here**. Backend page props must explicitly include only the safe account projection.

## Official references

- https://laravel.com/docs/13.x/starter-kits
- https://laravel.com/docs/13.x/frontend
- https://vuejs.org/guide/introduction
- https://vuejs.org/api/sfc-script-setup
- https://vuejs.org/guide/best-practices/security
- https://inertiajs.com/
