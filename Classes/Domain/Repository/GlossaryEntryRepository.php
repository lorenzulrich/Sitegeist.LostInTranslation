<?php
declare(strict_types=1);

namespace Sitegeist\LostInTranslation\Domain\Repository;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\Repository;
use Sitegeist\LostInTranslation\Domain\Model\GlossaryEntry;

/**
 * @Flow\Scope("singleton")
 */
class GlossaryEntryRepository extends Repository
{
    /**
     * @return GlossaryEntry[]
     */
    public function findByAggregateIdentifier(string $aggregateIdentifier): array
    {
        $query = $this->createQuery();

        $constraints = $query->logicalAnd(
            $query->equals('aggregateIdentifier', $aggregateIdentifier),
        );

        $query->matching($constraints);

        return $query->execute()->toArray();
    }
}
