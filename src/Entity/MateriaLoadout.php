<?php

namespace App\Entity;

use App\Repository\MateriaLoadoutRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\Ignore;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass=MateriaLoadoutRepository::class)
 */
class MateriaLoadout {
	/**
	 * @ORM\Id()
	 * @ORM\GeneratedValue()
	 * @ORM\Column(type="integer")
	 * @Groups({"view", "tree"})
	 */
	private $id;

	/**
	 * @ORM\Column(type="string", length=255)
	 * @Assert\NotBlank()
	 * @Groups({"view", "tree"})
	 */
	private $name;

	/**
	 * @ORM\ManyToOne(targetEntity=User::class, inversedBy="materiaLoadouts")
	 * @ORM\JoinColumn(nullable=false)
	 * @Ignore()
	 */
	private $owner;

	/**
	 * @ORM\OneToMany(targetEntity=MateriaLoadoutItem::class, mappedBy="loadout", orphanRemoval=true, cascade={"persist", "remove"})
	 * @Groups({"view"})
	 */
	private $items;

	/**
	 * @ORM\ManyToOne(targetEntity=MateriaLoadout::class, inversedBy="children")
	 * @Ignore()
	 */
	private $parent;

	/**
	 * @ORM\OneToMany(targetEntity=MateriaLoadout::class, mappedBy="parent", orphanRemoval=true, cascade={"persist", "remove"})
	 * @Ignore()
	 */
	private $children;

	/**
	 * @ORM\Column(type="json")
	 * @Groups({"view"})
	 */
	private $partyOrder = ['c', 'b', 't', 'a'];

	/**
	 * @ORM\Column(type="text", nullable=true)
	 */
	private $preferredChangeKey;

	/**
	 * @ORM\Column(type="string", length=2, nullable=true)
	 */
	private $startCharacter;

	/**
	 * @ORM\Column(type="text", nullable=true)
	 * @Groups({"notes"})
	 */
	private $notes;

	public function __construct() {
		$this->children = new ArrayCollection();
		$this->items    = new ArrayCollection();

		foreach (['c', 't', 'b', 'a'] as $char) {
			for ($row = 0; $row <= 1; $row++) {
				for ($col = 0; $col < ($row === 0 ? 7 : 4); $col++) {
					$i = new MateriaLoadoutItem();
					$i
						->setCharName($char)
						->setRow($row)
						->setCol($col);

					$this->addItem($i);
				}
			}
		}
	}

	public function getId(): ?int {
		return $this->id;
	}

	public function getName(): ?string {
		return $this->name;
	}

	public function setName(string $name): self {
		$this->name = $name;

		return $this;
	}

	/**
	 * @return Collection|MateriaLoadoutItem[]
	 */
	public function getItems(): Collection {
		return $this->items;
	}

	public function addItem(MateriaLoadoutItem $item): self {
		if (!$this->items->contains($item)) {
			$this->items[] = $item;
			$item->setLoadout($this);
		}

		return $this;
	}

	public function removeItem(MateriaLoadoutItem $item): self {
		if ($this->items->contains($item)) {
			$this->items->removeElement($item);
			// set the owning side to null (unless already changed)
			if ($item->getLoadout() === $this) {
				$item->setLoadout(null);
			}
		}

		return $this;
	}

	/**
	 * @return $this
	 */
	public function getRoot() {
		$c = $this;
		while ($c->getParent() !== null) {
			$c = $c->getParent();
		}

		return $c;
	}

	public function getOwner(): ?User {
		return $this->owner;
	}

	public function setOwner(?User $owner): self {
		$this->owner = $owner;

		return $this;
	}

	public function getParent(): ?self {
		return $this->parent;
	}

	public function setParent(?self $parent): self {
		$this->parent = $parent;

		return $this;
	}

	/**
	 * @return Collection|self[]
	 */
	public function getChildren(): Collection {
		return $this->children;
	}

	public function addChild(self $child): self {
		if (!$this->children->contains($child)) {
			$this->children[] = $child;
			$child->setParent($this);
		}

		return $this;
	}

	public function removeChild(self $child): self {
		if ($this->children->contains($child)) {
			$this->children->removeElement($child);
			// set the owning side to null (unless already changed)
			if ($child->getParent() === $this) {
				$child->setParent(null);
			}
		}

		return $this;
	}

	public function getPartyOrder(): ?array {
		return $this->partyOrder;
	}

	public function setPartyOrder(array $partyOrder): self {
		$this->partyOrder = $partyOrder;

		return $this;
	}

	public function getPreferredChangeKey(): ?string {
		return $this->preferredChangeKey;
	}

	public function setPreferredChangeKey(?string $preferredChangeKey): self {
		$this->preferredChangeKey = $preferredChangeKey;

		return $this;
	}

	public function getStartCharacter(): ?string {
		return $this->startCharacter;
	}

	public function setStartCharacter(?string $startCharacter): self {
		$this->startCharacter = $startCharacter;

		return $this;
	}

	public function getNotes(): ?string {
		return $this->notes;
	}

	public function setNotes(?string $notes): self {
		$this->notes = $notes;

		return $this;
	}
}
