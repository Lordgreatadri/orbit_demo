# ADR-0005: Organisation-led registration and invitation-only members

- Status: Accepted
- Date: 2026-10-24

## Context
ORBIT serves organisations. The product owner explicitly requires that students, employees, and staff must not self-register.

## Decision
Public registration creates an organisation and its responsible contact/initial organisation administrator. Other tenant users are invited by authorised tenant administrators or added through a controlled provisioning/import workflow. Platform operators use a separate privileged role boundary. Use Laravel session authentication for the first-party web application; add Sanctum when token/API needs are implemented.

## Security requirements
- Verify email & phone before enabling sensitive account actions.
- Rate-limit login, registration, password reset, and invitation endpoints.
- Invitations are single-use, expiring, revocable, and tied to the intended tenant and invitee.
- Acceptance cannot let the invitee choose or switch tenant arbitrarily.
- Never ship known demo passwords in production. Demo email and passwords should be referenced from .env file vaariables.
