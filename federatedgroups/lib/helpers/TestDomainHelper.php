<?php

namespace OCA\FederatedGroups\helpers;

class TestDomainHelper implements IDomainHelper
{
    public function getOurDomain(): string
    {
        return "oc1.docker";
    }
}