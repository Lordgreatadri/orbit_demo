# Phase 1 Acceptance Criteria

## Public experience
- [ ] Home page explains ORBIT and its actual MVP capabilities.
- [ ] Contact, user guide/how-it-works, Terms, and Privacy pages exist and are navigable.
- [ ] Layout works on mobile and desktop, with keyboard-accessible navigation.
- [ ] Light theme is default; alternate deep-teal/midnight-plum theme can be toggled.
- [ ] No fabricated customer endorsements, live metrics, or unsupported integration claims.

## Organisation registration and auth
- [ ] Organisation contact can register an organisation and become its initial administrator.
- [ ] Organisation/account bootstrap is transactional.
- [ ] Login, logout, password reset, and configured verification flows work.
- [ ] Registration, login, reset, and invitation endpoints are throttled.
- [ ] Students/employees/staff cannot self-register.
- [ ] Additional members can only enter through authorised invitation/provisioning paths.
- [ ] Unauthenticated users cannot access protected pages.

## Tenant and database safety
- [ ] Numeric `id` primary keys are used throughout.
- [ ] Publicly addressable entities use UUID route binding.
- [ ] Tenant-owned tables have tenant relationships and appropriate indexes/constraints.
- [ ] Cross-tenant read/write/export tests pass.
- [ ] No client-supplied tenant ID can override authorised context.

## Engineering quality
- [ ] Tests pass for implemented functionality.
- [ ] Migrations run cleanly against the documented local database setup.
- [ ] No secrets or production credentials are committed.
- [ ] Main lists are paginated and key relations are eager-loaded.
- [ ] Documentation matches the implemented behaviour.
- [ ] Cursor reports commands run and any unresolved failures honestly.
