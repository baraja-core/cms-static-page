<?php

declare(strict_types=1);

namespace Baraja\StaticPage\Entity;


use Baraja\Doctrine\Identifier\Identifier;
use Baraja\Localization\TranslateObject;
use Baraja\Localization\Translation;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Nette\Utils\DateTime;
use Nette\Utils\Strings;

/**
 * @ORM\Entity()
 * @ORM\Table(name="core__static_page")
 * @method Translation getTitle(?string $locale = null)
 * @method void setTitle(string $title, ?string $locale = null)
 * @method Translation getContent(?string $locale = null)
 * @method void setContent(string $content, ?string $locale = null)
 */
class StaticPage
{
	use Identifier;
	use TranslateObject;

	/**
	 * @var Translation
	 * @ORM\Column(type="translate")
	 */
	private $title;

	/**
	 * @var string
	 * @ORM\Column(type="string", length=64, unique=true)
	 */
	private $slug;

	/**
	 * @var Translation
	 * @ORM\Column(type="translate")
	 */
	private $content;

	/**
	 * @var self|null
	 * @ORM\ManyToOne(targetEntity="StaticPage", inversedBy="children")
	 */
	private $parent;

	/**
	 * @var self[]|Collection
	 * @ORM\OneToMany(targetEntity="StaticPage", mappedBy="parent")
	 */
	private $children;

	/**
	 * @var bool
	 * @ORM\Column(type="boolean")
	 */
	private $active = true;

	/**
	 * @var \DateTime
	 * @ORM\Column(type="datetime")
	 */
	private $updatedDate;

	/**
	 * @var \DateTime
	 * @ORM\Column(type="datetime")
	 */
	private $insertedDate;


	public function __construct(string $title, string $content = '', ?string $slug = null, ?self $parent = null)
	{
		$this->setTitle(Strings::firstUpper(trim($title)));
		$this->setSlug($slug ?? $title);
		$this->setContent(trim($content));
		$this->parent = $parent;
		$this->children = new ArrayCollection;
		$this->updatedDate = DateTime::from('now');
		$this->insertedDate = DateTime::from('now');
	}


	public function getName(?string $locale = null): ?string
	{
		return trim((string) $this->title->getTranslation($locale)) ?: null;
	}


	public function __toString(): string
	{
		return (string) $this->getContent();
	}


	public function getSlug(): string
	{
		return $this->slug;
	}


	public function setSlug(string $slug): void
	{
		$this->slug = Strings::webalize($slug);
	}


	public function getParent(): ?self
	{
		return $this->parent;
	}


	public function setParent(?self $parent): self
	{
		$this->parent = $parent;

		return $this;
	}


	public function isActive(): bool
	{
		return $this->active;
	}


	public function setActive(bool $active): void
	{
		$this->active = $active;
	}


	/**
	 * @return StaticPage[]|Collection
	 */
	public function getChildren()
	{
		return $this->children;
	}


	public function addChild(self $child): void
	{
		$this->children[] = $child;
	}


	public function getUpdatedDate(): \DateTime
	{
		return $this->updatedDate;
	}


	public function setUpdatedDate(\DateTime $updatedDate): void
	{
		$this->updatedDate = $updatedDate;
	}


	public function getInsertedDate(): \DateTime
	{
		return $this->insertedDate;
	}
}
