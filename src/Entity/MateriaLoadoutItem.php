<?php

namespace App\Entity;

use App\Repository\MateriaLoadoutItemRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\Ignore;

/**
 * @ORM\Entity(repositoryClass=MateriaLoadoutItemRepository::class)
 */
class MateriaLoadoutItem {
	/**
	 * @ORM\Id()
	 * @ORM\GeneratedValue()
	 * @ORM\Column(type="integer")
	 * @Groups({"view", "diff"})
	 */
	private $id;

	/**
	 * @ORM\ManyToOne(targetEntity=MateriaLoadout::class, inversedBy="items", cascade={"persist"})
	 * @ORM\JoinColumn(nullable=false)
	 * @Ignore()
	 */
	private $loadout;

	/**
	 * @ORM\ManyToOne(targetEntity=Materia::class, cascade={"persist"}, fetch="EAGER")
	 * @Groups({"view", "diff"})
	 */
	private $materia;

	/**
	 * @ORM\Column(type="integer")
	 * @Groups({"view", "diff"})
	 */
	private $row;

	/**
	 * @ORM\Column(type="integer")
	 * @Groups({"view", "diff"})
	 */
	private $col;

	/**
	 * @ORM\Column(type="string", length=4)
	 * @Groups({"view", "diff"})
	 */
	private $charName;

	public function getId(): ?int {
		return $this->id;
	}

	public function getLoadout(): ?MateriaLoadout {
		return $this->loadout;
	}

	public function setLoadout(?MateriaLoadout $loadout): self {
		$this->loadout = $loadout;

		return $this;
	}

	public function getMateria(): ?Materia {
		return $this->materia;
	}

	public function setMateria(?Materia $materia): self {
		$this->materia = $materia;

		return $this;
	}

	public function getRow(): ?int {
		return $this->row;
	}

	public function setRow(int $row): self {
		$this->row = $row;

		return $this;
	}

	public function getCol(): ?int {
		return $this->col;
	}

	public function setCol(int $col): self {
		$this->col = $col;

		return $this;
	}

	public function getCharName(): ?string {
		return $this->charName;
	}

	public function setCharName(string $charName): self {
		$this->charName = $charName;

		return $this;
	}

	public function __toString() {
		return $this->charName . $this->row . $this->col . ':' . $this->materia ?? 'NONE';
	}
}
