# ORBIT — agent entry point

This is the ORBIT multi-tenant workforce attendance platform (Laravel 13, PHP 8.4, Blade + Tailwind 4 + Alpine).

Before changing anything, read in this order:

1. `.cursor/rules/00-project-governance.mdc` (always applies) and the other rules in `.cursor/rules/`.
2. `.cursor/docs/architecture/overview.md` and the ADRs in `.cursor/docs/architecture/adr/`.
3. `.cursor/docs/requirements/` (product scope first) and `.cursor/docs/implementation/phase-1-plan.md`.
4. `.cursor/docs/implementation/phase-1-progress.md` for what is already built and verified.

Local toolchain notes:

- Use Herd PHP 8.4: `C:\Users\rexme\.config\herd\bin\php84\php.exe` (the `php` on PATH is 8.2 and cannot run this project).
- Tests: `php artisan test`. Formatting: `vendor/bin/pint`. Assets: `npm run build`.
- Never run `git commit`, `git pull`, `git push`, branch switching, or destructive Git commands.
