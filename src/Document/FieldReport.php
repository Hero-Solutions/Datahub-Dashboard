<?php

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

#[ODM\Document(collection: "reports_field")]
class FieldReport
{
    #[ODM\Id]
    private ?string $id = null;

    #[ODM\Field(type: "string")]
    private string $provider;

    #[ODM\Field(type: "int")]
    private int $total = 0;

    #[ODM\Field(type: "hash")]
    private array $minimum = [];

    #[ODM\Field(type: "hash")]
    private array $basic = [];

    #[ODM\Field(type: "hash")]
    private array $extended = [];

    public function __construct(string $provider)
    {
        $this->provider = $provider;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function setTotal(int $total): void
    {
        $this->total = $total;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): void
    {
        $this->provider = $provider;
    }

    public function getMinimum(): array
    {
        return $this->minimum;
    }

    public function setMinimum(array $minimum): void
    {
        $this->minimum = $minimum;
    }

    public function getBasic(): array
    {
        return $this->basic;
    }

    public function setBasic(array $basic): void
    {
        $this->basic = $basic;
    }

    public function getExtended(): array
    {
        return $this->extended;
    }

    public function setExtended(array $extended): void
    {
        $this->extended = $extended;
    }
}
