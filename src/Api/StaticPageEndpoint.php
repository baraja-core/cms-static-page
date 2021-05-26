<?php

declare(strict_types=1);

namespace Baraja\StaticPage;


use Baraja\Doctrine\EntityManager;
use Baraja\Localization\Translation;
use Baraja\SelectboxTree\SelectboxItem;
use Baraja\SelectboxTree\SelectboxTree;
use Baraja\StaticPage\Entity\StaticPage;
use Baraja\StructuredApi\BaseEndpoint;
use Baraja\StructuredApi\Entity\ItemsList;
use Baraja\StructuredApi\Entity\ItemsListItem;
use Doctrine\DBAL\DBALException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Nette\Utils\Paginator;
use Nette\Utils\Strings;
use Tracy\Debugger;
use Tracy\ILogger;

final class StaticPageEndpoint extends BaseEndpoint
{
	public function __construct(
		private EntityManager $entityManager,
	) {
	}


	/**
	 * @param string|null $status null, active, hidden
	 */
	public function actionDefault(int $page = 1, int $limit = 32, ?string $status = null, ?string $query = null): void
	{
		$selection = $this->entityManager->getRepository(StaticPage::class)
			->createQueryBuilder('staticPage')
			->select('PARTIAL staticPage.{id, title, updatedDate, active}');

		if ($status === 'active') {
			$selection->andWhere('staticPage.active = TRUE');
		} elseif ($status === 'hidden') {
			$selection->andWhere('staticPage.active = FALSE');
		}

		if ($query !== null) {
			$selection->andWhere('staticPage.title LIKE :query')
				->setParameter('query', '%' . $query . '%');
		}

		$counter = (clone $selection)->select('PARTIAL staticPage.{id}')->getQuery()->getArrayResult();

		$items = $selection
			->leftJoin('staticPage.parent', 'parent')
			->addSelect('PARTIAL parent.{id, title}')
			->setMaxResults($limit)
			->setFirstResult(($page - 1) * $limit)
			->orderBy('staticPage.updatedDate', 'DESC')
			->getQuery()
			->getArrayResult();

		$return = [];
		foreach ($items as $item) {
			$return[] = new ItemsListItem($item['id'], [
				'title' => (string) $item['title'],
				'parent' => $item['parent'] ? [
					'id' => $item['parent']['id'],
					'title' => (string) $item['parent']['title'],
				] : null,
				'updatedDate' => $item['updatedDate'],
				'active' => $item['active'],
			]);
		}

		$this->sendJson([
			'items' => ItemsList::from($return),
			'paginator' => (new Paginator)
				->setItemCount(\count($counter))
				->setItemsPerPage($limit)
				->setPage($page),
		]);
	}


	public function createDefault(string $title, string $slug, ?int $parentId = null): void
	{
		if ($parentId !== null) {
			try {
				/** @var StaticPage $parent */
				$parent = $this->entityManager->getRepository(StaticPage::class)
					->createQueryBuilder('staticPage')
					->where('staticPage.id = :parentId')
					->setParameter('parentId', $parentId)
					->setMaxResults(1)
					->getQuery()
					->getSingleResult();
			} catch (NoResultException | NonUniqueResultException) {
				$this->sendError('Parent page "' . $parentId . '" does not exist.');
			}
		} else {
			$parent = null;
		}

		$staticPage = new StaticPage($title, '', $slug, $parent);
		$this->entityManager->persist($staticPage);
		$this->entityManager->flush($staticPage);

		$this->sendOk([
			'id' => $staticPage->getId(),
		]);
	}


	public function actionDetail(int $id): void
	{
		try {
			$staticPage = $this->getStaticPageById($id);
		} catch (NonUniqueResultException | NoResultException) {
			$this->sendError('Static page "' . $id . '" does not exist.');
		}

		$parent = $staticPage->getParent();
		$this->sendJson([
			'item' => [
				'id' => $staticPage->getId(),
				'title' => $staticPage->getTitle(),
				'content' => $staticPage->getContent(),
				'parent' => $parent !== null ? [
					'id' => $parent->getId(),
					'title' => $parent->getTitle(),
				] : null,
				'active' => $staticPage->isActive(),
			],
		]);
	}


	public function postDetail(int $id, string $title, string $content, bool $active, ?int $parentId = null): void
	{
		try {
			$staticPage = $this->getStaticPageById($id);
		} catch (NoResultException | NonUniqueResultException) {
			$this->sendError('Static page "' . $id . '" does not exist.');
		}

		$parent = null;
		if ($parentId !== null) {
			try {
				$parent = $this->entityManager->getRepository(StaticPage::class)
					->createQueryBuilder('parent')
					->where('parent.id = :parentId')
					->setParameter('parentId', $parentId)
					->getQuery()
					->getSingleResult();
			} catch (NoResultException | NonUniqueResultException) {
				// Silence is golden.
			}
		}

		$staticPage->setParent($parent);
		$staticPage->setTitle($title);
		$staticPage->setContent($content);
		$staticPage->setActive($active);
		$this->entityManager->flush($staticPage);

		$this->sendOk();
	}


	public function actionCheckUniqueSlug(string $slug): void
	{
		$slug = Strings::webalize($slug);
		$page = $this->entityManager->getRepository(StaticPage::class)
			->createQueryBuilder('staticPage')
			->select('PARTIAL staticPage.{id}')
			->where('staticPage.slug = :slug')
			->setParameter('slug', $slug)
			->setMaxResults(1)
			->getQuery()
			->getArrayResult();

		$this->sendJson([
			'exist' => $slug === '' || $page !== [],
			'slug' => $slug,
		]);
	}


	public function actionStaticPagesAsTree(): void
	{
		$tree = new SelectboxTree;
		$table = $this->entityManager->getClassMetadata(StaticPage::class)->table['name'];

		try {
			$data = $this->entityManager->getConnection()->executeQuery(
				$tree->sqlBuilder($table, 'title', 'parent_id'),
			)->fetchAll();
		} catch (DBALException $e) {
			if (\class_exists('\Tracy\Debugger') === true) {
				Debugger::log($e, ILogger::ERROR);
			}
			$data = [];
		}

		$items = [];
		foreach ($data as $item) {
			$items[] = new SelectboxItem(
				(int) $item['id'],
				(string) new Translation($item['title']),
				$item['parent_id'] ? (int) $item['parent_id'] : null,
			);
		}

		$this->sendJson($this->formatBootstrapSelectArray($tree->process($items)));
	}


	public function actionStaticPagesTree(?string $parentId = null): void
	{
		$selection = $this->entityManager->getRepository(StaticPage::class)
			->createQueryBuilder('staticPage')
			->select('PARTIAL staticPage.{id, title, active}')
			->leftJoin('staticPage.parent', 'parent');

		if ($parentId === null) {
			$selection->where('staticPage.parent IS NULL');
		} else {
			$selection->where('staticPage.parent = :parentId')->setParameter('parentId', $parentId);
		}

		/** @var mixed[][] $staticPages */
		$staticPages = $selection
			->addOrderBy('staticPage.insertedDate', 'ASC')
			->groupBy('staticPage.id')
			->getQuery()
			->getArrayResult();

		$staticPageParents = $this->entityManager->getRepository(StaticPage::class)
			->createQueryBuilder('staticPage')
			->leftJoin('staticPage.parent', 'parent')
			->select('PARTIAL staticPage.{id}, PARTIAL parent.{id}')
			->where('staticPage.parent IN (:ids)')
			->setParameter('ids', array_map(static fn(array $item): string => (string) $item['id'], $staticPages))
			->getQuery()
			->getArrayResult();

		$staticPageContainChildren = [];
		foreach ($staticPageParents as $staticPageParent) {
			$staticPageContainChildren[$staticPageParent['parent']['id'] ?? ''] = true;
		}

		$return = [];
		foreach ($staticPages as $staticPage) {
			$return[] = [
				'id' => $staticPage['id'],
				'title' => $staticPage['title'],
				'active' => $staticPage['active'],
				'isChildren' => isset($staticPageContainChildren[$staticPage['id']]),
			];
		}

		$this->sendJson([
			'parentId' => $parentId,
			'staticPages' => $return,
		]);
	}


	/**
	 * @throws NoResultException|NonUniqueResultException
	 */
	private function getStaticPageById(?int $id): StaticPage
	{
		static $cache;

		return $cache ?? $cache = $this->entityManager->getRepository(StaticPage::class)
				->createQueryBuilder('staticPage')
				->select('staticPage, parent')
				->leftJoin('staticPage.parent', 'parent')
				->where('staticPage.id = :id')
				->setParameter('id', $id)
				->getQuery()
				->getSingleResult();
	}
}
