<?php
namespace Flownative\ContainsCondition;

/**
 *
 */
class EntityPrivilege extends \Neos\Flow\Security\Authorization\Privilege\Entity\Doctrine\EntityPrivilege
{
    /**
     * @return ConditionGenerator
     */
    protected function getConditionGenerator()
    {
        return new ConditionGenerator();
    }
}
