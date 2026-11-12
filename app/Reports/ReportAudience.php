<?php

namespace App\Reports;

enum ReportAudience: string
{
    case Tenant = 'tenant';
    case Platform = 'platform';
}
