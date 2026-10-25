# ADR-0003: Numeric internal IDs and UUID public identifiers

- Status: Accepted
- Date: 2026-10-24

## Decision
Every database table uses a numeric `id` primary key. Relevant externally addressable entities also have a UUID column with a unique index. UUIDs are used for route-model binding and public API identifiers. Internal relationships may use numeric foreign keys for efficient joins.

## Consequences
- Internal relational joins remain conventional and efficient.
- Public URLs do not reveal sequential identifiers.
- Models, route bindings, API Resources, factories, and tests must consistently use UUIDs at external boundaries.
- Do not assume every low-level pivot/log table needs a UUID if it is never externally addressable; add one where a record may be addressed independently or the requirements demand it.
