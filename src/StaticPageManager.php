<?php

declare(strict_types=1);

namespace Baraja\StaticPage;


use Baraja\Doctrine\EntityManager;
use Baraja\StaticPage\Entity\StaticPage;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

final class StaticPageManager
{

	/** @var EntityManager */
	private $entityManager;


	public function __construct(EntityManager $entityManager)
	{
		$this->entityManager = $entityManager;
	}


	/**
	 * @param int|string|mixed $identifier id or slug
	 * @return StaticPage
	 * @throws NoResultException|NonUniqueResultException
	 */
	public function get($identifier): StaticPage
	{
		$selection = $this->entityManager->getRepository(StaticPage::class)
			->createQueryBuilder('staticPage')
			->setMaxResults(1);

		if (\is_int($identifier)) {
			$selection->andWhere('staticPage.id = :id')
				->setParameter('id', $identifier);
		} elseif (\is_string($identifier)) {
			$selection->andWhere('staticPage.slug = :slug')
				->setParameter('slug', $identifier);
		} else {
			throw new \InvalidArgumentException('Identifier must be integer for ID or string for slug, but type "' . \gettype($identifier) . '" given.');
		}

		return $selection->getQuery()->getSingleResult();
	}
}
