<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\ORM\Relation;

use Spiral\ORM\Command\CarrierInterface;
use Spiral\ORM\Command\CommandInterface;
use Spiral\ORM\Command\Control\Condition;
use Spiral\ORM\Command\Control\Sequence;
use Spiral\ORM\PromiseInterface;
use Spiral\ORM\Point;
use Spiral\ORM\Util\Collection\CollectionPromise;
use Spiral\ORM\Util\Promise;

class HasManyRelation extends AbstractRelation
{
    use Traits\CollectionTrait;

    public function initPromise(Point $point): array
    {
        // todo: here we need paths (!)
        if (empty($innerKey = $this->fetchKey($point, $this->innerKey))) {
            return [null, null];
        }

        $pr = new Promise(
            [$this->outerKey => $innerKey],
            function (array $scope) use ($innerKey) {
                // todo: where is part of CONTEXT - yeeeaeh ????
                return $this->orm->getMapper($this->class)->getRepository()->findAll($scope);
            }
        );

        return [new CollectionPromise($pr), $pr];
    }

    /**
     * @inheritdoc
     */
    public function queueRelation(
        CarrierInterface $parentCommand,
        $parentEntity,
        Point $parentState,
        $related,
        $original
    ): CommandInterface {

        // todo: i can do quick compare here?
        // todo: why there is so many todos?

        if ($related instanceof PromiseInterface) {
            // todo: resolve both original and related
            $related = $related->__resolve();
        }

        if ($original instanceof PromiseInterface) {
            // todo: check consecutive changes
            $original = $original->__resolve();
            // todo: state->setRelation (!!!!!!)
        }

        $sequence = new Sequence();

        foreach ($related as $item) {
            $sequence->addCommand($this->queueStore($parentState, $item));
        }

        foreach ($this->calcDeleted($related, $original ?? []) as $item) {
            $sequence->addCommand($this->queueDelete($parentState, $item));
        }

        return $sequence;
    }

    /**
     * Return objects which are subject of removal.
     *
     * @param array $related
     * @param array $original
     * @return array
     */
    protected function calcDeleted(array $related, array $original)
    {
        return array_udiff($original ?? [], $related, function ($a, $b) {
            return strcmp(spl_object_hash($a), spl_object_hash($b));
        });
    }

    /**
     * Persist related object.
     *
     * @param Point  $parent
     * @param object $related
     * @return CarrierInterface
     */
    protected function queueStore(Point $parent, $related): CarrierInterface
    {
        $relStore = $this->orm->queueStore($related);
        $relState = $this->getPoint($related);
        $relState->addClaim();

        $this->addDependency($parent, $this->innerKey, $relStore, $relState, $this->outerKey);

        return $relStore;
    }

    /**
     * Remove one of related objects.
     *
     * @param Point  $parent
     * @param object $related
     * @return CommandInterface
     */
    protected function queueDelete(Point $parent, $related): CommandInterface
    {
        $origState = $this->getPoint($related);
        $origState->decClaim();

        return new Condition(
            $this->orm->queueDelete($related),
            function () use ($origState) {
                return !$origState->hasClaims();
            }
        );
    }
}