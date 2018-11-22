<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\ORM\Relation\ManyToMany;

use Doctrine\Common\Collections\Collection;
use Spiral\ORM\Collection\PivotedCollection;
use Spiral\ORM\Collection\PivotedCollectionInterface;
use Spiral\ORM\Command\CommandInterface;
use Spiral\ORM\Command\ContextualInterface;
use Spiral\ORM\Command\Control\Defer;
use Spiral\ORM\Command\Control\Sequence;
use Spiral\ORM\Iterator;
use Spiral\ORM\ORMInterface;
use Spiral\ORM\Relation;
use Spiral\ORM\State;
use Spiral\ORM\StateInterface;
use Spiral\ORM\Util\ContextStorage;

class PivotedRelation extends Relation\AbstractRelation
{
    /** @var string|null */
    private $pivotEntity;

    /** @var string */
    protected $thoughtInnerKey;

    /** @var string */
    protected $thoughtOuterKey;

    /**
     * @param ORMInterface $orm
     * @param string       $class
     * @param string       $relation
     * @param array        $schema
     */
    public function __construct(ORMInterface $orm, string $class, string $relation, array $schema)
    {
        parent::__construct($orm, $class, $relation, $schema);
        $this->pivotEntity = $this->define(Relation::PIVOT_ENTITY);
        $this->thoughtInnerKey = $this->define(Relation::THOUGHT_INNER_KEY);
        $this->thoughtOuterKey = $this->define(Relation::THOUGHT_OUTER_KEY);
    }

    /**
     * @inheritdoc
     */
    public function init($data): array
    {
        $elements = [];
        $pivotData = new \SplObjectStorage();

        foreach (new Iterator($this->orm, $this->class, $data) as $pivot => $entity) {
            $elements[] = $entity;
            $pivotData[$entity] = $this->orm->make($this->pivotEntity, $pivot, State::LOADED);
        }

        return [
            new PivotedCollection($elements, $pivotData),
            new ContextStorage($elements, $pivotData)
        ];
    }

    /**
     * @inheritdoc
     */
    public function extract($data)
    {
        if ($data instanceof PivotedCollectionInterface) {
            return new ContextStorage($data->toArray(), $data->getPivotData());
        }

        if ($data instanceof Collection) {
            return new ContextStorage($data->toArray());
        }

        return new ContextStorage();
    }

    /**
     * @inheritdoc
     */
    public function queueRelation(
        ContextualInterface $command,
        $entity,
        StateInterface $state,
        $related,
        $original
    ): CommandInterface {
        /**
         * @var ContextStorage $related
         * @var ContextStorage $original
         */
        $original = $original ?? new ContextStorage();

        $sequence = new Sequence();

        // link/sync new and existed elements
        foreach ($related->getElements() as $item) {
            $sequence->addCommand(
                $this->link($state, $item, $related->get($item), $original->get($item))
            );
        }

        // un-link old elements
        foreach ($original->getElements() as $item) {
            if (!$related->has($item)) {
                $sequence->addCommand($this->orm->queueDelete($original->get($item)));
            }
        }

        return $sequence;
    }

    /**
     * Link two entities together and create/update pivot context.
     *
     * @param StateInterface $state
     * @param object         $related
     * @param object         $pivot
     * @param object         $origPivot
     * @return CommandInterface
     */
    protected function link(StateInterface $state, $related, $pivot, $origPivot): CommandInterface
    {
        $relStore = $this->orm->queueStore($related);
        $relState = $this->getState($related);

        // todo: what about origPivot?
        if (!is_object($pivot)) {
            $pivot = $this->orm->make($this->pivotEntity, $pivot ?? []);
        }

        $pivotState = $this->getState($pivot);
        if ($pivotState == null || $pivotState->getState() == State::NEW) {
            // defer the insert until pivot keys are resolved
            $pivotStore = new Defer(
                $this->orm->queueStore($pivot),
                [$this->thoughtInnerKey, $this->thoughtOuterKey],
                (string)$this
            );

            $this->promiseContext($pivotStore, $state, $this->innerKey, null, $this->thoughtInnerKey);
            $this->promiseContext($pivotStore, $relState, $this->outerKey, null, $this->thoughtOuterKey);
        } else {
            // objects already linked, we can try to update pivot data if needed
            $pivotStore = $this->orm->queueStore($pivot);
        }

        $sequence = new Sequence();
        $sequence->addCommand($relStore);
        $sequence->addCommand($pivotStore);

        return $sequence;
    }
}