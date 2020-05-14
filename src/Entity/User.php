<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Ignore;

/**
 * @ORM\Entity(repositoryClass=UserRepository::class)
 */
class User implements UserInterface {
	/**
	 * @ORM\Id()
	 * @ORM\Column(type="integer")
	 */
	private $id;

	/**
	 * @ORM\Column(type="string", length=255)
	 */
	private $email;

	/**
	 * @ORM\Column(type="string", length=255)
	 */
	private $username;

	/**
	 * @ORM\Column(type="json")
	 */
	private $roles = [];

	/**
	 * @ORM\Column(type="json")
	 */
	private $token = [];

	/**
	 * @ORM\OneToMany(targetEntity=MateriaLoadout::class, mappedBy="owner", orphanRemoval=true)
	 * @Ignore()
	 */
	private $materiaLoadouts;

	public const MATERIA_COORDINATES_PREFERENCE_ROW_NUMBER = 0;
	public const MATERIA_COORDINATES_PREFERENCE_LETTER = 1;
	public const MATERIA_COORDINATES_PREFERENCE_CHARACTER_ROW_NUMBER = 2;
	public const MATERIA_COORDINATES_PREFERENCE_CHARACTER_LETTER = 3;
	/**
	 * @ORM\Column(type="integer")
	 */
	private $materiaCoordinatesPreference = self::MATERIA_COORDINATES_PREFERENCE_ROW_NUMBER;

	public function __construct() {
		$this->materiaLoadouts = new ArrayCollection();
	}

	public function getId(): ?int {
		return $this->id;
	}

	public function setId($id) {
		$this->id = $id;

		return $this;
	}

	public function getEmail(): ?string {
		return $this->email;
	}

	public function setEmail(string $email): self {
		$this->email = $email;

		return $this;
	}

	/**
	 * A visual identifier that represents this user.
	 *
	 * @see UserInterface
	 */
	public function getUsername(): string {
		return (string) $this->username;
	}

	/**
	 * @see UserInterface
	 */
	public function getRoles(): array {
		$roles = $this->roles;
		// guarantee every user at least has ROLE_USER
		$roles[] = 'ROLE_USER';

		return array_unique($roles);
	}

	public function setRoles(array $roles): self {
		$this->roles = $roles;

		return $this;
	}

	/**
	 * @see UserInterface
	 */
	public function getPassword() {
		// not needed for apps that do not check user passwords
	}

	/**
	 * @see UserInterface
	 */
	public function getSalt() {
		// not needed for apps that do not check user passwords
	}

	/**
	 * @see UserInterface
	 */
	public function eraseCredentials() {
		// If you store any temporary, sensitive data on the user, clear it here
		// $this->plainPassword = null;
	}

	public function getToken(): ?array {
		return $this->token;
	}

	public function setToken(array $token): self {
		$this->token = $token;

		return $this;
	}

	/**
	 * @return Collection|MateriaLoadout[]
	 */
	public function getMateriaLoadouts(): Collection {
		return $this->materiaLoadouts;
	}

	public function addMateriaLoadout(MateriaLoadout $materiaLoadout): self {
		if (!$this->materiaLoadouts->contains($materiaLoadout)) {
			$this->materiaLoadouts[] = $materiaLoadout;
			$materiaLoadout->setOwner($this);
		}

		return $this;
	}

	public function removeMateriaLoadout(MateriaLoadout $materiaLoadout): self {
		if ($this->materiaLoadouts->contains($materiaLoadout)) {
			$this->materiaLoadouts->removeElement($materiaLoadout);
			// set the owning side to null (unless already changed)
			if ($materiaLoadout->getOwner() === $this) {
				$materiaLoadout->setOwner(null);
			}
		}

		return $this;
	}

	public function setUsername(string $username): self {
		$this->username = $username;

		return $this;
	}

	public function getMateriaCoordinatesPreference(): ?int {
		return $this->materiaCoordinatesPreference;
	}

	public function setMateriaCoordinatesPreference(int $materiaCoordinatesPreference): self {
		$this->materiaCoordinatesPreference = $materiaCoordinatesPreference;

		return $this;
	}
}
