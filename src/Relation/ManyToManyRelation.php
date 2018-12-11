<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\ORM\Relation;

use Spiral\Database\DatabaseInterface;
use Spiral\ORM\Command\Branch\Sequence;
use Spiral\ORM\Command\CommandInterface;
use Spiral\ORM\Command\ContextCarrierInterface as CC;
use Spiral\ORM\Command\Database\Delete;
use Spiral\ORM\Command\Database\Insert;
use Spiral\ORM\Context\ConsumerInterface;
use Spiral\ORM\Heap\Node;
use Spiral\ORM\Iterator;
use Spiral\ORM\ORMInterface;
use Spiral\ORM\Promise\PromiseInterface;
use Spiral\ORM\Relation;
use Spiral\ORM\Relation\Pivoted;

class ManyToManyRelation extends AbstractRelation
{
    use Relation\Traits\PivotedTrait;

    /** @var string */
    protected $thoughtInnerKey;

    /** @var string */
    protected $thoughtOuterKey;

    /**
     * @param ORMInterface $orm
     * @param string       $target
     * @param string       $name
     * @param array        $schema
     */
    public function __construct(ORMInterface $orm, string $name, string $target, array $schema)
    {
        parent::__construct($orm, $name, $target, $schema);
        $this->thoughtInnerKey = $this->schema[Relation::THOUGHT_INNER_KEY] ?? null;
        $this->thoughtOuterKey = $this->schema[Relation::THOUGHT_OUTER_KEY] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function init(array $data): array
    {
        $elements = [];
        $pivotData = new \SplObjectStorage();

        foreach (new Iterator($this->orm, $this->target, $data) as $pivot => $entity) {
            $pivotData[$entity] = $pivot;
            $elements[] = $entity;
        }

        return [
            new Pivoted\PivotedCollection($elements, $pivotData),
            new Pivoted\PivotedStorage($elements, $pivotData)
        ];
    }

    /**
     * @inheritdoc
     *
     * @param Pivoted\PivotedStorage $related
     * @param Pivoted\PivotedStorage $original
     */
    public function queue(CC $parentStore, $parentEntity, Node $parentNode, $related, $original): CommandInterface
    {
        $original = $original ?? new Pivoted\PivotedStorage();

        if ($related instanceof PromiseInterface) {
            $related = $related->__resolve();
        }

        if ($original instanceof PromiseInterface) {
            $original = $original->__resolve();
        }

        $sequence = new Sequence();

        // link/sync new and existed elements
        foreach ($related->getElements() as $item) {
            $sequence->addCommand($this->link($parentNode, $item, $original->has($item)));
        }

        // un-link old elements
        foreach ($original->getElements() as $item) {
            if (!$related->has($item)) {
                $sequence->addCommand($this->unlink($parentNode, $item));
            }
        }

        return $sequence;
    }

    /**
     * Link two entities together and create/update pivot context.
     *
     * @param Node   $parentNode
     * @param object $related
     * @param bool   $exists
     * @return CommandInterface
     */
    protected function link(Node $parentNode, $related, $exists): CommandInterface
    {
        $relStore = $this->orm->queueStore($related);

        if ($exists) {
            // no changes in relation between the objects
            return $relStore;
        }

        $sync = new Insert($this->pivotDatabase(), $this->pivotTable());

        $sync->waitContext($this->thoughtInnerKey, true);
        $sync->waitContext($this->thoughtOuterKey, true);

        $parentNode->forward($this->innerKey, $sync, $this->thoughtInnerKey, true);
        $this->getNode($related)->forward($this->outerKey, $sync, $this->thoughtOuterKey, true);

        $sequence = new Sequence();
        $sequence->addCommand($relStore);
        $sequence->addCommand($sync);

        return $sequence;
    }

    /**
     * Remove the connection between two objects.
     *
     * @param Node   $parentNode
     * @param object $related
     * @return CommandInterface
     */
    protected function unlink(Node $parentNode, $related): CommandInterface
    {
        $relNode = $this->getNode($related);

        $delete = new Delete($this->pivotDatabase(), $this->pivotTable());
        $delete->waitScope($this->thoughtOuterKey);
        $delete->waitScope($this->thoughtInnerKey);

        $parentNode->forward(
            $this->innerKey
            , $delete,
            $this->thoughtInnerKey,
            true,
            ConsumerInterface::SCOPE
        );

        $relNode->forward(
            $this->outerKey,
            $delete,
            $this->thoughtOuterKey,
            true,
            ConsumerInterface::SCOPE
        );

        return $delete;
    }

    /**
     * @return DatabaseInterface
     */
    protected function pivotDatabase(): DatabaseInterface
    {
        // always expect entities to be located in a same database
        return $this->getSource()->getDatabase();
    }

    /**
     * @return string
     */
    protected function pivotTable(): string
    {
        return $this->schema[Relation::PIVOT_TABLE] ?? null;
    }
}