<?php

namespace App\Http\Middleware;

/**
 * @deprecated Use IdentifyTenant. Hostname resolution replaced user-derived
 * tenant context (ADR-0011). This subclass remains so older references resolve.
 */
class SetTenantContext extends IdentifyTenant {}
