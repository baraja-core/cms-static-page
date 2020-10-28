<?php

declare(strict_types=1);

namespace Baraja\StaticPage;


use Baraja\Doctrine\EntityManager;
use Baraja\Plugin\BasePlugin;
use Baraja\StaticPage\Entity\StaticPage;

final class StaticPagePlugin extends BasePlugin
{
	private EntityManager $entityManager;


	public function __construct(EntityManager $entityManager)
	{
		$this->entityManager = $entityManager;
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
		return 'fa fa-file';
	}


	public function actionDetail(int $id): void
	{
		/** @var mixed[][] $staticPage */
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

			return;
		}

		$this->setTitle((string) ($staticPage[0]['title'] ?? ''));
	}
}
