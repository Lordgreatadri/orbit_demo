# ADR-0001: Modular monolith for the MVP

- Status: Accepted
- Date: 2026-10-24

## Context
ORBIT must launch efficiently while supporting multiple organisations, a web-first UI, APIs, and future integrations. The EPA prototype explicitly excludes microservices.

## Decision
Use one Laravel application organised by clear domain boundaries. Use Laravel-native conventions and extract service/action classes only where they clarify business workflows. Integrations are decoupled behind contracts and adapters.

## Consequences
- Lower operational complexity and easier local development.
- Transactions can protect workflows within one database.
- Domain boundaries and integration contracts must be maintained to avoid a monolith becoming tightly coupled.
- Services may be extracted later only with measurable operational or scaling need and a separate ADR.
