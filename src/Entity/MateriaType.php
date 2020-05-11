<?php

namespace App\Entity;

use App\Repository\MateriaTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;

/**
 * @ORM\Entity(repositoryClass=MateriaTypeRepository::class)
 */
class MateriaType {
	/**
	 * @ORM\Id()
	 * @ORM\GeneratedValue()
	 * @ORM\Column(type="integer")
	 * @Groups({"view", "listing", "diff"})
	 */
	private $id;

	/**
	 * @ORM\Column(type="string", length=255)
	 * @Groups({"view", "listing", "diff"})
	 */
	private $name;

	/**
	 * @ORM\Column(type="string", length=255)
	 * @Groups({"view", "listing", "diff"})
	 */
	private $color;

	/**
	 * @ORM\Column(type="string", length=255)
	 */
	private $image;

	/**
	 * @ORM\OneToMany(targetEntity=Materia::class, orphanRemoval=true, mappedBy="type")
	 * @Groups({"listing"})
	 */
	private $materias;

	public function __construct() {
		$this->materias = new ArrayCollection();
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

	public function getColor(): ?string {
		return $this->color;
	}

	public function setColor(string $color): self {
		$this->color = $color;

		return $this;
	}

	public function getImage(): ?string {
		return $this->image;
	}

	public function setImage(string $image): self {
		$this->image = $image;

		return $this;
	}

	/**
	 * @return Collection|Materia[]
	 */
	public function getMaterias(): Collection {
		return $this->materias;
	}

	public function addMateria(Materia $materia): self {
		if (!$this->materias->contains($materia)) {
			$this->materias[] = $materia;
			$materia->setType($this);
		}

		return $this;
	}

	public function removeMateria(Materia $materia): self {
		if ($this->materias->contains($materia)) {
			$this->materias->removeElement($materia);
			// set the owning side to null (unless already changed)
			if ($materia->getType() === $this) {
				$materia->setType(null);
			}
		}

		return $this;
	}
}
