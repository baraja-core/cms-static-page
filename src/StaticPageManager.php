<?php

declare(strict_types=1);

namespace Baraja\StaticPage;


use Baraja\Doctrine\EntityManager;
use Baraja\StaticPage\Entity\StaticPage;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

final class StaticPageManager
{
	private EntityManager $entityManager;


	public function __construct(EntityManager $entityManager)
	{
		$this->entityManager = $entityManager;
	}


	/**
	 * @return StaticPage
	 * @throws NoResultException|NonUniqueResultException
	 */
	public function get(int|string $identifier): StaticPage
	{
		$selection = $this->entityManager->getRepository(StaticPage::class)
			->createQueryBuilder('staticPage')
			->setMaxResults(1);

		if (\is_int($identifier)) {
			$selection->andWhere('staticPage.id = :id')
				->setParameter('id', $identifier);
		} else {
			$selection->andWhere('staticPage.slug = :slug')
				->setParameter('slug', $identifier);
		}

		return $selection->getQuery()->getSingleResult();
	}
}
