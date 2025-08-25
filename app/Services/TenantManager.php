<?php

namespace App\Services;

use App\Models\Company;

class TenantManager
{
    protected ?Company $tenant = null;

    public function setTenant(?Company $company): void
    {
        $this->tenant = $company;
    }

    public function getTenant(): ?Company
    {
        return $this->tenant;
    }
}

