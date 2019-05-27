<?php
namespace Flownative\ContainsCondition;

/**
 *
 */
class ConditionGenerator extends \Neos\Flow\Security\Authorization\Privilege\Entity\Doctrine\ConditionGenerator
{
    /**
     * @param string $path The property path
     * @return PropertyConditionGenerator
     */
    public function property($path)
    {
        return new PropertyConditionGenerator($path);
    }
}
