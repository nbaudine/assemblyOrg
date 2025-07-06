<?php

namespace App\Entity;

use App\Repository\RondeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RondeRepository::class)]
class Ronde
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?\DateTime $start = null;

    #[ORM\Column]
    private ?\DateTime $end = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'rondes')]
    private Collection $sesUsers;

    public function __construct()
    {
        $this->sesUsers = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStart(): ?\DateTime
    {
        return $this->start;
    }

    public function setStart(\DateTime $start): static
    {
        $this->start = $start;

        return $this;
    }

    public function getEnd(): ?\DateTime
    {
        return $this->end;
    }

    public function setEnd(\DateTime $end): static
    {
        $this->end = $end;

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getSesUsers(): Collection
    {
        return $this->sesUsers;
    }

    public function addSesUser(User $sesUser): static
    {
        if (!$this->sesUsers->contains($sesUser)) {
            $this->sesUsers->add($sesUser);
        }

        return $this;
    }

    public function removeSesUser(User $sesUser): static
    {
        $this->sesUsers->removeElement($sesUser);

        return $this;
    }
}
