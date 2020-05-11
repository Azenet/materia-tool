<?php

namespace App\Entity;

use App\Repository\MateriaRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\Ignore;
use Symfony\Component\Serializer\Annotation\MaxDepth;

/**
 * @ORM\Entity(repositoryClass=MateriaRepository::class)
 */
class Materia {
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
	 * @ORM\ManyToOne(targetEntity=MateriaType::class, fetch="EAGER", inversedBy="materias")
	 * @ORM\JoinColumn(nullable=false)
	 * @MaxDepth(1)
	 * @Groups({"view", "diff"})
	 */
	private $type;

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

	public function getType(): ?MateriaType {
		return $this->type;
	}

	public function setType(?MateriaType $type): self {
		$this->type = $type;

		return $this;
	}

	public function __toString() {
		return $this->name;
	}
}
