<?php

namespace OCA\FederatedGroups\helpers;

class ProductionDomainHelper implements IDomainHelper
{
    public function getOurDomain(): string
    {
        return $_SERVER['HTTP_HOST'];
    }
}