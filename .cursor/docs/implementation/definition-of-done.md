# Definition of Done

A task is done only when:
1. It satisfies the written requirement and its acceptance criteria.
2. It follows Laravel conventions and preserves existing project structure unless a documented decision justifies a change.
3. Authentication, authorization, tenant isolation, validation, and failure states are covered.
4. Database changes include appropriate constraints, indexes, and rollback-safe migrations.
5. Important success/failure/edge paths have automated tests.
6. Queries are paginated, tenant-scoped, and checked for obvious N+1 or unbounded work.
7. UI is responsive, accessible, and theme-consistent.
8. Secrets and unnecessary sensitive data are not logged or committed.
9. Relevant documentation and ADRs are updated.
10. Relevant tests/builds are run and their outcomes reported truthfully.
11. No Git commit, pull, push, or branch-changing operation has been run by Cursor.
