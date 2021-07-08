<?php

declare(strict_types=1);

namespace Baraja\StaticPage;


use Baraja\Doctrine\EntityManager;
use Baraja\Plugin\BasePlugin;
use Baraja\StaticPage\Entity\StaticPage;

final class StaticPagePlugin extends BasePlugin
{
	public function __construct(
		private EntityManager $entityManager,
	) {
	}


	public function getName(): string
	{
		return 'Static Page';
	}


	public function getBaseEntity(): string
	{
		return StaticPage::class;
	}


	public function getIcon(): ?string
	{
		return 'file-text';
	}


	public function actionDetail(int $id): void
	{
		/** @var array<int, array{id: int, title: string}> $staticPage */
		$staticPage = $this->entityManager->getRepository(StaticPage::class)
			->createQueryBuilder('staticPage')
			->select('PARTIAL staticPage.{id, title}')
			->where('staticPage.id = :id')
			->setParameter('id', $id)
			->setMaxResults(1)
			->getQuery()
			->getArrayResult();

		if ($staticPage === []) {
			$this->error('Static page does not exist.');
		}

		$this->setTitle((string) ($staticPage[0]['title'] ?? ''));
	}
}
