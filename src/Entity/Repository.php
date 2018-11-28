<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\ORM\Entity;

use Spiral\ORM\RepositoryInterface;
use Spiral\ORM\Selector;

/**
 * Repository provides ability to load entities and construct queries.
 */
class Repository implements RepositoryInterface
{
    /** @var Selector */
    private $selector;

    /**
     * Create repository linked to one specific selector.
     *
     * @param Selector $selector
     */
    public function __construct(Selector $selector)
    {
        $this->selector = $selector;
    }

    /**
     * @inheritdoc
     */
    public function findByPK($id)
    {
        return $this->find()->wherePK($id)->fetchOne();
    }

    /**
     * @inheritdoc
     */
    public function findOne(array $where = [])
    {
        return $this->find($where)->fetchOne();
    }

    /**
     * @inheritdoc
     */
    public function findAll(array $where = []): iterable
    {
        return $this->find($where)->fetchAll();
    }

    /**
     * @param array $where
     * @return Selector|iterable
     */
    public function find(array $where = []): Selector
    {
        return (clone $this->selector)->where($where);
    }

    /**
     * Create new version of repository with scope defined by
     * closure function.
     *
     * @param callable $scope
     * @return Repository
     */
    public function withScope(callable $scope): self
    {
        $repository = clone $this;
        call_user_func($scope, $repository->selector);

        return $repository;
    }

    /**
     * Repositories are always immutable by default.
     */
    public function __clone()
    {
        $this->selector = clone $this->selector;
    }
}