<?php
namespace Flownative\ContainsCondition;

use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\QuoteStrategy;
use Doctrine\ORM\Query\Filter\SQLFilter as DoctrineSqlFilter;
use Neos\Flow\Persistence\Doctrine\Query;
use Neos\Flow\Security\Exception\InvalidPolicyException;
use Neos\Flow\Security\Exception\InvalidQueryRewritingConstraintException;

/**
 * Property Condition Generator with addditional "contains" capabilities
 */
class PropertyConditionGenerator extends \Neos\Flow\Security\Authorization\Privilege\Entity\Doctrine\PropertyConditionGenerator
{
    /**
     * @param $operandDefinition
     * @return $this
     * @throws InvalidPolicyException
     */
    public function contains($operandDefinition): PropertyConditionGenerator
    {
        if (strpos($this->path, '.') !== false) {
            throw new InvalidPolicyException(sprintf('The "contains" operator does not work on nested property paths (contained a "."). Got: "%s"', $this->path), 1545212769);
        }
        $this->operator = 'contains';
        $this->operandDefinition = $operandDefinition;
        $this->operand = $this->getValueForOperand($operandDefinition);

        return $this;
    }

    /**
     * @param DoctrineSqlFilter $sqlFilter
     * @param ClassMetadata $targetEntity
     * @param string $targetTableAlias
     * @return string
     * @throws InvalidQueryRewritingConstraintException
     */
    public function getSql(DoctrineSqlFilter $sqlFilter, ClassMetadata $targetEntity, $targetTableAlias)
    {
        $targetEntityPropertyName = (strpos($this->path, '.') ? substr($this->path, 0, strpos($this->path, '.')) : $this->path);
        $quoteStrategy = $this->entityManager->getConfiguration()->getQuoteStrategy();

        try {
            return parent::getSql($sqlFilter, $targetEntity, $targetTableAlias);
        } catch (InvalidQueryRewritingConstraintException $e) {
            if ($e->getCode() === 1416397655) {
                return $this->getSqlForPropertyContains($sqlFilter, $quoteStrategy, $targetEntity, $targetTableAlias, $targetEntityPropertyName);
            }

            throw $e;
        }
    }

    /**
     * @param DoctrineSqlFilter $sqlFilter
     * @param QuoteStrategy $quoteStrategy
     * @param ClassMetadata $targetEntity
     * @param string $targetTableAlias
     * @param string $targetEntityPropertyName
     * @return string
     * @throws InvalidQueryRewritingConstraintException
     * @throws \Exception
     */
    protected function getSqlForPropertyContains(DoctrineSqlFilter $sqlFilter, QuoteStrategy $quoteStrategy, ClassMetadata $targetEntity, string $targetTableAlias, string $targetEntityPropertyName): string
    {
        if ($this->operator !== 'contains') {
            throw new InvalidQueryRewritingConstraintException('Multivalued properties are not supported in a content security constraint path unless the "contains" operation is used! Got: "' . $this->path . ' ' . $this->operator . ' ' . $this->operandDefinition . '"', 1416397655);
        }
        if (is_array($this->operandDefinition)) {
            throw new InvalidQueryRewritingConstraintException('Multivalued properties with "contains" cannot have a multivalued operand! Got: "' . $this->path . ' ' . $this->operator . ' ' . $this->operandDefinition . '"', 1545145424);
        }
        $associationMapping = $targetEntity->getAssociationMapping($targetEntityPropertyName);
        $identityColumnNames = $targetEntity->getIdentifierColumnNames();
        if (count($identityColumnNames) > 1) {
            throw new InvalidQueryRewritingConstraintException('Cannot apply constraints on multi-identity entities.', 1545219903);
        }
        $identityColumnName = reset($identityColumnNames);
        $parameterValue = $this->operand;
        if (is_object($parameterValue)) {
            $parameterValue = $this->persistenceManager->getIdentifierByObject($parameterValue);
        }
        $parameterValue = $this->entityManager->getConnection()->quote($parameterValue);
        if (isset($associationMapping['joinTable'])) {
            // TODO: We take the first join column here, technically there could be multiple though.
            if (!empty($associationMapping['mappedBy'])) {
                $joinColumn = $associationMapping['joinTable']['joinColumns'][0]['name'];
                $reverseColumn = $associationMapping['joinTable']['inverseJoinColumns'][0]['name'];
            } else {
                $joinColumn = $associationMapping['joinTable']['inverseJoinColumns'][0]['name'];
                $reverseColumn = $associationMapping['joinTable']['joinColumns'][0]['name'];
            }
            $subQuerySql = 'SELECT ' . $reverseColumn . ' FROM ' . $associationMapping['joinTable']['name'] . ' WHERE ' . $joinColumn . ' = ' . $parameterValue;
        } else {
            $subselectQuery = new Query($targetEntity->getAssociationTargetClass($targetEntityPropertyName));
            $rootAliases = $subselectQuery->getQueryBuilder()->getRootAliases();
            $primaryRootAlias = reset($rootAliases);
            $subselectQuery->getQueryBuilder()->where('' . $primaryRootAlias . ' = ' . $parameterValue);
            $subselectQuery->getQueryBuilder()->select('IDENTITY(' . $primaryRootAlias . '.' . $associationMapping['mappedBy'] . ')');
            $subQuerySql = $subselectQuery->getSql();
        }

        return $targetTableAlias . '.' . $identityColumnName . ' IN (' . $subQuerySql . ')';
    }
}
