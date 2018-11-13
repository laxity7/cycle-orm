<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\ORM\Relation;

use Spiral\ORM\Command\ChainCommand;
use Spiral\ORM\Command\CommandInterface;
use Spiral\ORM\Command\CommandPromiseInterface;
use Spiral\ORM\Command\ConditionalCommand;
use Spiral\ORM\Command\NullCommand;
use Spiral\ORM\Relation;
use Spiral\ORM\State;

//todo: nullable
class HasOneRelation extends AbstractRelation
{
    // todo: move to the strategy
    public function queueChange(
        $parent,
        State $state,
        CommandPromiseInterface $command
    ): CommandInterface {
        $related = $this->getRelated($parent);
        $orig = $state->getRelation($this->relation);

        // todo: need rollback
        $state->setRelation($this->relation, $related);

        $chain = new ChainCommand();

        // delete, we need to think about replace
        if (!empty($orig) && empty($related)) {
            $origState = $this->orm->getHeap()->get($orig);
            $origState->delRef();

            return new ConditionalCommand(
                $this->orm->getMapper(get_class($orig))->queueDelete($orig),
                function () use ($origState) {
                    return $origState->getRefCount() == 0;
                }
            );
        }

        if (!empty($orig) && !empty($related) && $orig !== $related) {
            $origState = $this->orm->getHeap()->get($orig);
            $origState->delRef();

            $chain->addCommand(
                new ConditionalCommand(
                    $this->orm->getMapper(get_class($orig))->queueDelete($orig),
                    function () use ($origState) {
                        return $origState->getRefCount() == 0;
                    }
                )
            );
        }

        if (!empty($related)) {
            $relState = $this->orm->getHeap()->get($related);
            if (!empty($relState)) {
                $relState->addReference();
                if ($relState->getRefCount() > 2) {
                    // todo: detect if it's the same parent over and over again?
                    return new NullCommand();
                }
            }

            // todo: dirty state [?]
            $inner = $this->orm->getMapper(get_class($related))->queueStore($related);

            $chain->addTargetCommand($inner);

            // syncing (TODO: CHECK IF NOT SYNCED ALREADY)
            $command->onExecute(function (CommandPromiseInterface $command) use ($inner, $parent) {
                $inner->setContext(
                    $this->schema[Relation::OUTER_KEY],
                    $this->lookupKey($this->schema[Relation::INNER_KEY], $parent, $command)
                );

                // todo: MORPH KEY
            });

            // todo: update relation state
        }

        return $chain;
    }
}