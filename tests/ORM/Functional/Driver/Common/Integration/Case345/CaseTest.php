<?php

declare(strict_types=1);

namespace Cycle\ORM\Tests\Functional\Driver\Common\Integration\Case345;

use Cycle\ORM\Select;
use Cycle\ORM\Tests\Functional\Driver\Common\BaseTest;
use Cycle\ORM\Tests\Functional\Driver\Common\Integration\Case345\Entity\UserId;
use Cycle\ORM\Tests\Functional\Driver\Common\Integration\IntegrationTestTrait;
use Cycle\ORM\Tests\Traits\TableTrait;

abstract class CaseTest extends BaseTest
{
    use IntegrationTestTrait;
    use TableTrait;

    public function setUp(): void
    {
        // Init DB
        parent::setUp();
        $this->makeTables();
        $this->fillData();

        $this->loadSchema(__DIR__ . '/schema.php');
    }

    public function testChechWrapper(): void
    {
        /** @var Entity\User $post */
        $user = (new Select($this->orm, Entity\User::class))
            ->wherePK(1)
            ->fetchOne();

        /** @var Entity\Post $post */
        $post = new Entity\Post('test', 'test');
        $post->user_id = new UserId(1);
        $post->user = $user;

        $this->save($post);

        $post->user_id = new UserId(2);

        $this->save($post);

        $this->assertEquals('2', (string)$post->user_id);
        $this->orm->getHeap()->clean();

        $post = (new Select($this->orm, Entity\Post::class))
            ->wherePK($post->id)
            ->fetchOne();

        $this->assertEquals('2', (string)$post->user_id);
        $this->assertEquals('2', (string)$post->user->id);
    }

    private function makeTables(): void
    {
        // Make tables
        $this->makeTable(Entity\User::ROLE, [
            'id' => 'primary', // autoincrement
            'login' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ]);

        $this->makeTable('post', [
            'id' => 'primary',
            'user_id' => 'int',
            'slug' => 'string',
            'title' => 'string',
            'public' => 'bool',
            'content' => 'string',
            'published_at' => 'datetime,nullable',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime,nullable',
        ]);
        $this->makeFK('post', 'user_id', 'user', 'id', 'NO ACTION', 'NO ACTION');
    }

    private function fillData(): void
    {
        $this->getDatabase()->table('user')->insertMultiple(
            ['login'],
            [
                ['user-1'],
                ['user-2'],
                ['user-3'],
                ['user-4'],
            ],
        );
        $this->getDatabase()->table('post')->insertMultiple(
            ['user_id', 'slug', 'title', 'public', 'content'],
            [
                [1, 'slug-string-1', 'Title 1', true, 'Foo-bar-baz content 1'],
                [2, 'slug-string-2', 'Title 2', true, 'Foo-bar-baz content 2'],
                [2, 'slug-string-3', 'Title 3', true, 'Foo-bar-baz content 3'],
                [3, 'slug-string-4', 'Title 4', true, 'Foo-bar-baz content 4'],
                [3, 'slug-string-5', 'Title 5', true, 'Foo-bar-baz content 5'],
                [3, 'slug-string-6', 'Title 6', true, 'Foo-bar-baz content 6'],
            ],
        );
    }
}
